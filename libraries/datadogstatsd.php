<?php
/**
 * Datadog implementation of StatsD
 * - Most of this code was stolen from: https://gist.github.com/1065177/5f7debc212724111f9f500733c626416f9f54ee6
 **/

class Datadogstatsd {

    static protected $__server = 'localhost';
    static protected $__serverPort = 8125;
    static private $__datadogHost;
    static private $__eventUrl = '/api/v1/events';
    static private $__apiKey;
    static private $__applicationKey;

    /**
     * @var int Config pass-through for CURLOPT_SSL_VERIFYHOST
     */
    static private $__apiCurlSslVerifyHost;

    /**
     * @var int Config pass-through for CURLOPT_SSL_VERIFYPEER
     */
    static private $__apiCurlSslVerifyPeer;

    /**
     * @var string Config for submitting events via 'TCP' vs 'UDP'; default 'UDP'
     */
    static private $__submitEventsOver = 'UDP';

    const OK        = 0;
    const WARNING   = 1;
    const CRITICAL  = 2;
    const UNKNOWN   = 3;

    /**
     * Log timing information
     *
     * @param string $stat The metric to in log timing info for.
     * @param float $time The elapsed time (ms) to log
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     */
    public static function timing($stat, $time, $sampleRate = 1.0, $tags = null) {
        static::send(array($stat => "$time|ms"), $sampleRate, $tags);
    }

    /**
     * A convenient alias for the timing function when used with micro-timing
     *
     * @param string $stat The metric name
     * @param float $time The elapsed time to log, IN SECONDS
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     **/
    public static function microtiming($stat, $time, $sampleRate = 1.0, $tags = null) {

        static::timing($stat, $time*1000, $sampleRate, $tags);

    }

    /**
     * Gauge
     *
     * @param string $stat The metric
     * @param float $value The value
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     **/
    public static function gauge($stat, $value, $sampleRate = 1.0, $tags = null) {
        static::send(array($stat => "$value|g"), $sampleRate, $tags);
    }

    /**
     * Histogram
     *
     * @param string $stat The metric
     * @param float $value The value
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     **/
    public static function histogram($stat, $value, $sampleRate = 1.0, $tags = null) {
        static::send(array($stat => "$value|h"), $sampleRate, $tags);
    }

    /**
     * Set
     *
     * @param string $stat The metric
     * @param float $value The value
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     **/
    public static function set($stat, $value, $sampleRate = 1.0, $tags = null) {
        static::send(array($stat => "$value|s"), $sampleRate, $tags);
    }


    /**
     * Increments one or more stats counters
     *
     * @param string|array $stats The metric(s) to increment.
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     * @return boolean
     **/
    public static function increment($stats, $sampleRate = 1.0, $tags = null) {
        static::updateStats($stats, 1, $sampleRate, $tags);
    }

    /**
     * Decrements one or more stats counters.
     *
     * @param string|array $stats The metric(s) to decrement.
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     * @return boolean
     **/
    public static function decrement($stats, $sampleRate = 1.0, $tags = null) {
        static::updateStats($stats, -1, $sampleRate, $tags);
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
    public static function updateStats($stats, $delta = 1, $sampleRate = 1.0, $tags = null) {
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
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     *
     * @return null
     **/
    public static function send($data, $sampleRate = 1.0, $tags = null) {
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
            if (is_array($tags) && count($tags) > 0) {
                $value .= '|#';
                foreach ($tags as $tag_key => $tag_val) {
                    if (isset($tag_val)) {
                        $value .= $tag_key . ':' . $tag_val . ',';
                    } else {
                        $value .= $tag_key . ',';
                    }
                }
                $value = substr($value, 0, -1);
            } elseif (!empty($tags)) {
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
    public static function service_check($name, $status, $tags = null,
                                         $hostname = null, $message = null, $timestamp = null) {
        $msg = "_sc|$name|$status";

        if ($timestamp !== null) {
            $msg .= sprintf("|d:%s", $timestamp);
        }
        if ($hostname !== null) {
            $msg .= sprintf("|h:%s", $hostname);
        }
        if (is_array($tags) && count($tags) > 0) {
            $msg .= sprintf('|#%s', join(',', $tags));
        } elseif (!empty($tags)) {
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

    public static function configure($apiKey, $applicationKey, $datadogHost = 'https://app.datadoghq.com',
                                     $submitEventsOver = 'TCP', $localStatsdServer = 'localhost', $localStatsdPort = 8125,
									 $curlVerifySslHost = 2, $curlVerifySslPeer = 1 ) {
        self::$__apiKey = $apiKey;
        self::$__applicationKey = $applicationKey;
        self::$__datadogHost = $datadogHost;
        self::$__submitEventsOver = $submitEventsOver;
        self::$__apiCurlSslVerifyHost = $curlVerifySslHost;
        self::$__apiCurlSslVerifyPeer = $curlVerifySslPeer;
        self::$__server = $localStatsdServer;
		self::$__serverPort = $localStatsdPort;
    }

    /**
     * Send an event to the Datadog HTTP api. Potentially slow, so avoid
     * making many call in a row if you don't want to stall your app.
     * Requires PHP >= 5.3.0
     *
     * @param string $title Title of the event
     * @param array $vals Optional values of the event. See
     *   http://docs.datadoghq.com/guides/dogstatsd/#events for the valid keys
     * @return null
     **/
    public static function event($title, $vals = array()) {

        // Assemble the request
        $vals['title'] = $title;

        // If sending events via UDP
        if (self::$__submitEventsOver === 'UDP') {
            return self::eventUdp($vals);
        }

        // Convert a comma-separated string of tags into an array
        if (array_key_exists('tags', $vals) && is_string($vals['tags'])) {
            $tags = explode(',', $vals['tags']);
            $vals['tags'] = array();
            foreach ($tags as $tag) {
                $vals['tags'][] = trim($tag);
            }
        }

        /**
         * @var boolean Flag for returning success
         */
        $success = true;

        // Get the url to POST to
        $url = self::$__datadogHost . self::$__eventUrl
             . '?api_key='          . self::$__apiKey
             . '&application_key='  . self::$__applicationKey;

        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, self::$__apiCurlSslVerifyPeer);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, self::$__apiCurlSslVerifyHost);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($vals));

        // Nab response and HTTP code
        $response_body = curl_exec($curl);
        $response_code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        try {

            // Check for cURL errors
            if ($curlErrorNum = curl_errno($curl)) {
                throw new Exception('Datadog event API call cURL issue #' . $curlErrorNum . ' - ' . curl_error($curl));
            }

            // Check response code is 202
            if ($response_code !== 200 && $response_code !== 202) {
                throw new Exception('Datadog event API call HTTP response not OK - ' . $response_code . '; response body: ' . $response_body);
            }

            // Check for empty response body
            if (!$response_body) {
                throw new Exception('Datadog event API call did not return a body');
            }

            // Decode JSON response
            if (!$decodedJson = json_decode($response_body, true)) {
                throw new Exception('Datadog event API call did not return a body that could be decoded via json_decode');
            }

            // Check JSON decoded "status" is OK from the Datadog API
            if ($decodedJson['status'] !== 'ok') {
                throw new Exception('Datadog event API response  status not "ok"; response body: ' . $response_body);
            }

        } catch (Exception $e) {

            $success = false;

            // Use error_log for API submission errors to avoid warnings/etc.
            error_log($e->getMessage());
        }

        curl_close($curl);
        return $success;
    }

    /**
     * Formats $vals array into event for submission to Datadog via UDP
     * @param array $vals Optional values of the event. See
     *   http://docs.datadoghq.com/guides/dogstatsd/#events for the valid keys
     * @return null
     */
    private static function eventUdp($vals) {

        // Format required values title and text
        $title = isset($vals['title']) ? (string) $vals['title'] : '';
        $text = isset($vals['text']) ? (string) $vals['text'] : '';

        // Format fields into string that follows Datadog event submission via UDP standards
        //   http://docs.datadoghq.com/guides/dogstatsd/#events
        $fields = '';
        $fields .= ($title);
        $fields .= ($text) ? '|' . $text : '|';
        $fields .= (isset($vals['date_happened'])) ? '|d:' . ((string) $vals['date_happened']) : '';
        $fields .= (isset($vals['hostname'])) ? '|h:' . ((string) $vals['hostname']) : '';
        $fields .= (isset($vals['priority'])) ? '|p:' . ((string) $vals['priority']) : '';
        $fields .= (isset($vals['alert_type'])) ? '|t:' . ((string) $vals['alert_type']) : '';
        $fields .= (isset($vals['tags'])) ? '|#' . implode(',', $vals['tags']) : '';

        $title_length = strlen($title);
        $text_length = strlen($text);

        self::report('_e{' . $title_length . ',' . $text_length . '}:' . $fields);

        return null;
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
