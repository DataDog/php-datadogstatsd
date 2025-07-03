<?php

namespace DataDog;

class OriginDetection
{
    // phpcs:disable
    // CGROUPV1BASECONTROLLER is the controller used to identify the container-id for cgroup v1
    const CGROUPV1BASECONTROLLER = "memory";

    // From
    // https://github.com/torvalds/linux/blob/5859a2b1991101d6b978f3feb5325dad39421f29/include/linux/proc_ns.h#L41-L49
    // Currently, host namespace inode number are hardcoded, which can be used to detect
    // if we're running in host namespace or not (does not work when running in DinD)
    const HOSTCGROUPNAMESPACEINODE = 0xEFFFFFFB;
    // phpcs:enable

    public function getFilepaths()
    {
        return array(
            // cgroupPath is the path to the cgroup file where we can find the container id if one exists.
            "cgroupPath" => "/proc/self/cgroup",

            // selfMountinfo is the path to the mountinfo path where we can find the container id in case
            // cgroup namespace is preventing the use of /proc/self/cgroup
            "selfMountInfoPath" => "/proc/self/mountinfo",

            // defaultCgroupMountPath is the default path to the cgroup mount point.
            "defaultCgroupMountPath" => "/sys/fs/cgroup",
        );
    }

    public function isHostCgroupNamespace()
    {
        // phpcs:disable
        $stat = @stat("/proc/self/ns/cgroup");
        // phpcs:enable
        if (!$stat) {
            return false;
        }
        $inode = isset($stat['ino']) ? $stat['ino'] : null;
        return $inode === self::HOSTCGROUPNAMESPACEINODE;
    }

    // parseCgroupNodePath parses /proc/self/cgroup and returns a map of controller to its associated cgroup node path.
    public function parseCgroupNodePath($lines)
    {
        $res = [];

        foreach (explode("\n", $lines) as $line) {
            $tokens = explode(':', $line);
            if (count($tokens) !== 3) {
                continue;
            }

            if ($tokens[1] === self::CGROUPV1BASECONTROLLER || $tokens[1] === '') {
                $res[$tokens[1]] = $tokens[2];
            }
        }

        return $res;
    }

    public function getCgroupInode($cgroupMountPath, $procSelfCgroupPath)
    {
        // phpcs:disable
        $content = @file_get_contents($procSelfCgroupPath);
        // phpcs:enable
        if ($content == false) {
            return '';
        }

        $cgroupControllersPaths = $this->parseCgroupNodePath($content);

        foreach ([self::CGROUPV1BASECONTROLLER , ''] as $controller) {
            if (!isset($cgroupControllersPaths[$controller])) {
                continue;
            }

            $segments = array(rtrim($cgroupMountPath, '/'),
                              trim($controller, '/'),
                              ltrim($cgroupControllersPaths[$controller], '/'));
            $path = implode("/", array_filter($segments, function ($segment) {
                return $segment !== null && $segment !== '';
            }));
            $inode = $this->inodeForPath($path);
            if ($inode !== '') {
                return $inode;
            }
        }

        return '';
    }

    // inodeForPath returns the inode number for the file at the given path.
    // The number is prefixed by 'in-' so the agent can identify this as an
    // inode and not a container id.
    private function inodeForPath($path)
    {
        // phpcs:disable
        $stat = @stat($path);
        // phpcs:enable
        if (!$stat || !isset($stat['ino'])) {
            return "";
        }

        return 'in-' . $stat['ino'];
    }

    // parseContainerID finds the first container ID reading from $handle and returns it.
    private function parseContainerID($handle)
    {
        $expLine = '/^\d+:[^:]*:(.+)$/';
        $uuidSource = "[0-9a-f]{8}[-_][0-9a-f]{4}[-_][0-9a-f]{4}[-_][0-9a-f]{4}[-_][0-9a-f]{12}";
        $containerSource = "[0-9a-f]{64}";
        $taskSource = "[0-9a-f]{32}-\\d+";

        $expContainerID = '/(' . $uuidSource . '|' . $containerSource . '|' . $taskSource . ')(?:.scope)?$/';

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line);
            if (preg_match($expLine, $line, $matches)) {
                if (count($matches) != 2) {
                    continue;
                }

                if (preg_match($expContainerID, $matches[1], $idMatch) && count($idMatch) == 2) {
                    return $idMatch[1];
                }
            }
        }

        return "";
    }

    // readContainerID attempts to return the container ID from the provided file path or empty on failure.
    public function readContainerID($fpath)
    {
        // phpcs:disable
        $handle = @fopen($fpath, 'r');
        // phpcs:enable
        if (!$handle) {
            return "";
        }

        $id = $this->parseContainerID($handle);

        fclose($handle);

        return $id;
    }

    // Parsing /proc/self/mountinfo is not always reliable in Kubernetes+containerd (at least)
    // We're still trying to use it as it may help in some cgroupv2 configurations (Docker, ECS, raw containerd)
    private function parseMountInfo($handle)
    {
        $containerRegexpStr = '([0-9a-f]{64})|([0-9a-f]{32}-\\d+)|([0-9a-f]{8}(-[0-9a-f]{4}){4}$)';
        $cIDMountInfoRegexp = '#.*/([^\s/]+)/(' . $containerRegexpStr . ')/[\S]*hostname#';

        while (($line = fgets($handle)) !== false) {
            preg_match_all($cIDMountInfoRegexp, $line, $allMatches, PREG_SET_ORDER);
            if (empty($allMatches)) {
                continue;
            }

            // Get the rightmost match
            $matches = $allMatches[count($allMatches) - 1];

            // Ensure the first capture group isn't the sandbox prefix
            $containerdSandboxPrefix = "sandboxes";
            if (count($matches) > 0 && $matches[1] !== $containerdSandboxPrefix) {
                return $matches[2];
            }
        }

        return "";
    }

    public function readMountInfo($path)
    {
        // phpcs:disable
        $handle = @fopen($path, 'r');
        // phpcs:enable
        if (!$handle) {
            return "";
        }

        $info = $this->parseMountInfo($handle);

        fclose($handle);

        return $info;
    }

    // getContainerID attempts to retrieve the container Id in the following order:
    // 1. If the user provides a container ID via the configuration, this is used.
    // 2. Reading the container ID from /proc/self/cgroup. Works with cgroup v1.
    // 3. Read the container Id from /proc/self/mountinfo. Sometimes, depending on container runtimes or
    //    mount settings this can contain a container id.
    // 4. Read the inode from /proc/self/cgroup.
    public function getContainerID($userProvidedId, $cgroupFallback)
    {
        if ($userProvidedId != "") {
            return $userProvidedId;
        }

        if ($cgroupFallback) {
            $paths = $this->getFilepaths();
            $containerID = $this->readContainerID($paths["cgroupPath"]);
            if ($containerID != "") {
                return $containerID;
            }

            $containerID = $this->readMountInfo($paths["selfMountInfoPath"]);
            if ($containerID != "") {
                return $containerID;
            }

            if ($this->isHostCgroupNamespace()) {
                return "";
            }

            $containerID = $this->getCgroupInode($paths["defaultCgroupMountPath"], $paths["cgroupPath"]);
            return $containerID;
        }

        return "";
    }
}
