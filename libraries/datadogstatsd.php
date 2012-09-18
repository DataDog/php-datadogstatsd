<?php
/**
 * Datadog implementation of StatsD
 * Added the ability to Tag!
 *
 * Most of this code was stolen from: https://gist.github.com/1065177/5f7debc212724111f9f500733c626416f9f54ee6
 * 
 * I did make it the most effecient UDP process possible, and add tagging.
 * 
 * @author Alex Corley <anthroprose@gmail.com>
 **/
 
class Datadogstatsd {

	static private $__server = 'localhost';
    static private $__datadogHost;
    static private $__eventUrl = '/api/v1/events';
    static private $__apiKey;
    static private $__applicationKey;

    /**
     * Log timing information
     *
     * @param string $stats The metric to in log timing info for.
     * @param float $time The ellapsed time (ms) to log
     * @param float|1 $sampleRate the rate (0-1) for sampling.
     **/
    public static function timing($stat, $time, $sampleRate = 1, array $tags = null) {
    	
        static::send(array($stat => "$time|ms"), $sampleRate, $tags);
		
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
	
		// Non - Blocking UDP I/O - Use IP Addresses!
		$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		socket_set_nonblock($socket);
		
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
        	
			socket_sendto($socket, "$stat:$value", strlen("$stat:$value"), 0, static::$__server, 8125);
			
		}

		socket_close($socket);
		
    }

    public static function configure($apiKey, $applicationKey, $datadogHost = 'https://app.datadoghq.com') {
        static::$__apiKey = $apiKey;
        static::$__applicationKey = $applicationKey;
        static::$__datadogHost = $datadogHost;
    }

    /**
     * Send an event to the Datadog HTTP api. Potentially slow, so avoid
     * making many call in a row if you don't want to stall your app.
     * Requires PHP >= 5.3.0 and the PECL extension pecl_http
     *
     * @param string $title Title of the event
     * @param array $vals Optional values of the event. See
     *   http://api.datadoghq.com/events for the valid keys
     * @return null
     **/
    public static function event($title, $vals = array()) {
        // Assemble the request
        $vals['title'] = $title;
        // Convert a comma-separated string of tags into an array
        if (array_key_exists('tags', $vals) && is_string($vals['tags'])) {
            $tags = explode(',', $vals['tags']);
            $vals['tags'] = array();
            foreach ($tags as $tag) {
                $vals['tags'][] = trim($tag);
            }
        }

        $body = json_encode($vals); // Added in PHP 5.3.0

        // Get the url to POST to
        $url = static::$__datadogHost . static::$__eventUrl
             . '?api_key='            . static::$__apiKey
             . '&application_key='    . static::$__applicationKey;

        // Set up the http request. Need the PECL pecl_http extension
        $r = new HttpRequest($url, HttpRequest::METH_POST);
        $r->addHeaders(array('Content-Type' => 'application/json'));
        $r->setBody($body);

        // Send, suppressing and logging any http errors
        try {
            $r->send();
        } catch (HttpException $ex) {
            error_log($ex);
        }
    }

}

?>
