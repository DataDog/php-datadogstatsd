<?php

namespace DataDog;

/**
 * Contract for creating an instance of TransportMechanism.
 * For Runtime creation of implementations of the TransportMechanism contract.
 * Used for statsd. Can be used to re-factor constructor logic.
 */
interface TransportFactory
{
    /**
     * @return TransportMechanism
     */
    public function create();
}
