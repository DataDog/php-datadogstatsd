<?php

namespace DataDog;

class BatchedDogStatsd extends DogStatsd
{
    private static $buffer = array();
    private static $bufferLength = 0;
    public static $maxBufferLength = 50;


    public function __construct(array $config = array())
    {
      # by default the telemetry is enabled for BatchedDogStatsd
      if (!isset($config["disable_telemetry"]))
      {
        $config["disable_telemetry"] = false;
      }
      parent::__construct($config);
    }

    public function report($message)
    {
        static::$buffer[] = $message;
        static::$bufferLength++;
        if (static::$bufferLength > static::$maxBufferLength) {
            $this->flush_buffer();
        }
    }

    public function flush_buffer()
    {
        $this->flush(join("\n", static::$buffer));
        static::$buffer = array();
        static::$bufferLength = 0;
    }
}
