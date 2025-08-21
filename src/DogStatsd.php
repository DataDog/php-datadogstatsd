<?php

namespace DataDog;

use DataDog\OriginDetection;
use Exception;

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
     * @var array Tags to apply to all metrics
     */
    private $globalTags;
    /**
     * @var string External data to apply to all metrics
     */
    private $externalData;
    /**
     * @var int Number of decimals to use when formatting numbers to strings
     */
    private $decimalPrecision;
    /**
     * @var string The prefix to apply to all metrics
     */
    private $metricPrefix;
    /**
     * @var string The tag cardinality.
     * Possible values are "none", "low", "orchestrator" and "high"
     */
    private $cardinality;
    /**
     * @var string The container ID field, used for origin detection
     */
    private $containerID;
    /**
     * @var (callable(\Throwable, string))|null The closure which is executed when there is a failure flushing metrics.
     */
    private $flushFailureHandler = null;

    // Telemetry
    private $disable_telemetry;
    private $telemetry_tags;
    private $metrics_sent;
    private $events_sent;
    private $service_checks_sent;
    private $bytes_sent;
    private $bytes_dropped;
    private $packets_sent;
    private $packets_dropped;

    // Used for the telemetry tags
    public static $version = '1.7.0';

    /**
     * DogStatsd constructor, takes a configuration array. The configuration can take any of the following values:
     * host,
     * port,
     * socket_path,
     * cardinality,
     * datadog_host,
     * global_tags,
     * decimal_precision,
     * metric_prefix,
     * disable_telemetry,
     * container_id,
     * origin_detection
     * flush_failure_handler
     *
     * @param array{
     *     host?: string,
     *     port?: int,
     *     socket_path?: string,
     *     cardinality?: "none"|"low"|"orchestrator"|"high",
     *     datadog_host?: string,
     *     global_tags?: string|string[]|array<string,string>|array<string,null>,
     *     decimal_precision?: int,
     *     metric_prefix?: string,
     *     disable_telemetry?: bool,
     *     container_id?: string,
     *     origin_detection?: bool,
     *     flush_failure_handler?: callable
     * } $config
     */
    public function __construct(array $config = array())
    {
        $urlHost = null;
        $urlPort = null;
        $urlSocketPath = null;

        if ($url = getenv("DD_DOGSTATSD_URL")) {
            if (substr($url, 0, 6) === 'udp://') {
                $parts = parse_url($url);
                $urlHost = $parts['host'];
                $urlPort = $parts['port'];
            }

            if (substr($url, 0, 7) === 'unix://') {
                $urlSocketPath = substr($url, 7);
            }
        }

        $this->host = isset($config['host'])
            ? $config['host'] : (getenv('DD_AGENT_HOST')
            ? getenv('DD_AGENT_HOST') : ($urlHost
            ? $urlHost : 'localhost'));

        $this->port = isset($config['port'])
            ? $config['port'] : (getenv('DD_DOGSTATSD_PORT')
            ? (int)getenv('DD_DOGSTATSD_PORT') : ($urlPort
            ? $urlPort : 8125));

        $this->socketPath = isset($config['socket_path'])
            ? $config['socket_path'] : ($urlSocketPath
            ? $urlSocketPath : null);

        $this->cardinality = isset($config['cardinality'])
            ? $config['cardinality'] : ((getenv('DD_CARDINALITY'))
            ? getenv('DD_CARDINALITY') : ((getenv('DATADOG_CARDINALITY'))
            ? getenv('DATADOG_CARDINALITY') : null));

        $this->datadogHost = isset($config['datadog_host']) ? $config['datadog_host'] : 'https://app.datadoghq.com';

        $this->decimalPrecision = isset($config['decimal_precision']) ? $config['decimal_precision'] : 2;

        $this->globalTags = isset($config['global_tags']) ? $config['global_tags'] : array();
        if (getenv('DD_ENTITY_ID')) {
            $this->globalTags['dd.internal.entity_id'] = getenv('DD_ENTITY_ID');
        }
        if (getenv('DD_ENV')) {
            $this->globalTags['env'] = getenv('DD_ENV');
        }
        if (getenv('DD_SERVICE')) {
            $this->globalTags['service'] = getenv('DD_SERVICE');
        }
        if (getenv('DD_VERSION')) {
            $this->globalTags['version'] = getenv('DD_VERSION');
        }

        $this->metricPrefix = isset($config['metric_prefix']) ? "$config[metric_prefix]." : '';

        // by default the telemetry is disabled
        $this->disable_telemetry = isset($config["disable_telemetry"]) ? $config["disable_telemetry"] : true;
        $transport_type = !is_null($this->socketPath) ? "uds" : "udp";
        $this->telemetry_tags = $this->serializeTags(
            array(
            "client" => "php",
            "client_version" => self::$version,
            "client_transport" => $transport_type)
        );

        $this->resetTelemetry();

        $originDetection = new OriginDetection();
        $originDetectionEnabled = $this->isOriginDetectionEnabled($config);

        // DD_EXTERNAL_ENV can be supplied by the Admission controller for origin detection.
        if ($originDetectionEnabled && getEnv('DD_EXTERNAL_ENV')) {
            $this->externalData = $this->sanitize(getenv('DD_EXTERNAL_ENV'));
        }

        $containerID = isset($config["container_id"]) ? $config["container_id"] : "";
        $this->containerID = $originDetection->getContainerID($containerID, $originDetectionEnabled);

        $this->flushFailureHandler = isset($config['flush_failure_handler'])
            ? $config['flush_failure_handler']
            : null;
    }

    /**
     * For boolean environment variables if the value is 0, f or false (case insensitive) the
     * value is treated as false.
     * All other values are true.
     **/
    private function isTrue($value)
    {
        switch (strtolower($value)) {
            case '0':
            case 'f':
            case 'false':
                return false;
        }

        return true;
    }

    private function isOriginDetectionEnabled($config)
    {
        if (isset($config["origin_detection"])) {
            return $config["origin_detection"];
        }

        if (getenv("DD_ORIGIN_DETECTION_ENABLED")) {
            $envVarValue = getenv("DD_ORIGIN_DETECTION_ENABLED");
            return $this->isTrue($envVarValue);
        }

        // default to true
        return true;
    }

    /**
     * Sanitize the DD_EXTERNAL_ENV input to ensure it doesn't contain invalid characters
     * that may break the protocol.
     * Removing any non-printable characters and `|`.
     */
    private function sanitize($input)
    {
        $output = '';

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $char = $input[$i];

            if (ctype_print($char) && $char !== '|') {
                $output .= $char;
            }
        }

        return $output;
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
     * Gets the origin detection fields to be appended to each metric.
     *
     * @param string|null $cardinality override cardinality to set.
     * @returns string
     */
    private function getFields($cardinality)
    {
        $cardinalityToUse = $this->validateCardinality($cardinality ?: $this->cardinality);

        $additionalFields = "";
        if ($this->externalData) {
            $additionalFields .= "|e:{$this->externalData}";
        }
        if ($cardinalityToUse) {
            $additionalFields .= "|card:{$cardinalityToUse}";
        }
        if ($this->containerID) {
            $additionalFields .= "|c:{$this->containerID}";
        }

        return $additionalFields;
    }


    /**
     * Reset the telemetry value to zero
     */
    private function flushTelemetry()
    {
        if ($this->disable_telemetry == true) {
            return "";
        }

        $additionalFields = $this->getFields(null);

        // phpcs:disable
        return "\ndatadog.dogstatsd.client.metrics:{$this->metrics_sent}|c{$this->telemetry_tags}{$additionalFields}"
             . "\ndatadog.dogstatsd.client.events:{$this->events_sent}|c{$this->telemetry_tags}{$additionalFields}"
             . "\ndatadog.dogstatsd.client.service_checks:{$this->service_checks_sent}|c{$this->telemetry_tags}{$additionalFields}"
             . "\ndatadog.dogstatsd.client.bytes_sent:{$this->bytes_sent}|c{$this->telemetry_tags}{$additionalFields}"
             . "\ndatadog.dogstatsd.client.bytes_dropped:{$this->bytes_dropped}|c{$this->telemetry_tags}{$additionalFields}"
             . "\ndatadog.dogstatsd.client.packets_sent:{$this->packets_sent}|c{$this->telemetry_tags}{$additionalFields}"
             . "\ndatadog.dogstatsd.client.packets_dropped:{$this->packets_dropped}|c{$this->telemetry_tags}{$additionalFields}";
        // phpcs:enable
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
    public function timing($stat, $time, $sampleRate = 1.0, $tags = null, $cardinality = null)
    {
        $time = $this->normalizeValue($time);
        $this->send(array($stat => "$time|ms"), $sampleRate, $tags, $cardinality);
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
    public function microtiming($stat, $time, $sampleRate = 1.0, $tags = null, $cardinality = null)
    {
        $this->timing($stat, $time * 1000, $sampleRate, $tags, $cardinality);
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
    public function gauge($stat, $value, $sampleRate = 1.0, $tags = null, $cardinality = null)
    {
        $value = $this->normalizeValue($value);
        $this->send(array($stat => "$value|g"), $sampleRate, $tags, $cardinality);
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
    public function histogram($stat, $value, $sampleRate = 1.0, $tags = null, $cardinality = null)
    {
        $value = $this->normalizeValue($value);
        $this->send(array($stat => "$value|h"), $sampleRate, $tags, $cardinality);
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
    public function distribution($stat, $value, $sampleRate = 1.0, $tags = null, $cardinality = null)
    {
        $value = $this->normalizeValue($value);
        $this->send(array($stat => "$value|d"), $sampleRate, $tags, $cardinality);
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
    public function set($stat, $value, $sampleRate = 1.0, $tags = null, $cardinality = null)
    {
        if (!is_string($value)) {
            $value = $this->normalizeValue($value);
        }

        $this->send(array($stat => "$value|s"), $sampleRate, $tags, $cardinality);
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
    public function increment($stats, $sampleRate = 1.0, $tags = null, $value = 1, $cardinality = null)
    {
        $this->updateStats($stats, $value, $sampleRate, $tags, $cardinality);
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
    public function decrement($stats, $sampleRate = 1.0, $tags = null, $value = -1, $cardinality = null)
    {
        if ($value > 0) {
            $value = -$value;
        }
        $this->updateStats($stats, $value, $sampleRate, $tags, $cardinality);
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
    public function updateStats($stats, $delta = 1, $sampleRate = 1.0, $tags = null, $cardinality = null)
    {
        $delta = $this->normalizeValue($delta);
        if (!is_array($stats)) {
            $stats = array($stats);
        }
        $data = array();
        foreach ($stats as $stat) {
            $data[$stat] = "$delta|c";
        }
        $this->send($data, $sampleRate, $tags, $cardinality);
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
            } elseif (is_int($tag)) {
                $tag_strings[] = $value;
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
    public function send($data, $sampleRate = 1.0, $tags = null, $cardinality = null)
    {
        $sampleRate = $this->normalizeValue($sampleRate);
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

        $fields = $this->getFields($cardinality);

        foreach ($sampledData as $stat => $value) {
            $value .= $this->serializeTags($tags);
            if ($fields) {
                $value .= $fields;
            }
            $this->report("{$this->metricPrefix}$stat:$value");
        }
    }

    /**
     * validateCardinality ensures the given cardinality is valid either null,
     * "none", "low", "orchestrator" or "high".
     * Return the lower case cardinality if it is valid.
     * If it is not valid, raises a warning and if the warning is handled
     * returns null if it is not valid.
     *
     * @param string $cardinality the cardinality
     */
    private function validateCardinality($cardinality)
    {
        if ($cardinality == null) {
            return null;
        }

        $cardinality = strtolower($cardinality);
        if (in_array($cardinality, ["none", "low", "orchestrator", "high"])) {
            return $cardinality;
        } else {
            trigger_error(
                "Cardinality must be one of the following: 'none', 'low', 'orchestrator' or 'high'.",
                E_USER_WARNING
            );
            return null;
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

    /**
     * @throws \Exception|\Throwable
     */
    public function flush($message)
    {
        $message .= $this->flushTelemetry();

        try {
            $res = $this->writeToSocket($message);
        } catch (\Throwable $e) {
            if ($this->flushFailureHandler === null) {
                throw $e;
            } else {
                call_user_func($this->flushFailureHandler, $e, $message);
                $res = false;
            }
        } catch (Exception $e) {
            if ($this->flushFailureHandler === null) {
                throw $e;
            } else {
                call_user_func($this->flushFailureHandler, $e, $message);
                $res = false;
            }
        }

        if ($res !== false) {
            $this->resetTelemetry();
            $this->bytes_sent += strlen($message);
            $this->packets_sent += 1;
        } else {
            $this->bytes_dropped += strlen($message);
            $this->packets_dropped += 1;
        }
    }

    /**
     * @param string $message
     * @return false|int
     * @throws \Exception|\Throwable
     */
    protected function writeToSocket($message)
    {
        try {
            // Non - Blocking UDP I/O - Use IP Addresses!
            if (!is_null($this->socketPath)) {
                $socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
            } elseif (filter_var($this->host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $socket = socket_create(AF_INET6, SOCK_DGRAM, SOL_UDP);
            } else {
                $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            }
            socket_set_nonblock($socket);

            if (!is_null($this->socketPath)) {
                $res = socket_sendto($socket, $message, strlen($message), 0, $this->socketPath);
            } else {
                $res = socket_sendto($socket, $message, strlen($message), 0, $this->host, $this->port);
            }

            return $res;
        } finally {
            if (isset($socket)) {
                socket_close($socket);
            }
        }
    }


     /**
     * Formats $vals array into event for submission to Datadog via UDP
     *
     * @param  array $vals Optional values of the event. See
     *                     https://docs.datadoghq.com/api/?lang=bash#post-an-event for the valid keys
     * @return bool
     */
    public function event($title, $vals = array())
    {
        // Format required values title and text
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

        return true;
    }

    /**
     * Normalize the value witout locale consideration before queuing the metric for sending
     *
     * @param float $value The value to normalize
     *
     * @return string Formatted value
     */
    private function normalizeValue($value)
    {
        // Controlls the way things are converted to a string.
        // Otherwise localization settings impact float to string conversion (e.x 1.3 -> 1,3 and 10000 => 10,000)

        return rtrim(rtrim(number_format((float) $value, $this->decimalPrecision, '.', ''), "0"), ".");
    }
}
