<?php

namespace DataDog;

/**
 * Datadog implementation of StatsD
 * Added the ability to Tag!
 *
 * Most of this code was stolen from: https://gist.github.com/1065177/5f7debc212724111f9f500733c626416f9f54ee6
 *
 * I did make it the most effecient UDP process possible, and add tagging.
 **/
class DogStatsd
{
    const OK = 0;
    const WARNING = 1;
    const CRITICAL = 2;
    const UNKNOWN = 3;

    /**
     * @var string
     */
    private $host;
    /**
     * @var int
     */
    private $port;
    /**
     * @var int
     */
    private $maxBufferSize;
    /**
     * @var null
     */
    private $namespace;
    /**
     * @var null
     */
    private $constantTags;
    /**
     * @var bool
     */
    private $useMs;

    /**
     * DogStatsd constructor.
     * @param string $host
     * @param int $port
     * @param int $maxBufferSize
     * @param string $namespace
     * @param array $constantTags
     * @param bool $useMs
     */
    public function __construct($host = 'localhost', $port = 8125, $maxBufferSize = 50, $namespace = null, $constantTags = [], $useMs = false)
    {
        $this->host = $host;
        $this->port = $port;
        $this->maxBufferSize = $maxBufferSize;
        $this->namespace = $namespace;
        $this->constantTags = $constantTags;
        $this->useMs = $useMs;
    }

    /**
     * Log timing information.
     *
     * @param string $stat The metric to in log timing info for.
     * @param float $time The ellapsed time (ms) to log
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array $tags
     */
    public function timing($stat, $time, $sampleRate = 1.0, array $tags = null)
    {
        $this->send(array($stat => "{$time}|ms"), $sampleRate, $tags);
    }

    /**
     * A convenient alias for the timing function when used with microtiming.
     *
     * @param string $stat The metric name
     * @param float $time The ellapsed time to log, IN SECONDS
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array $tags
     */
    public function microtiming($stat, $time, $sampleRate = 1.0, array $tags = null)
    {
        $this->timing($stat, $time * 1000, $sampleRate, $tags);
    }

    /**
     * Gauge.
     *
     * @param string $stat The metric
     * @param float $value The value
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array $tags
     */
    public function gauge($stat, $value, $sampleRate = 1.0, array $tags = null)
    {
        $this->send(array($stat => "{$value}|g"), $sampleRate, $tags);
    }

    /**
     * Histogram.
     *
     * @param string $stat The metric
     * @param float $value The value
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array $tags
     */
    public function histogram($stat, $value, $sampleRate = 1.0, array $tags = null)
    {
        $this->send(array($stat => "{$value}|h"), $sampleRate, $tags);
    }

    /**
     * Set.
     *
     * @param string $stat The metric
     * @param float $value The value
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array $tags
     */
    public function set($stat, $value, $sampleRate = 1.0, array $tags = null)
    {
        $this->send(array($stat => "{$value}|s"), $sampleRate, $tags);
    }

    /**
     * Increments one or more stats counters.
     *
     * @param string|array $stats The metric(s) to increment.
     * @param float $sampleRate the rate (0-1) for sampling.
     *
     * @param array $tags
     * @return bool
     */
    public function increment($stats, $sampleRate = 1.0, array $tags = null)
    {
        $this->updateStats($stats, 1, $sampleRate, $tags);
    }

    /**
     * Decrements one or more stats counters.
     *
     * @param string|array $stats The metric(s) to decrement.
     * @param float $sampleRate the rate (0-1) for sampling.
     *
     * @param array $tags
     * @return bool
     */
    public function decrement($stats, $sampleRate = 1.0, array $tags = null)
    {
        $this->updateStats($stats, -1, $sampleRate, $tags);
    }

    /**
     * Updates one or more stats counters by arbitrary amounts.
     *
     * @param string|array $stats      The metric(s) to update. Should be either a string or array of metrics.
     * @param int          $delta      The amount to increment/decrement each metric by.
     * @param float        $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags       Key Value array of Tag => Value, or single tag as string
     *
     * @return bool
     **/
    public function updateStats($stats, $delta = 1, $sampleRate = 1.0, array $tags = null)
    {
        if (!is_array($stats)) {
            $stats = array($stats);
        }
        $data = array();
        foreach ($stats as $stat) {
            $data[$stat] = "{$delta}|c";
        }
        $this->send($data, $sampleRate, $tags);
    }

    /**
     * Squirt the metrics over UDP.
     *
     * @param array        $data       Incoming Data
     * @param float        $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags       Key Value array of Tag => Value, or single tag as string
     **/
    public function send($data, $sampleRate = 1.0, array $tags = null)
    {
        // sampling
        $sampledData = array();
        if ($sampleRate < 1) {
            foreach ($data as $stat => $value) {
                if (mt_rand() / mt_getrandmax() <= $sampleRate) {
                    $sampledData[$stat] = "{$value}|@{$sampleRate}";
                }
            }
        } else {
            $sampledData = $data;
        }
        if (empty($sampledData)) {
            return;
        }
        foreach ($sampledData as $stat => $value) {
            if ($tags !== null && is_array($tags) && count($tags) > 0) {
                $value .= '|';
                foreach ($tags as $tag_key => $tag_val) {
                    $value .= '#'.$tag_key.':'.$tag_val.',';
                }
                $value = substr($value, 0, -1);
            } elseif (isset($tags) && !empty($tags)) {
                $value .= '|#'.$tags;
            }
            $this->report("{$stat}:{$value}");
        }
    }

    /**
     * Send a custom service check status over UDP.
     *
     * @param string       $name      service check name
     * @param int          $status    service check status code (see static::OK, static::WARNING,...)
     * @param array|string $tags      Key Value array of Tag => Value, or single tag as string
     * @param string       $hostname  hostname to associate with this service check status
     * @param string       $message   message to associate with this service check status
     * @param int          $timestamp timestamp for the service check status (defaults to now)
     **/
    public function serviceCheck($name, $status, array $tags = null, $hostname = null, $message = null, $timestamp = null)
    {
        $msg = "_sc|{$name}|{$status}";
        if ($timestamp !== null) {
            $msg .= sprintf('|d:%s', $timestamp);
        }
        if ($hostname !== null) {
            $msg .= sprintf('|h:%s', $hostname);
        }
        if ($tags !== null && is_array($tags) && count($tags) > 0) {
            $msg .= sprintf('|#%s', implode(',', $tags));
        } elseif (isset($tags) && !empty($tags)) {
            $msg .= sprintf('|#%s', $tags);
        }
        if ($message !== null) {
            $msg .= sprintf('|m:%s', $this->escapeSCMessage($message));
        }
        $this->report($msg);
    }

    private static function escapeSCMessage($msg)
    {
        return str_replace("m:", "m\:", str_replace("\n", "\\n", $msg));
    }

    public function report($udp_message)
    {
        $this->flush($udp_message);
    }

    public function flush($udp_message)
    {
        // Non - Blocking UDP I/O - Use IP Addresses!
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_nonblock($socket);
        socket_sendto($socket, $udp_message, strlen($udp_message), 0, $this->host, $this->port);
        socket_close($socket);
    }

    /**
     *
     * Fromat and send an event.
     * >>> statsd.event('Man down!', 'This server needs assistance.')
     * @param $title
     * @param $text
     * @param null $tags
     * @param null $alertType
     * @param null $aggregationKey
     * @param null $sourceTypeName
     * @param null $dateHappened
     * @param null $priority
     * @param null $hostname
     * @throws \Exception
     */
    public function event($title, $text, $tags = null, $alertType = null, $aggregationKey = null, $sourceTypeName = null, $dateHappened = null, $priority = null, $hostname = null)
    {
        # Append all client level tags to every event
        if (!empty($this->constantTags)) {
            if (isset($tags)) {
                $tags = array_merge($tags, $this->constantTags);
            }
            else {

                $tags = $this->constantTags;
            }

        }

        $string = sprintf('_e{%d,%d}:%s|%s', strlen($title), strlen($text), $title, $text);

        if (isset($dateHappened)) {
            $string = sprintf('%s|d:%d', $string, $dateHappened);
        }

        if (isset($hostname)) {
            $string = sprintf('%s|h:%s', $string, $hostname);
        }

        if (isset($aggregationKey)) {
            $string = sprintf('%s|k:%s', $string, $aggregationKey);
        }

        if ($priority) {
            $string = sprintf('%s|p:%s', $string, $priority);
        }

        if (isset($sourceTypeName)) {
            $string = sprintf('%s|s:%s', $string, $sourceTypeName);
        }

        if (isset($alertType)) {
            $string = sprintf('%s|t:%s', $string, $alertType);
        }

        if (isset($tags)) {
            $string = sprintf('%s|#%s', $string, implode(',', $tags));
        }

        if (strlen($string) > 8 * 1024) {

            throw new \Exception(sprintf('Event "%s" payload is too big (more that 8KB), event discarded', $title));
        }

        $this->send($string);
    }
}
