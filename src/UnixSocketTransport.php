<?php

namespace DataDog;

final class UnixSocketTransport implements TransportMechanism
{
    private $socket;
    private $socketPath;

    public function __construct($socketPath)
    {
        $this->socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
        socket_set_nonblock($this->socket);
        $this->socketPath = $socketPath;
    }

    public function sendMessage($message)
    {
        return (bool) socket_sendto($this->socket, $message, strlen($message), 0, $this->socketPath);
    }

    public function __destruct()
    {
        socket_close($this->socket);
    }
}
