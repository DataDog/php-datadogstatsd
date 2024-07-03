<?php

namespace DataDog;

/**
 * Class BatchedDogStatsd
 *
 * Useful for sending batches of UDP messages to DataDog after reaching a
 * configurable max buffer size of unsent messages.
 *
 * Buffer defaults to 50 messages;
 *
 * @package DataDog
 */
class BatchedDogStatsd extends DogStatsd
{
    private static $buffer = array();
    private static $bufferLength = 0;
    public static $maxBufferLength = 50;


    public function __construct(array $config = array())
    {
        // by default the telemetry is enabled for BatchedDogStatsd
        if (!isset($config["disable_telemetry"])) {
            $config["disable_telemetry"] = false;
        }
        parent::__construct($config);
    }

    /**
     * @param string $message
     */
    public function report($message)
    {
        static::$buffer[] = $message;
        static::$bufferLength++;
        if (static::$bufferLength > static::$maxBufferLength) {
            $this->flushBuffer();
        }
    }

    /**
     * @deprecated flush_buffer will be removed in future versions in favor of flushBuffer
     */
    public function flush_buffer() // phpcs:ignore
    {
        $this->flushBuffer();
    }


    public function flushBuffer()
    {
        $this->flush(join("\n", static::$buffer));
        static::$buffer = array();
        static::$bufferLength = 0;
    }

    /**
     * @return int
     */
    public static function getBufferLength()
    {
        return self::$bufferLength;
    }
}
