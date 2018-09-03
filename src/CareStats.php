<?php

namespace StatsDC;

class CareStats
{
    const OK        = 0;
    const WARNING   = 1;
    const CRITICAL  = 2;
    const UNKNOWN   = 3;

    /**
     * @var string
     */
    private $host;
    /**
     * @var int
     */
    private $port;
    /**
     * @var string
     */
    private $submitEventsOver = 'UDP';
    /**
     * @var int Config pass-through for CURLOPT_SSL_VERIFYHOST; defaults 2
     */

    /**
     * CareStats constructor, takes a configuration array. The configuration can take any of the following values:
     * host,
     * port
     * @param array $config
     */
    public function __construct(array $config = array())
    {
        $this->host = isset($config['host']) ? $config['host'] : '127.0.0.1';
        $this->port = isset($config['port']) ? $config['port'] : 8125;
    }

    /**
     * Log timing information
     *
     * @param string $stat The metric to in log timing info for.
     * @param float $time The elapsed time (ms) to log
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     */
    public function timing($stat, $time, $sampleRate = 1.0, $tags = null)
    {
        $this->send(array($stat => "$time|ms"), $sampleRate, $tags);
    }

    /**
     * A convenient alias for the timing function when used with micro-timing
     *
     * @param string $stat The metric name
     * @param float $time The elapsed time to log, IN SECONDS
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     **/
    public function microtiming($stat, $time, $sampleRate = 1.0, $tags = null)
    {
        $this->timing($stat, $time*1000, $sampleRate, $tags);
    }

    /**
     * Gauge
     *
     * @param string $stat The metric
     * @param float $value The value
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     **/
    public function gauge($stat, $value, $sampleRate = 1.0, $tags = null)
    {
        $this->send(array($stat => "$value|g"), $sampleRate, $tags);
    }

    /**
     * Histogram
     *
     * @param string $stat The metric
     * @param float $value The value
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     **/
    public function histogram($stat, $value, $sampleRate = 1.0, $tags = null)
    {
        $this->send(array($stat => "$value|h"), $sampleRate, $tags);
    }

    /**
     * Distribution
     *
     * @param string $stat The metric
     * @param float $value The value
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     **/
    public function distribution($stat, $value, $sampleRate = 1.0, $tags = null)
    {
        $this->send(array($stat => "$value|d"), $sampleRate, $tags);
    }

    /**
     * Set
     *
     * @param string $stat The metric
     * @param float $value The value
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     **/
    public function set($stat, $value, $sampleRate = 1.0, $tags = null)
    {
        $this->send(array($stat => "$value|s"), $sampleRate, $tags);
    }

    /**
     * Increments one or more stats counters
     *
     * @param string|array $stats The metric(s) to increment.
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     * @return boolean
     **/
    public function increment($stats, $sampleRate = 1.0, $tags = null)
    {
        $this->updateStats($stats, 1, $sampleRate, $tags);
    }

    /**
     * Decrements one or more stats counters.
     *
     * @param string|array $stats The metric(s) to decrement.
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     * @return boolean
     **/
    public function decrement($stats, $sampleRate = 1.0, $tags = null)
    {
        $this->updateStats($stats, -1, $sampleRate, $tags);
    }

    /**
     * Updates one or more stats counters by arbitrary amounts.
     *
     * @param string|array $stats The metric(s) to update. Should be either a string or array of metrics.
     * @param int $delta The amount to increment/decrement each metric by.
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     *
     * @return boolean
     **/
    public function updateStats($stats, $delta = 1, $sampleRate = 1.0, $tags = null)
    {
        if (!is_array($stats)) {
            $stats = array($stats);
        }
        $data = array();
        foreach ($stats as $stat) {
            $data[$stat] = "$delta|c";
        }
        $this->send($data, $sampleRate, $tags);
    }

    /**
     * Serialize tags to StatsD protocol
     *
     * @param string|array $tags The tags to be serialize
     *
     * @return string
     **/
    private function serialize_tags($tags)
    {
        if (is_array($tags) && count($tags) > 0) {
            $data = array();
            foreach ($tags as $tag_key => $tag_val) {
                if (isset($tag_val)) {
                    array_push($data, $tag_key . ':' . $tag_val);
                } else {
                    array_push($data, $tag_key);
                }
            }
            return '|#'.implode(",", $data);
        } elseif (!empty($tags)) {
            return '|#' . $tags;
        }
        return "";
    }

    /**
     * Squirt the metrics over UDP
     * @param array $data Incoming Data
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     *
     * @return null
     **/
    public function send($data, $sampleRate = 1.0, $tags = null)
    {
        // sampling
        $sampledData = array();
        if ($sampleRate < 1) {
            foreach ($data as $stat => $value) {
                if ((mt_rand() / mt_getrandmax()) <= $sampleRate) {
                    $sampledData[$stat] = "$value|@$sampleRate";
                }
            }
        } else {
            $sampledData = $data;
        }

        if (empty($sampledData)) {
            return;
        }

        foreach ($sampledData as $stat => $value) {
            $value .= $this->serialize_tags($tags);
            $this->report("$stat:$value");
        }
    }

    /**
     * Send a custom service check status over UDP
     * @param string $name service check name
     * @param int $status service check status code (see OK, WARNING,...)
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     * @param string $hostname hostname to associate with this service check status
     * @param string $message message to associate with this service check status
     * @param int $timestamp timestamp for the service check status (defaults to now)
     *
     * @return null
     **/
    public function service_check(
        $name,
        $status,
        $tags = null,
        $hostname = null,
        $message = null,
        $timestamp = null
    ) {
        $msg = "_sc|$name|$status";

        if ($timestamp !== null) {
            $msg .= sprintf("|d:%s", $timestamp);
        }
        if ($hostname !== null) {
            $msg .= sprintf("|h:%s", $hostname);
        }
        $msg .= $this->serialize_tags($tags);
        if ($message !== null) {
            $msg .= sprintf('|m:%s', $this->escape_sc_message($message));
        }

        $this->report($msg);
    }

    private function escape_sc_message($msg)
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
}
