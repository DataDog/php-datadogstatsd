<?php

namespace DataDog;

final class BridgingTransportFactory implements TransportFactory
{
    private $socketPath;
    private $host;
    private $port;

    public function __construct($socketPath = null, $host = null, $port = null)
    {
        $this->socketPath = $socketPath;
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * @return TransportMechanism
    */
    public function create()
    {
        if (is_null($this->socketPath)) {
            return new Ipv4UdpTransport(
                $this->host,
                $this->port
            );
        }

        return new UnixSocketTransport($this->socketPath);
    }
}
