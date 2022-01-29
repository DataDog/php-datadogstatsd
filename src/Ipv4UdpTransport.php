<?php

namespace DataDog;

final class Ipv4UdpTransport implements TransportMechanism
{
    private $host;
    private $port;
    private $socket;

    public function __construct($host, $port)
    {
        $this->host = $host;
        $this->port = $port;
        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_nonblock($this->socket);
    }

    public function sendMessage($message)
    {
        return (bool) socket_sendto($this->socket, $message, strlen($message), 0, $this->host, $this->port);
    }

    public function __destruct()
    {
        socket_close($this->socket);
    }
}
