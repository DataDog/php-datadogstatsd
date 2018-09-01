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

    /**
     * @param string $udp_message
     */
    public function report($udp_message)
    {
        static::$buffer[] = $udp_message;
        static::$bufferLength++;
        if (static::$bufferLength > static::$maxBufferLength) {
            $this->flushBuffer();
        }
    }

    public function flushBuffer()
    {
        $this->flush(join("\n", static::$buffer));
        static::$buffer = array();
        static::$bufferLength = 0;
    }
}
