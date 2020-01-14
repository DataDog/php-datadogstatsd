<?php

namespace DataDog\UnitTests;

use DataDog\BatchedDogStatsd;
use DataDog\TestHelpers\SocketSpy;
use DataDog\TestHelpers\SocketSpyTestCase;

class BatchedDogStatsdTest extends SocketSpyTestCase
{
    protected function setUp()
    {
        parent::setUp();

        // Flush the buffer to reset state for next test
        BatchedDogStatsd::$maxBufferLength = 50;
        $batchedDog = new BatchedDogStatsd();
        $batchedDog->flush_buffer();

        // Reset the SocketSpy state to get clean assertions.
        // @see \DataDog\SocketSpy
        global $socketSpy;
        $socketSpy = new SocketSpy();
    }

    public function testReportDoesNotSendIfBufferNotFilled()
    {
        $batchedDog = new BatchedDogStatsd();

        $batchedDog->report('some fake UDP message');

        $spy = $this->getSocketSpy();

        $this->assertSame(
            0,
            count($spy->argsFromSocketSendtoCalls),
            'Should not send UDP message until buffer is filled'
        );
    }

    public function testReportSendsOnceBufferIsFilled()
    {
        $batchedDog = new BatchedDogStatsd();

        $batchedDog::$maxBufferLength = 2;

        $udpMessage = 'some fake UDP message';
        $expectedUdpMessageOnceSent = $udpMessage . "1:21|g\n"
            . $udpMessage . "2:21|g\n"
            . $udpMessage . "3:21|g";

        $batchedDog->gauge($udpMessage . '1', 21);
        $batchedDog->gauge($udpMessage . '2', 21);

        $spy = $this->getSocketSpy();
        $this->assertSame(
            0,
            count($spy->argsFromSocketSendtoCalls),
            'Should not have sent any message until the buffer is full'
        );

        $batchedDog->gauge($udpMessage . '3', 21);

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send all buffered UDP messages once buffer is filled'
        );

        $this->assertSameTelemetry(
            $expectedUdpMessageOnceSent,
            $spy->argsFromSocketSendtoCalls[0][1],
            'Should concatenate UDP messages with newlines',
            array("metrics" => 3)
        );
    }
}
