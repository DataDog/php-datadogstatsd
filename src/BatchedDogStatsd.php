<?php

namespace DataDog;

class BatchedDogStatsd extends DogStatsd
{
    private static $buffer = array();
    private static $bufferLength = 0;
    public static $maxBufferLength = 50;

    public static function report($udp_message)
    {
        static::$buffer[] = $udp_message;
        static::$bufferLength++;
        if (static::$bufferLength > static::$maxBufferLength) {
            static::flushBuffer();
        }
    }

    public static function reportMetric($udp_message)
    {
        static::report($udp_message);
    }

    public static function flushBuffer()
    {
        static::flush(implode("\n", static::$buffer));
        static::$buffer = array();
        static::$bufferLength = 0;
    }
}
