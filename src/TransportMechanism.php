<?php

namespace DataDog;

/**
 * Contract for all-transports of DataDog statsd flush.
 * Can be used to for test-doubles, fallback implementations; future iterations.
 */
interface TransportMechanism
{
    /**
     * @return bool
     */
    public function sendMessage($message);
}
