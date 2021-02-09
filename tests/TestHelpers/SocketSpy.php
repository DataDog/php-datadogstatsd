<?php

namespace DataDog\TestHelpers;

/**
 * Class SocketSpy
 *
 * Useful for recording calls to stubbed global socket functions built into PHP
 *
 * @see \DataDog\SocketSpyTestCase
 * @package DataDog
 */
class SocketSpy
{
    /**
     * @var array
     */
    public $argsFromSocketCreateCalls = array();

    /**
     * @var array
     */
    public $socketCreateReturnValues = array();

    /**
     * @var array
     */
    public $argsFromSocketSetNonblockCalls = array();

    /**
     * @var array
     */
    public $argsFromSocketSendtoCalls = array();

    /**
     * @var array
     */
    public $argsFromSocketCloseCalls = array();

    /**
     * var boolean
     */
    public $returnErrorOnSend = false;

    /**
     * @param int $domain
     * @param int $type
     * @param int $protocol
     */
    public function socketCreateWasCalledWithArgs($domain, $type, $protocol)
    {
        $this->argsFromSocketCreateCalls[] = array(
            $domain,
            $type,
            $protocol
        );
    }

    /**
     * @param resource $socket
     */
    public function socketCreateDidReturn($socket)
    {
        $this->socketCreateReturnValues[] = $socket;
    }

    /**
     * @param resource $socket
     */
    public function socketSetNonblockWasCalledWithArg($socket)
    {
        $this->argsFromSocketSetNonblockCalls[] = $socket;
    }

    /**
     * @param resource $socket
     * @param string $buf
     * @param int $len
     * @param int $flags
     * @param string $addr
     * @param int $port
     */
    public function socketSendtoWasCalledWithArgs(
        $socket,
        $buf,
        $len,
        $flags,
        $addr,
        $port
    ) {
        if ($this->returnErrorOnSend === true) {
          return false;
        }

        $this->argsFromSocketSendtoCalls[] = array(
            $socket,
            $buf,
            $len,
            $flags,
            $addr,
            $port
        );
        return $len;
    }

    /**
     * @param resource $socket
     */
    public function socketCloseWasCalled($socket)
    {
        $this->argsFromSocketCloseCalls[] = $socket;
    }
}
