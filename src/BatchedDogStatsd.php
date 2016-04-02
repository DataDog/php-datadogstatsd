<?php

namespace DataDog;

class BatchedDogStatsd extends DogStatsd
{
    private static $buffer = array();
    private static $bufferLength = 0;
    public static $maxBufferLength = 50;

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
        $this->flush(implode("\n", static::$buffer));
        static::$buffer = array();
        static::$bufferLength = 0;
    }
}
