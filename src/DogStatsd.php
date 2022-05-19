<?php

namespace DataDog;

/**
 * Datadog implementation of StatsD
 **/

class DogStatsd
{
    // phpcs:disable
    const OK        = 0;
    const WARNING   = 1;
    const CRITICAL  = 2;
    const UNKNOWN   = 3;
    // phpcs:enable


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
    private $socketPath;
    /**
     * @var string
     */
    private $datadogHost;
    /**
     * @var string
     */
    private $apiKey;
    /**
     * @var string
     */
    private $appKey;
    /**
     * @var string Config for submitting events via 'TCP' vs 'UDP'; default 'UDP'
     */
    private $submitEventsOver = 'UDP';
    /**
     * @var int Config pass-through for CURLOPT_SSL_VERIFYHOST; defaults 2
     */
    private $curlVerifySslHost;
    /**
     * @var int Config pass-through for CURLOPT_SSL_VERIFYPEER; default 1
     */
    private $curlVerifySslPeer;
    /**
     * @var array Tags to apply to all metrics
     */
    private $globalTags;
    /**
     * @var int Number of decimals to use when formatting numbers to strings
     */
    private $decimalPrecision;
    /**
     * @var string The prefix to apply to all metrics
     */
    private $metricPrefix;

    private static $eventUrl = '/api/v1/events';

    // Used for the telemetry tags
    public static $version = '1.5.4';

    /**
     * DogStatsd constructor, takes a configuration array. The configuration can take any of the following values:
     * host,
     * port,
     * datadog_host,
     * curl_ssl_verify_host,
     * curl_ssl_verify_peer,
     * api_key and app_key,
     * global_tags,
     * decimal_precision,
     * metric_prefix
     *
     * @param array $config
     */
    public function __construct(array $config = array())
    {
        $this->host = isset($config['host'])
            ? $config['host'] : (getenv('DD_AGENT_HOST')
            ? getenv('DD_AGENT_HOST') : 'localhost');

        $this->port = isset($config['port'])
            ? $config['port'] : (getenv('DD_DOGSTATSD_PORT')
            ? (int)getenv('DD_DOGSTATSD_PORT') : 8125);

        $this->socketPath = isset($config['socket_path']) ? $config['socket_path'] : null;

        $this->datadogHost = isset($config['datadog_host']) ? $config['datadog_host'] : 'https://app.datadoghq.com';
        $this->curlVerifySslHost = isset($config['curl_ssl_verify_host']) ? $config['curl_ssl_verify_host'] : 2;
        $this->curlVerifySslPeer = isset($config['curl_ssl_verify_peer']) ? $config['curl_ssl_verify_peer'] : 1;

        $this->apiKey = isset($config['api_key']) ? $config['api_key'] : null;
        $this->appKey = isset($config['app_key']) ? $config['app_key'] : null;

        $this->decimalPrecision = isset($config['decimal_precision']) ? $config['decimal_precision'] : 2;

        $this->globalTags = isset($config['global_tags']) ? $config['global_tags'] : array();
        if (getenv('DD_ENTITY_ID')) {
            $this->globalTags['dd.internal.entity_id'] = getenv('DD_ENTITY_ID');
        }

        $this->metricPrefix = isset($config['metric_prefix']) ? "$config[metric_prefix]." : '';

        if ($this->apiKey !== null) {
            $this->submitEventsOver = 'TCP';
        }

        // by default the telemetry is disable
        $this->disable_telemetry = isset($config["disable_telemetry"]) ? $config["disable_telemetry"] : true;
        $transport_type = !is_null($this->socketPath) ? "uds" : "udp";
        $this->telemetry_tags = $this->serializeTags(
            array(
            "client" => "php",
            "client_version" => self::$version,
            "client_transport" => $transport_type)
        );

        $this->resetTelemetry();
    }

    /**
     * Reset the telemetry value to zero
     */
    private function resetTelemetry()
    {
        $this->metrics_sent = 0;
        $this->events_sent = 0;
        $this->service_checks_sent = 0;
        $this->bytes_sent = 0;
        $this->bytes_dropped = 0;
        $this->packets_sent = 0;
        $this->packets_dropped = 0;
    }
    /**
     * Reset the telemetry value to zero
     */
    private function flushTelemetry()
    {
        if ($this->disable_telemetry == true) {
            return "";
        }

        return "\ndatadog.dogstatsd.client.metrics:{$this->metrics_sent}|c{$this->telemetry_tags}"
             . "\ndatadog.dogstatsd.client.events:{$this->events_sent}|c{$this->telemetry_tags}"
             . "\ndatadog.dogstatsd.client.service_checks:{$this->service_checks_sent}|c{$this->telemetry_tags}"
             . "\ndatadog.dogstatsd.client.bytes_sent:{$this->bytes_sent}|c{$this->telemetry_tags}"
             . "\ndatadog.dogstatsd.client.bytes_dropped:{$this->bytes_dropped}|c{$this->telemetry_tags}"
             . "\ndatadog.dogstatsd.client.packets_sent:{$this->packets_sent}|c{$this->telemetry_tags}"
             . "\ndatadog.dogstatsd.client.packets_dropped:{$this->packets_dropped}|c{$this->telemetry_tags}";
    }

    /**
     * Log timing information
     *
     * @param  string       $stat       The metric to in log timing info for.
     * @param  float        $time       The elapsed time (ms) to log
     * @param  float        $sampleRate the rate (0-1) for sampling.
     * @param  array|string $tags       Key Value array of Tag => Value, or single tag as string
     * @return void
     */
    public function timing($stat, $time, $sampleRate = 1.0, $tags = null)
    {
        $time = $this->normalizeStat($time);
        $this->send(array($stat => "$time|ms"), $sampleRate, $tags);
    }

    /**
     * A convenient alias for the timing function when used with micro-timing
     *
     * @param  string       $stat       The metric name
     * @param  float        $time       The elapsed time to log, IN SECONDS
     * @param  float        $sampleRate the rate (0-1) for sampling.
     * @param  array|string $tags       Key Value array of Tag => Value, or single tag as string
     * @return void
     **/
    public function microtiming($stat, $time, $sampleRate = 1.0, $tags = null)
    {
        $this->timing($stat, $time * 1000, $sampleRate, $tags);
    }

    /**
     * Gauge
     *
     * @param  string       $stat       The metric
     * @param  float        $value      The value
     * @param  float        $sampleRate the rate (0-1) for sampling.
     * @param  array|string $tags       Key Value array of Tag => Value, or single tag as string
     * @return void
     **/
    public function gauge($stat, $value, $sampleRate = 1.0, $tags = null)
    {
        $value = $this->normalizeStat($value);
        $this->send(array($stat => "$value|g"), $sampleRate, $tags);
    }

    /**
     * Histogram
     *
     * @param  string       $stat       The metric
     * @param  float        $value      The value
     * @param  float        $sampleRate the rate (0-1) for sampling.
     * @param  array|string $tags       Key Value array of Tag => Value, or single tag as string
     * @return void
     **/
    public function histogram($stat, $value, $sampleRate = 1.0, $tags = null)
    {
        $value = $this->normalizeStat($value);
        $this->send(array($stat => "$value|h"), $sampleRate, $tags);
    }

    /**
     * Distribution
     *
     * @param  string       $stat       The metric
     * @param  float        $value      The value
     * @param  float        $sampleRate the rate (0-1) for sampling.
     * @param  array|string $tags       Key Value array of Tag => Value, or single tag as string
     * @return void
     **/
    public function distribution($stat, $value, $sampleRate = 1.0, $tags = null)
    {
        $value = $this->normalizeStat($value);
        $this->send(array($stat => "$value|d"), $sampleRate, $tags);
    }

    /**
     * Set
     *
     * @param  string       $stat       The metric
     * @param  string|float $value      The value
     * @param  float        $sampleRate the rate (0-1) for sampling.
     * @param  array|string $tags       Key Value array of Tag => Value, or single tag as string
     * @return void
     **/
    public function set($stat, $value, $sampleRate = 1.0, $tags = null)
    {
        if (!is_string($value)) {
            $value = $this->normalizeStat($value);
        }

        $this->send(array($stat => "$value|s"), $sampleRate, $tags);
    }


    /**
     * Increments one or more stats counters
     *
     * @param  string|array $stats      The metric(s) to increment.
     * @param  float        $sampleRate the rate (0-1) for sampling.
     * @param  array|string $tags       Key Value array of Tag => Value, or single tag as string
     * @param  int          $value      the amount to increment by (default 1)
     * @return void
     **/
    public function increment($stats, $sampleRate = 1.0, $tags = null, $value = 1)
    {
        $this->updateStats($stats, $value, $sampleRate, $tags);
    }

    /**
     * Decrements one or more stats counters.
     *
     * @param  string|array $stats      The metric(s) to decrement.
     * @param  float        $sampleRate the rate (0-1) for sampling.
     * @param  array|string $tags       Key Value array of Tag => Value, or single tag as string
     * @param  int          $value      the amount to decrement by (default -1)
     * @return void
     **/
    public function decrement($stats, $sampleRate = 1.0, $tags = null, $value = -1)
    {
        if ($value > 0) {
            $value = -$value;
        }
        $this->updateStats($stats, $value, $sampleRate, $tags);
    }

    /**
     * Updates one or more stats counters by arbitrary amounts.
     *
     * @param  string|array $stats      The metric(s) to update. Should be either a string or array of metrics.
     * @param  int          $delta      The amount to increment/decrement each metric by.
     * @param  float        $sampleRate the rate (0-1) for sampling.
     * @param  array|string $tags       Key Value array of Tag => Value, or single tag as string
     * @return void
     **/
    public function updateStats($stats, $delta = 1, $sampleRate = 1.0, $tags = null)
    {
        $delta = $this->normalizeStat($delta);
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
     * @param  string|array $tags The tags to be serialize
     * @return string
     **/
    private function serializeTags($tags)
    {
        $all_tags = array_merge(
            $this->normalizeTags($this->globalTags),
            $this->normalizeTags($tags)
        );

        if (count($all_tags) === 0) {
            return '';
        }
        $tag_strings = array();
        foreach ($all_tags as $tag => $value) {
            if ($value === null) {
                $tag_strings[] = $tag;
            } elseif (is_bool($value)) {
                $tag_strings[] = $tag . ':' . ($value === true ? 'true' : 'false');
            } else {
                $tag_strings[] = $tag . ':' . $value;
            }
        }
        return '|#' . implode(',', $tag_strings);
    }

    /**
     * Turns tags in any format into an array of tags
     *
     * @param  mixed $tags The tags to normalize
     * @return array
     */
    private function normalizeTags($tags)
    {
        if ($tags === null) {
            return array();
        }
        if (is_array($tags)) {
            $data = array();
            foreach ($tags as $tag_key => $tag_val) {
                if (isset($tag_val)) {
                    $data[$tag_key] = $tag_val;
                } else {
                    $data[$tag_key] = null;
                }
            }
            return $data;
        } else {
            $tags = explode(',', $tags);
            $data = array();
            foreach ($tags as $tag_string) {
                if (false === strpos($tag_string, ':')) {
                    $data[$tag_string] = null;
                } else {
                    list($key, $value) = explode(':', $tag_string, 2);
                    $data[$key] = $value;
                }
            }
            return $data;
        }
    }

    /**
     * Squirt the metrics over UDP
     *
     * @param  array        $data       Incoming Data
     * @param  float        $sampleRate the rate (0-1) for sampling.
     * @param  array|string $tags       Key Value array of Tag => Value, or single tag as string
     * @return void
     **/
    public function send($data, $sampleRate = 1.0, $tags = null)
    {
        $this->metrics_sent += count($data);
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
            $value .= $this->serializeTags($tags);
            $this->report("{$this->metricPrefix}$stat:$value");
        }
    }

    /**
     * @deprecated service_check will be removed in future versions in favor of serviceCheck
     *
     * Send a custom service check status over UDP
     * @param      string       $name      service check name
     * @param      int          $status    service check status code (see OK, WARNING,...)
     * @param      array|string $tags      Key Value array of Tag => Value, or single tag as string
     * @param      string       $hostname  hostname to associate with this service check status
     * @param      string       $message   message to associate with this service check status
     * @param      int          $timestamp timestamp for the service check status (defaults to now)
     * @return     void
     **/
    public function service_check( // phpcs:ignore
        $name,
        $status,
        $tags = null,
        $hostname = null,
        $message = null,
        $timestamp = null
    ) {
        $this->serviceCheck($name, $status, $tags, $hostname, $message, $timestamp);
    }

    /**
     * Send a custom service check status over UDP
     *
     * @param  string       $name      service check name
     * @param  int          $status    service check status code (see OK, WARNING,...)
     * @param  array|string $tags      Key Value array of Tag => Value, or single tag as string
     * @param  string       $hostname  hostname to associate with this service check status
     * @param  string       $message   message to associate with this service check status
     * @param  int          $timestamp timestamp for the service check status (defaults to now)
     * @return void
     **/
    public function serviceCheck(
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
        $msg .= $this->serializeTags($tags);
        if ($message !== null) {
            $msg .= sprintf('|m:%s', $this->escapeScMessage($message));
        }

        $this->service_checks_sent += 1;
        $this->report($msg);
    }

    private function escapeScMessage($msg)
    {
        return str_replace("m:", "m\:", str_replace("\n", "\\n", $msg));
    }

    public function report($message)
    {
        $this->flush($message);
    }

    public function flush($message)
    {
        $message .= $this->flushTelemetry();

        // Non - Blocking UDP I/O - Use IP Addresses!
        $socket = is_null($this->socketPath) ? socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)
            : socket_create(AF_UNIX, SOCK_DGRAM, 0);
        socket_set_nonblock($socket);

        if (!is_null($this->socketPath)) {
            $res = socket_sendto($socket, $message, strlen($message), 0, $this->socketPath);
        } else {
            $res = socket_sendto($socket, $message, strlen($message), 0, $this->host, $this->port);
        }

        if ($res !== false) {
            $this->resetTelemetry();
            $this->bytes_sent += strlen($message);
            $this->packets_sent += 1;
        } else {
            $this->bytes_dropped += strlen($message);
            $this->packets_dropped += 1;
        }

        socket_close($socket);
    }

    /**
     * Send an event to the Datadog HTTP api. Potentially slow, so avoid
     * making many call in a row if you don't want to stall your app.
     * Requires PHP >= 5.3.0
     *
     * @param  string $title Title of the event
     * @param  array  $vals  Optional values of the event. See
     *                       https://docs.datadoghq.com/api/?lang=bash#post-an-event
     *                       for the valid keys
     * @return null
     **/
    public function event($title, $vals = array())
    {

        // Assemble the request
        $vals['title'] = $title;

        // If sending events via UDP
        if ($this->submitEventsOver === 'UDP') { // FIX
            return $this->eventUdp($vals);
        }

        // Convert tags string or array into array of tags: ie ['key:value']
        if (isset($vals['tags'])) {
            $vals['tags'] = explode(",", substr($this->serializeTags($vals['tags']), 2));
        }

        /**
         * @var boolean Flag for returning success
         */
        $success = true;

        // Get the url to POST to
        $url = $this->datadogHost . self::$eventUrl
             . '?api_key='          . $this->apiKey
             . '&application_key='  . $this->appKey;

        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $this->curlVerifySslPeer);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $this->curlVerifySslHost);
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
                throw new \Exception('Datadog event API call cURL issue #' . $curlErrorNum . ' - ' . curl_error($curl));
            }

            // Check response code is 202
            if ($response_code !== 200 && $response_code !== 202) {
                throw new \Exception(
                    'Datadog event API call HTTP response not OK - '
                    . $response_code . '; response body: ' . $response_body
                );
            }

            // Check for empty response body
            if (!$response_body) {
                throw new \Exception('Datadog event API call did not return a body');
            }

            // Decode JSON response
            if (!$decodedJson = json_decode($response_body, true)) {
                throw new \Exception(
                    'Datadog event API call did not return a body'
                    . ' that could be decoded via json_decode'
                );
            }

            // Check JSON decoded "status" is OK from the Datadog API
            if ($decodedJson['status'] !== 'ok') {
                throw new \Exception(
                    'Datadog event API response  status not "ok"; response body: '
                    . $response_body
                );
            }
        } catch (\Exception $e) {
            $success = false;

            // Use error_log for API submission errors to avoid warnings/etc.
            error_log($e->getMessage());
        }

        curl_close($curl);
        return $success;
    }

    /**
     * Formats $vals array into event for submission to Datadog via UDP
     *
     * @param  array $vals Optional values of the event. See
     *                     https://docs.datadoghq.com/api/?lang=bash#post-an-event for the valid keys
     * @return null
     */
    private function eventUdp($vals)
    {

        // Format required values title and text
        $title = isset($vals['title']) ? (string) $vals['title'] : '';
        $text = isset($vals['text']) ? (string) $vals['text'] : '';

        // Format fields into string that follows Datadog event submission via UDP standards
        //   http://docs.datadoghq.com/guides/dogstatsd/#events
        $fields = '';
        $fields .= ($title);
        $textField = ($text) ? '|' . str_replace("\n", "\\n", $text) : '|';
        $fields .= $textField;
        $fields .= (isset($vals['date_happened'])) ? '|d:' . ((string) $vals['date_happened']) : '';
        $fields .= (isset($vals['hostname'])) ? '|h:' . ((string) $vals['hostname']) : '';
        $fields .= (isset($vals['aggregation_key'])) ? '|k:' . ((string) $vals['aggregation_key']) : '';
        $fields .= (isset($vals['priority'])) ? '|p:' . ((string) $vals['priority']) : '';
        $fields .= (isset($vals['source_type_name'])) ? '|s:' . ((string) $vals['source_type_name']) : '';
        $fields .= (isset($vals['alert_type'])) ? '|t:' . ((string) $vals['alert_type']) : '';
        $fields .= (isset($vals['tags'])) ? $this->serializeTags($vals['tags']) : '';

        $title_length = strlen($title);
        $text_length = strlen($textField) - 1;

        $this->events_sent += 1;
        $this->report('_e{' . $title_length . ',' . $text_length . '}:' . $fields);

        return null;
    }

    /**
     * Normalize the value witout locale consideration before queuing the metric for sending
     *
     * @param float $value The value to normalize
     *
     * @return string Formatted value
     */
    private function normalizeStat($value)
    {
        // Controlls the way things are converted to a string.
        // Otherwise localization settings impact float to string conversion (e.x 1.3 -> 1,3 and 10000 => 10,000)

        return rtrim(rtrim(number_format((float) $value, $this->decimalPrecision, '.', ''), "0"), ".");
    }
}
