<?php

namespace DataDog\TestHelpers;

use DataDog\DogStatsd;
use PHPUnit\Framework\TestCase;

/**
 * Making this variable global to this file is necessary for interacting with
 * the stubbed global functions below.
 */
$socketSpy = new SocketSpy();

/**
 * Class SocketSpyTestCase
 *
 * A PHPUnit TestCase useful for spying on calls to global built in socket
 * functions
 *
 * @package DataDog
 */
class SocketSpyTestCase extends TestCase
{
    /**
     * Set up a spy object to capture calls to global built in socket functions
     */
    protected function setUp()
    {
        global $socketSpy;

        $socketSpy = new SocketSpy();

        parent::setUp();
    }

    /**
     * @return \DataDog\TestHelpers\SocketSpy
     */
    protected function getSocketSpy()
    {
        global $socketSpy;

        return $socketSpy;
    }

    private function get_default(&$var, $default=null) {
      return isset($var) ? $var : $default;
    }

    public function assertSameTelemetry($expected, $actual, $message="", $params = array())
    {
        $metrics_sent = $this->get_default($params["metrics"], 1);
        $events_sent = $this->get_default($params["events"], 0);
        $service_checks_sent = $this->get_default($params["service_checks"], 0);
        $bytes_sent = $this->get_default($params["bytes_sent"], 0);
        $bytes_dropped = $this->get_default($params["bytes_dropped"], 0);
        $packets_sent = $this->get_default($params["packets_sent"], 0);
        $packets_dropped = $this->get_default($params["packets_dropped"], 0);
        $transport_type = $this->get_default($params["transport"], "udp");

        $version = DogStatsd::$version;
        $tags = "client:php,client_version:{$version},client_transport:{$transport_type}";
        $extra_tags = $this->get_default($params["tags"], "");
        if ($extra_tags != "")
        {
          $tags = $extra_tags.",".$tags;
        }

        $telemetry = "\ndatadog.dogstatsd.client.metrics:{$metrics_sent}|c|#{$tags}"
             . "\ndatadog.dogstatsd.client.events:{$events_sent}|c|#{$tags}"
             . "\ndatadog.dogstatsd.client.service_checks:{$service_checks_sent}|c|#{$tags}"
             . "\ndatadog.dogstatsd.client.bytes_sent:{$bytes_sent}|c|#{$tags}"
             . "\ndatadog.dogstatsd.client.bytes_dropped:{$bytes_dropped}|c|#{$tags}"
             . "\ndatadog.dogstatsd.client.packets_sent:{$packets_sent}|c|#{$tags}"
             . "\ndatadog.dogstatsd.client.packets_dropped:{$packets_dropped}|c|#{$tags}";

        $this->assertSame(
          $expected.$telemetry,
          $actual,
          $message
        );
    }

}
