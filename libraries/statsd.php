<?php

/*
 * A simple PHP class for interacting with a statsd server
 * @author John Crepezzi <john.crepezzi@gmail.com>
 */
class StatsD {

    // Default host
    private static $host = 'localhost';

    // Default port
    private static $port = 8125;
 
    // Set host
    public static function setHost($host) {
        self::$host = $host;
    }

    // Set port
    public static function setPort($port) {
        self::$port = $port;
    }

    // Record timing
    public static function timing($key, $time, $rate = 1) {
        self::send("$key:$time|ms", $rate);
    }

    // Time something
    public static function time_this($key, $callback, $rate = 1) {
        $begin = microtime(true);
        $callback();
        $time = floor((microtime(true) - $begin) / 1000);
        // And record
        self::timing($key, $time, $rate);
    }

    // Record counting
    public static function counting($key, $amount = 1, $rate = 1) {
        self::send("$key:$amount|c", $rate);
    }

    // Send
    private static function send($value, $rate) {
        $fp = fsockopen('udp://' . self::$host, self::$port, $errno, $errstr);
        // Will show warning if not opened, and return false
        if ($fp) {
            fwrite($fp, "$value|@$rate");
            fclose($fp);
        }
    }

}
