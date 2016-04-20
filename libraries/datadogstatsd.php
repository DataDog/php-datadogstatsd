<?php
/**
 * Datadog implementation of StatsD
 * Added the ability to Tag!
 *
 * Most of this code was stolen from: https://gist.github.com/1065177/5f7debc212724111f9f500733c626416f9f54ee6
 *
 * I did make it the most effecient UDP process possible, and add tagging.
 **/

class Datadogstatsd {

    static protected $__server = 'localhost';
    static protected $__serverPort = 8125;
    static private $__datadogHost;
    static private $__apiKey;
    static private $__applicationKey;

    const OK        = 0;
    const WARNING   = 1;
    const CRITICAL  = 2;
    const UNKNOWN   = 3;

    /**
     * Log timing information
     *
     * @param string $stat The metric to in log timing info for.
     * @param float $time The ellapsed time (ms) to log
     * @param float|1 $sampleRate the rate (0-1) for sampling.
     **/
    public static function timing($stat, $time, $sampleRate = 1, array $tags = null) {
        static::send(array($stat => "$time|ms"), $sampleRate, $tags);
    }

    /**
     * A convenient alias for the timing function when used with microtiming
     *
     * @param string $stat The metric name
     * @param float $time The ellapsed time to log, IN SECONDS
     * @param float|1 $sampleRate the rate (0-1) for sampling.
     **/
    public static function microtiming($stat, $time, $sampleRate = 1, array $tags = null) {

        static::timing($stat, $time*1000, $sampleRate, $tags);

    }

    /**
     * Gauge
     *
     * @param string $stat The metric
     * @param float $value The value
     * @param float|1 $sampleRate the rate (0-1) for sampling.
     **/
    public static function gauge($stat, $value, $sampleRate = 1, array $tags = null) {
        static::send(array($stat => "$value|g"), $sampleRate, $tags);
    }

    /**
     * Histogram
     *
     * @param string $stat The metric
     * @param float $value The value
     * @param float|1 $sampleRate the rate (0-1) for sampling.
     **/
    public static function histogram($stat, $value, $sampleRate = 1, array $tags = null) {
        static::send(array($stat => "$value|h"), $sampleRate, $tags);
    }

    /**
     * Set
     *
     * @param string $stat The metric
     * @param float $value The value
     * @param float|1 $sampleRate the rate (0-1) for sampling.
     **/
    public static function set($stat, $value, $sampleRate = 1, array $tags = null) {
        static::send(array($stat => "$value|s"), $sampleRate, $tags);
    }


    /**
     * Increments one or more stats counters
     *
     * @param string|array $stats The metric(s) to increment.
     * @param float|1 $sampleRate the rate (0-1) for sampling.
     * @return boolean
     **/
    public static function increment($stats, $sampleRate = 1, array $tags = null) {
        static::updateStats($stats, 1, $sampleRate, $tags);
    }

    /**
     * Decrements one or more stats counters.
     *
     * @param string|array $stats The metric(s) to decrement.
     * @param float|1 $sampleRate the rate (0-1) for sampling.
     * @return boolean
     **/
    public static function decrement($stats, $sampleRate = 1, array $tags = null) {
        static::updateStats($stats, -1, $sampleRate, $tags);
    }

    /**
     * Updates one or more stats counters by arbitrary amounts.
     *
     * @param string|array $stats The metric(s) to update. Should be either a string or array of metrics.
     * @param int|1 $delta The amount to increment/decrement each metric by.
     * @param float|1 $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     *
     * @return boolean
     **/
    public static function updateStats($stats, $delta = 1, $sampleRate = 1, array $tags = null) {
        if (!is_array($stats)) { $stats = array($stats); }
        $data = array();
        foreach($stats as $stat) {
            $data[$stat] = "$delta|c";
        }
        static::send($data, $sampleRate, $tags);
    }

    /**
     * Squirt the metrics over UDP
     * @param array $data Incoming Data
     * @param float|1 $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     *
     * @return null
     **/
    public static function send($data, $sampleRate = 1, array $tags = null) {
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

        if (empty($sampledData)) { return; }

        foreach ($sampledData as $stat => $value) {
            if ($tags !== NULL && is_array($tags) && count($tags) > 0) {
                $value .= '|';
                foreach ($tags as $tag_key => $tag_val) {
                    $value .= '#' . $tag_key . ':' . $tag_val . ',';
                }
                $value = substr($value, 0, -1);
            } elseif (isset($tags) && !empty($tags)) {
                $value .= '|#' . $tags;
            }
            static::report_metric("$stat:$value");
        }
    }
    /**
     * Send a custom service check status over UDP
     * @param string $name service check name
     * @param int $status service check status code (see static::OK, static::WARNING,...)
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     * @param string $hostname hostname to associate with this service check status
     * @param string $message message to associate with this service check status
     * @param int $timestamp timestamp for the service check status (defaults to now)
     *
     * @return null
     **/
    public static function service_check($name, $status, array $tags = null,
                                         $hostname = null, $message = null, $timestamp = null) {
        $msg = "_sc|$name|$status";

        if ($timestamp !== null) {
            $msg .= sprintf("|d:%s", $timestamp);
        }
        if ($hostname !== null) {
            $msg .= sprintf("|h:%s", $hostname);
        }
        if ($tags !== null && is_array($tags) && count($tags) > 0) {
            $msg .= sprintf('|#%s', join(',', $tags));
        } elseif (isset($tags) && !empty($tags)) {
            $msg .= sprintf('|#%s', $tags);
        }
        if ($message !== null) {
            $msg .= sprintf('|m:%s', static::escape_sc_message($message));
        }

        static::report($msg);
    }

    private static function escape_sc_message($msg) {
        return str_replace("m:", "m\:", str_replace("\n", "\\n", $msg));
    }

    public static function report($udp_message) {
        static::flush($udp_message);
    }

    public static function report_metric($udp_message) {
        static::report($udp_message);
    }

    public static function flush($udp_message) {
        // Non - Blocking UDP I/O - Use IP Addresses!
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_nonblock($socket);
        socket_sendto($socket, $udp_message, strlen($udp_message), 0, static::$__server, static::$__serverPort);
        socket_close($socket);

    }

    public static function configure($apiKey, $applicationKey, $datadogHost = 'https://app.datadoghq.com') {
        self::$__apiKey = $apiKey;
        self::$__applicationKey = $applicationKey;
        self::$__datadogHost = $datadogHost;
    }

    /**
     *
     * Fromat and send an event.
     * >>> statsd.event('Man down!', 'This server needs assistance.')
     *
     * @param $title
     * @param array $vals
     * @throws \Exception
     */
    public static function event($title, array $vals = [])
    {
        // Convert a comma-separated string of tags into an array
        if (array_key_exists('tags', $vals) && is_string($vals['tags'])) {
            $tags = explode(',', $vals['tags']);
            $vals['tags'] = array();
            foreach ($tags as $tag) {
                $vals['tags'][] = trim($tag);
            }
        }
        $text = isset($vals['text']) ? $vals['text'] : '';
        $string = sprintf('_e{%d,%d}:%s|%s', strlen($title), strlen($text), $title, $text);
        if (isset($vals['date_happened'])) {
            $string = sprintf('%s|d:%d', $string, $vals['date_happened']);
        }
        if (isset($vals['hostname'])) {
            $string = sprintf('%s|h:%s', $string, $vals['hostname']);
        }
        if (isset($vals['aggregation_key'])) {
            $string = sprintf('%s|k:%s', $string, $vals['aggregation_key']);
        }
        if ($vals['priority']) {
            $string = sprintf('%s|p:%s', $string, $vals['priority']);
        }
        if (isset($vals['source_type_name'])) {
            $string = sprintf('%s|s:%s', $string, $vals['source_type_name']);
        }
        if (isset($vals['alert_type'])) {
            $string = sprintf('%s|t:%s', $string, $vals['alert_type']);
        }
        if (isset($tags)) {
            $string = sprintf('%s|#%s', $string, implode(',', $tags));
        }
        if (strlen($string) > 8 * 1024) {
            throw new \Exception(sprintf('Event "%s" payload is too big (more that 8KB), event discarded', $title));
        }
        self::send($string);
    }
}

class BatchedDatadogstatsd extends Datadogstatsd {

    static private $__buffer = array();
    static private $__buffer_length = 0;
    static public $max_buffer_length = 50;

    public static function report($udp_message) {
        static::$__buffer[] = $udp_message;
        static::$__buffer_length++;
        if(static::$__buffer_length > static::$max_buffer_length) {
            static::flush_buffer();
        }
    }

    public static function report_metric($udp_message) {
        static::report($udp_message);
    }

    public static function flush_buffer() {
        static::flush(join("\n",static::$__buffer));
        static::$__buffer = array();
        static::$__buffer_length = 0;
    }
}
