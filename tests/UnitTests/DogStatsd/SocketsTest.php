<?php

namespace DataDog\UnitTests\DogStatsd;

use DateTime;
use ReflectionProperty;
use DataDog\DogStatsd;
use DataDog\TestHelpers\SocketSpyTestCase;

class SocketsTest extends SocketSpyTestCase
{
    private $oldAgentHost;
    private $oldDogstatsdPort;
    private $oldDogstatsdUrl;
    private $oldEntityId;
    private $oldVersion;
    private $oldEnv;
    private $oldExternalEnv;
    private $oldService;
    private $oldCardinality;
    private $oldOriginDetectionEnabled;

    public function set_up()
    {
        parent::set_up();

        // Reset the stubs for mt_rand() and mt_getrandmax()
        global $mt_rand_stub_return_value;
        global $mt_getrandmax_stub_return_value;
        $mt_rand_stub_return_value = null;
        $mt_getrandmax_stub_return_value = null;

        $this->oldAgentHost = getenv("DD_AGENT_HOST");
        $this->oldDogstatsdPort = getenv("DD_DOGSTATSD_PORT");
        $this->oldDogstatsdUrl = getenv("DD_DOGSTATSD_URL");
        $this->oldEntityId = getenv("DD_ENTITY_ID");
        $this->oldVersion = getenv("DD_VERSION");
        $this->oldEnv = getenv("DD_ENV");
        $this->oldExternalEnv = getenv("DD_EXTERNAL_ENV");
        $this->oldService = getenv("DD_SERVICE");
        $this->oldCardinality = getenv("DD_CARDINALITY");
        $this->oldOriginDetectionEnabled = getenv("DD_ORIGIN_DETECTION_ENABLED");

        putenv("DD_EXTERNAL_ENV");
    }

    protected function tear_down() {
        if ($this->oldAgentHost) {
            putenv("DD_AGENT_HOST=" . $this->oldAgentHost);
        } else {
            putenv("DD_AGENT_HOST");
        }

        if ($this->oldDogstatsdPort) {
            putenv("DD_DOGSTATSD_PORT=" . $this->oldDogstatsdPort);
        } else {
            putenv("DD_DOGSTATSD_PORT");
        }
        if ($this->oldDogstatsdUrl) {
            putenv("DD_DOGSTATSD_URL=" . $this->oldDogstatsdUrl);
        } else {
            putenv("DD_DOGSTATSD_URL");
        }

        if ($this->oldEntityId) {
            putenv("DD_ENTITY_ID=" . $this->oldEntityId);
        } else {
            putenv("DD_ENTITY_ID");
        }

        if ($this->oldVersion) {
            putenv("DD_VERSION=" . $this->oldVersion);
        } else {
            putenv("DD_VERSION");
        }

        if ($this->oldEnv) {
            putenv("DD_ENV=" . $this->oldEnv);
        } else {
            putenv("DD_ENV");
        }

        if ($this->oldExternalEnv) {
            putenv("DD_EXTERNAL_ENV=" . $this->oldExternalEnv);
        } else {
            putenv("DD_EXTERNAL_ENV");
        }

        if ($this->oldService) {
            putenv("DD_SERVICE=" . $this->oldService);
        } else {
            putenv("DD_SERVICE");
        }

        if ($this->oldCardinality) {
            putenv("DD_CARDINALITY=" . $this->oldCardinality);
        } else {
            putenv("DD_CARDINALITY");
        }

        if ($this->oldOriginDetectionEnabled) {
            putenv("DD_ORIGIN_DETECTION_ENABLED=" . $this->oldOriginDetectionEnabled);
        } else {
            putenv("DD_ORIGIN_DETECTION_ENABLED");
        }

        parent::tear_down();
    }


    /**
     * Origin detection via cgroups only works on Linux. On Linux this could
     * interfere with the tests and the container ID is non deterministic,
     * so for Linux we disable origin detection. Other OSes shouldn't pick
     * up the container Id, so we keep origin detection enabled to ensure it
     * doesn't raise unwanted errors.
     */
    function disableOriginDetectionLinux() {
        if (strtoupper(substr(PHP_OS, 0, 5)) == 'LINUX') {
            putenv("DD_ORIGIN_DETECTION_ENABLED=false");
        }
    }

    static function getPrivate($object, $property) {
        $reflector = new ReflectionProperty(get_class($object), $property);
        $reflector->setAccessible(true);
        return $reflector->getValue($object);
    }

    private function get_default(&$var, $default=null) {
      return isset($var) ? $var : $default;
    }

    public function assertSameWithTelemetry($expected, $actual, $message="", $params = array())
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

    public function testHostAndPortFromEnvVar()
    {
        putenv("DD_AGENT_HOST=myenvvarhost");
        putenv("DD_DOGSTATSD_PORT=1234");
        $this->disableOriginDetectionLinux();

        $dog = new DogStatsd();
        $this->assertSame(
            'myenvvarhost',
            self::getPrivate($dog, 'host'),
            'Should retrieve host from env var'
        );
        $this->assertSame(
            1234,
            self::getPrivate($dog, 'port'),
            'Should retrieve port from env var'
        );
    }

    public function testHostAndPortFromArgs()
    {
        putenv("DD_AGENT_HOST=myenvvarhost");
        putenv("DD_DOGSTATSD_PORT=1234");
        $this->disableOriginDetectionLinux();
        $dog = new DogStatsd(array(
            'host' => 'myhost',
            'port' => 4321
        ));
        $this->assertSame(
            'myhost',
            self::getPrivate($dog, 'host'),
            'Should retrieve host from args not env var'
        );
        $this->assertSame(
            4321,
            self::getPrivate($dog, 'port'),
            'Should retrieve port from args not env var'
        );
    }

    public function testHostAndPortFromUrl()
    {
        putenv("DD_DOGSTATSD_URL=udp://oh.my.address:1234");
        $this->disableOriginDetectionLinux();
        $dog = new DogStatsd();
        $this->assertSame(
            'oh.my.address',
            self::getPrivate($dog, 'host'),
            'Should retrieve host from url'
        );
        $this->assertSame(
            1234,
            self::getPrivate($dog, 'port'),
            'Should retrieve port from url'
        );
    }

    public function testDefaultHostAndPort()
    {
        $dog = new DogStatsd();
        $this->assertSame(
            'localhost',
            self::getPrivate($dog, 'host'),
            'Should retrieve defaulthost'
        );
        $this->assertSame(
            8125,
            self::getPrivate($dog, 'port'),
            'Should retrieve default port'
        );
        $this->assertSame(
            null,
            self::getPrivate($dog, 'socketPath'),
            'Should retrieve default port'
        );
    }

    public function testSocketPathFromEnvVar()
    {
        putenv("DD_DOGSTATSD_URL=unix:///var/run/datadog/dsd.socket");
        $dog = new DogStatsd();
        $this->assertSame(
            '/var/run/datadog/dsd.socket',
            self::getPrivate($dog, 'socketPath'),
            'Should retrieve socket_path from env var'
        );
    }

    public function testSocketPathFromArgs()
    {
        putenv("DD_DOGSTATSD_URL='unix:///var/run/datadog/ignored.socket'");
        $dog = new DogStatsd([
            'socket_path' => '/var/run/datadog/dsd.socket',
        ]);
        $this->assertSame(
            '/var/run/datadog/dsd.socket',
            self::getPrivate($dog, 'socketPath'),
            'Should retrieve socket_path from args not env var'
        );
    }

    public function testTiming()
    {
        $stat = 'some.timing_metric';
        $time = 43;
        $sampleRate = 1.0;
        $tags = array('horse' => 'cart');
        $expectedUdpMessage = 'some.timing_metric:43|ms|#horse:cart';

        $dog = new DogStatsd(array('disable_telemetry' => false));

        $dog->timing(
            $stat,
            $time,
            $sampleRate,
            $tags
        );

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1]
        );
    }

    public function testMicrotiming()
    {
        $stat = 'some.microtiming_metric';
        $time = 26;
        $sampleRate = 1.0;
        $tags = array('tuba' => 'solo');
        $expectedUdpMessage = 'some.microtiming_metric:26000|ms|#tuba:solo';

        $dog = new DogStatsd(array('disable_telemetry' => false));

        $dog->microtiming(
            $stat,
            $time,
            $sampleRate,
            $tags
        );

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1]
        );
    }

    public function testGauge()
    {
        $this->disableOriginDetectionLinux();
        
        $stat = 'some.gauge_metric';
        $value = 5;
        $sampleRate = 1.0;
        $tags = array('baseball' => 'cap');
        $expectedUdpMessage = 'some.gauge_metric:5|g|#baseball:cap';

        $dog = new DogStatsd(array('disable_telemetry' => false));

        $dog->gauge(
            $stat,
            $value,
            $sampleRate,
            $tags
        );

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1]
        );
    }

    public function testGaugeZero()
    {
        $this->disableOriginDetectionLinux();

        $stat = 'some.gauge_metric';
        $value = 0;
        $sampleRate = 1.0;
        $tags = array('baseball' => 'cap');
        $expectedUdpMessage = 'some.gauge_metric:0|g|#baseball:cap';

        $dog = new DogStatsd(array('disable_telemetry' => false));

        $dog->gauge(
            $stat,
            $value,
            $sampleRate,
            $tags
        );

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1]
        );
    }

    public function testHistogram()
    {
        $this->disableOriginDetectionLinux();

        $stat = 'some.histogram_metric';
        $value = 109;
        $sampleRate = 1.0;
        $tags = array('happy' => 'days');
        $expectedUdpMessage = 'some.histogram_metric:109|h|#happy:days';

        $dog = new DogStatsd(array("disable_telemetry" => false));

        $dog->histogram(
            $stat,
            $value,
            $sampleRate,
            $tags
        );

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1]
        );
    }

    public function testDistribution()
    {
        $this->disableOriginDetectionLinux();

        $stat = 'some.distribution_metric';
        $value = 7;
        $sampleRate = 1.0;
        $tags = array('floppy' => 'hat');
        $expectedUdpMessage = 'some.distribution_metric:7|d|#floppy:hat';

        $dog = new DogStatsd(array("disable_telemetry" => false));

        $dog->distribution(
            $stat,
            $value,
            $sampleRate,
            $tags
        );

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1]
        );
    }

    /**
     * @dataProvider setProvider
     * @param $stat
     * @param $value
     * @param $sampleRate
     * @param $tags
     * @param $expectedUdpMessage
     */
    public function testSet($stat, $value, $sampleRate, $tags, $expectedUdpMessage)
    {
        $this->disableOriginDetectionLinux();

        $dog = new DogStatsd(array("disable_telemetry" => false));

        $dog->set(
            $stat,
            $value,
            $sampleRate,
            $tags
        );

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1]
        );
    }

    public function setProvider()
    {
        return array(
            'integer' => array(
                'some.int_metric',
                1,
                1.0,
                array('little' => 'bit'),
                'some.int_metric:1|s|#little:bit'
            ),
            'float' => array(
                'some.float_metric',
                3.1415926535898,
                1.0,
                array('little' => 'bit'),
                'some.float_metric:3.14|s|#little:bit'
            ),
            'string' => array(
                'some.string_metric',
                'uniqueval',
                1.0,
                array('little' => 'bit'),
                'some.string_metric:uniqueval|s|#little:bit'
            ),
        );
    }

    /**
     * @dataProvider serviceCheckProvider
     * @param $name
     * @param $status
     * @param $tags
     * @param $hostname
     * @param $message
     * @param $timestamp
     * @param $expectedUdpMessage
     */
    public function testServiceCheck(
        $name,
        $status,
        $tags,
        $hostname,
        $message,
        $timestamp,
        $expectedUdpMessage
    ) {
        $this->disableOriginDetectionLinux();

        $dog = new DogStatsd(array("disable_telemetry" => false));

        $dog->service_check(
            $name,
            $status,
            $tags,
            $hostname,
            $message,
            $timestamp
        );

        $spy = $this->getSocketSpy();

        $argsPassedToSocketSendto = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendto[1],
            '',
            array('metrics' => 0, 'service_checks' => 1)
        );
    }

    public function serviceCheckProvider()
    {
        $this->disableOriginDetectionLinux();

        $name = 'neat-service';
        $status = DogStatsd::CRITICAL;
        $tags = array('red' => 'balloon', 'green' => 'ham');
        $hostname = 'some-host.com';
        $message = 'Important message';
        $timestamp = $this->getDeterministicTimestamp();

        return array(
            'all arguments provided' => array(
                $name,
                $status,
                $tags,
                $hostname,
                $message,
                $timestamp,
                '_sc|neat-service|2|d:1535776860|h:some-host.com|#red:balloon,green:ham|m:Important message',
            ),
            'without tags' => array(
                $name,
                $status,
                null,
                $hostname,
                $message,
                $timestamp,
                '_sc|neat-service|2|d:1535776860|h:some-host.com|m:Important message',
            ),
            'without hostname' => array(
                $name,
                $status,
                $tags,
                null,
                $message,
                $timestamp,
                '_sc|neat-service|2|d:1535776860|#red:balloon,green:ham|m:Important message',
            ),
            'without message' => array(
                $name,
                $status,
                $tags,
                $hostname,
                null,
                $timestamp,
                '_sc|neat-service|2|d:1535776860|h:some-host.com|#red:balloon,green:ham',
            ),
            'without timestamp' => array(
                $name,
                $status,
                $tags,
                $hostname,
                $message,
                null,
                '_sc|neat-service|2|h:some-host.com|#red:balloon,green:ham|m:Important message',
            ),
        );
    }

    public function testSend()
    {
        $this->disableOriginDetectionLinux();

        $sampleRate = 1.0;
        $tags = array(
            'cowboy' => 'hat'
        );

        $expectedUdpMessage1 = 'foo.metric:893|s|#cowboy:hat';
        $expectedUdpMessage2 = 'bar.metric:4|s|#cowboy:hat';

        $dog = new DogStatsd(array("disable_telemetry" => false));

        $dog->set("foo.metric", 893, $sampleRate, $tags);
        $dog->set("bar.metric", 4, $sampleRate, $tags);

        $spy = $this->getSocketSpy();

        $argsPassedToSocketSendtoCall1 = $spy->argsFromSocketSendtoCalls[0];
        $argsPassedToSocketSendtoCall2 = $spy->argsFromSocketSendtoCalls[1];

        $this->assertSame(
            2,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 2 UDP messages'
        );

        $this->assertSameWithTelemetry(
            $expectedUdpMessage1,
            $argsPassedToSocketSendtoCall1[1],
            'First UDP message should be correct'
        );

        $this->assertSameWithTelemetry(
            $expectedUdpMessage2,
            $argsPassedToSocketSendtoCall2[1],
            'Second UDP message should be correct',
            array("bytes_sent" => 693, "packets_sent" => 1)
        );
    }

    public function testSendSerializesTagAsString()
    {
        $this->disableOriginDetectionLinux();

        $data = array(
            'foo.metric' => '82|s',
        );
        $sampleRate = 1.0;
        $tag = 'string:tag';

        $expectedUdpMessage = 'foo.metric:82|s|#string:tag';

        $dog = new DogStatsd(array("disable_telemetry" => false));

        $dog->send($data, $sampleRate, $tag);

        $spy = $this->getSocketSpy();

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $spy->argsFromSocketSendtoCalls[0][1],
            'Should serialize tag passed as string'
        );
    }

    public function testSendSerializesMessageWithoutTags()
    {
        $this->disableOriginDetectionLinux();

        $data = array(
            'foo.metric' => '19872|h',
        );
        $sampleRate = 1.0;
        $tag = null;

        $expectedUdpMessage = 'foo.metric:19872|h';

        $dog = new DogStatsd(array("disable_telemetry" => false));

        $dog->send($data, $sampleRate, $tag);

        $spy = $this->getSocketSpy();

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $spy->argsFromSocketSendtoCalls[0][1],
            'Should serialize message when no tags are provided'
        );
    }

    public function testSendReturnsEarlyWhenPassedEmptyData()
    {
        $this->disableOriginDetectionLinux();

        $dog = new DogStatsd(array("disable_telemetry" => false));

        $dog->send(array());

        $spy = $this->getSocketSpy();

        $this->assertSame(
            0,
            count($spy->argsFromSocketSendtoCalls),
            'Should not send UDP message when event data is empty'
        );
    }

    public function testSendSendsWhenRandCalculationLessThanSampleRate()
    {
        $this->disableOriginDetectionLinux();

        global $mt_rand_stub_return_value;
        global $mt_getrandmax_stub_return_value;

        // 0.333 will be less than the sample rate, 0.5
        $mt_rand_stub_return_value = 1;
        $mt_getrandmax_stub_return_value = 3;

        $data = array(
            'foo.metric' => '469|s'
        );
        $sampleRate = 0.5;

        $dog = new DogStatsd(array("disable_telemetry" => false));

        $dog->send($data, $sampleRate);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );
    }

    public function testSendSendsWhenRandCalculationEqualToSampleRate()
    {
        $this->disableOriginDetectionLinux();

        global $mt_rand_stub_return_value;
        global $mt_getrandmax_stub_return_value;

        // 1/2 will be equal to the sample rate, 0.5
        $mt_rand_stub_return_value = 1;
        $mt_getrandmax_stub_return_value = 2;

        $data = array(
            'foo.metric' => '23|g'
        );
        $sampleRate = 0.5;

        $dog = new DogStatsd(array("disable_telemetry" => false));

        $dog->send($data, $sampleRate);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );
    }

    public function testSendDoesNotSendWhenRandCalculationGreaterThanSampleRate()
    {
        $this->disableOriginDetectionLinux();

        global $mt_rand_stub_return_value;
        global $mt_getrandmax_stub_return_value;

        // 1/1 will be greater than the sample rate, 0.5
        $mt_rand_stub_return_value = 1;
        $mt_getrandmax_stub_return_value = 1;

        $data = array(
            'foo.metric' => '23|g'
        );
        $sampleRate = 0.5;

        $dog = new DogStatsd(array("disable_telemetry" => false));

        $dog->send($data, $sampleRate);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            0,
            count($spy->argsFromSocketSendtoCalls),
            'Should not send a UDP message'
        );
    }

    public function testIncrement()
    {
        $this->disableOriginDetectionLinux();

        $stats = array(
            'foo.metric',
        );

        $expectedUdpMessage = 'foo.metric:1|c';

        $dog = new DogStatsd(array("disable_telemetry" => false));

        $dog->increment($stats);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendto = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendto[1],
            'Should send the expected message'
        );
    }

    public function testDecrement()
    {
        $this->disableOriginDetectionLinux();

        $stats = array(
            'foo.metric',
        );

        $expectedUdpMessage = 'foo.metric:-1|c';

        $dog = new DogStatsd(array("disable_telemetry" => false));

        $dog->decrement($stats);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendto = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendto[1],
            'Should send the expected message'
        );
    }

    public function testDecrementWithValueGreaterThanOne()
    {
        $this->disableOriginDetectionLinux();

        $stats = array(
            'foo.metric',
        );

        $expectedUdpMessage = 'foo.metric:-9|c';

        $dog = new DogStatsd(array("disable_telemetry" => false));

        $dog->decrement($stats, 1.0, null, 9);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendto = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendto[1],
            'Should send the expected message'
        );
    }

    public function testDecrementWithValueLessThanOne()
    {
        $this->disableOriginDetectionLinux();

        $stats = array(
            'foo.metric',
        );

        $expectedUdpMessage = 'foo.metric:-47|c';

        $dog = new DogStatsd(array("disable_telemetry" => false));

        $dog->decrement($stats, 1.0, null, -47);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendto = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendto[1],
            'Should send the expected message'
        );
    }

    public function testUpdateStats()
    {
        $this->disableOriginDetectionLinux();

        $stats = array(
            'foo.metric',
            'bar.metric',
        );
        $delta = 3;
        $sampleRate = 1.0;
        $tags = array(
            'every' => 'day',
        );

        $expectedUdpMessage1 = 'foo.metric:3|c|#every:day';
        $expectedUdpMessage2 = 'bar.metric:3|c|#every:day';

        $dog = new DogStatsd(array("disable_telemetry" => false));

        $dog->updateStats($stats, $delta, $sampleRate, $tags);

        $spy = $this->getSocketSpy();

        $argsPassedToSocketSendto = $spy->argsFromSocketSendtoCalls;

        $this->assertSame(
            2,
            count($argsPassedToSocketSendto),
            'Should send 2 UDP messages'
        );

        # we send multiple metrics at once, but they are push over the network
        # one by one. This means that the first telemetry payload will count 2
        # metrics, while the second will count 0 has it's still pushing to the
        # network. We should concatenate payload.

        $this->assertSameWithTelemetry(
            $expectedUdpMessage1,
            $argsPassedToSocketSendto[0][1],
            'Should send the expected message for the first call',
            array("metrics" => 2)
        );

        $this->assertSameWithTelemetry(
            $expectedUdpMessage2,
            $argsPassedToSocketSendto[1][1],
            'Should send the expected message for the first call',
            array("metrics" => 0, "bytes_sent" => 690, "packets_sent" => 1)
        );
    }

    public function testUpdateStatsWithStringMetric()
    {
        $this->disableOriginDetectionLinux();

        $stats = 'foo.metric';
        $delta = -45;
        $sampleRate = 1.0;
        $tags = array(
            'long' => 'walk',
        );

        $expectedUdpMessage = 'foo.metric:-45|c|#long:walk';

        $dog = new DogStatsd(array("disable_telemetry" => false));

        $dog->updateStats($stats, $delta, $sampleRate, $tags);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendto = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendto[1],
            'Should send the expected message'
        );
    }

    public function testReport()
    {
        $expectedUdpMessage = 'some fake UDP message';

        $dog = new DogStatsd(array("disable_telemetry" => true));

        $dog->report($expectedUdpMessage);

        $spy = $this->getSocketSpy();

        $argsPassedToSocketSendto = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSame(
            $expectedUdpMessage,
            $argsPassedToSocketSendto[1]
        );

        $this->assertSame(
            strlen($expectedUdpMessage),
            $argsPassedToSocketSendto[2]
        );
    }

    public function testFlushUdp()
    {
        $this->disableOriginDetectionLinux();

        $expectedUdpMessage = 'foo';

        $dog = new DogStatsd(array("disable_telemetry" => true));

        $dog->flush($expectedUdpMessage);

        $spy = $this->getSocketSpy();

        $socketCreateReturnValue = $spy->socketCreateReturnValues[0];

        $this->assertCount(
            1,
            $spy->argsFromSocketCreateCalls,
            'Should call socket_create once'
        );

        $this->assertSame(
            array(AF_INET, SOCK_DGRAM, SOL_UDP),
            $spy->argsFromSocketCreateCalls[0],
            'Should create a UDP socket to send datagrams over IPv4'
        );

        $this->assertCount(
            1,
            $spy->argsFromSocketSetNonblockCalls,
            'Should call socket_set_nonblock once'
        );

        $this->assertSame(
            $socketCreateReturnValue,
            $spy->argsFromSocketSetNonblockCalls[0],
            'Should call socket_set_nonblock once with the socket previously created'
        );

        $this->assertCount(
            1,
            $spy->argsFromSocketSendtoCalls,
            'Should call socket_sendto once'
        );

        $this->assertSame(
            array(
                $socketCreateReturnValue,
                $expectedUdpMessage,
                strlen($expectedUdpMessage),
                0,
                'localhost',
                8125
            ),
            $spy->argsFromSocketSendtoCalls[0],
            'Should send the expected message to localhost:8125'
        );

        $this->assertCount(
            1,
            $spy->argsFromSocketCloseCalls,
            'Should call socket_close once'
        );

        $this->assertSame(
            $socketCreateReturnValue,
            $spy->socketCreateReturnValues[0],
            'Should close the socket previously created'
        );
    }

    public function testFlushUds()
    {
        $this->disableOriginDetectionLinux();

        $expectedUdsMessage = 'foo';
        $expectedUdsSocketPath = '/path/to/some.socket';

        $dog = new Dogstatsd(array("socket_path" => $expectedUdsSocketPath, "disable_telemetry" => true));

        $dog->flush($expectedUdsMessage);

        $spy = $this->getSocketSpy();

        $socketCreateReturnValue = $spy->socketCreateReturnValues[0];

        $this->assertCount(
            1,
            $spy->argsFromSocketCreateCalls,
            'Should call socket_create once'
        );

        $this->assertSame(
            array(AF_UNIX, SOCK_DGRAM, 0),
            $spy->argsFromSocketCreateCalls[0],
            'Should create a UDS socket to send datagrams over UDS'
        );

        $this->assertCount(
            1,
            $spy->argsFromSocketSetNonblockCalls,
            'Should call socket_set_nonblock once'
        );

        $this->assertSame(
            $socketCreateReturnValue,
            $spy->argsFromSocketSetNonblockCalls[0],
            'Should call socket_set_nonblock once with the socket previously created'
        );

        $this->assertCount(
            1,
            $spy->argsFromSocketSendtoCalls,
            'Should call socket_sendto once'
        );

        $this->assertSame(
            array(
                $socketCreateReturnValue,
                $expectedUdsMessage,
                strlen($expectedUdsMessage),
                0,
                $expectedUdsSocketPath,
                null
            ),
            $spy->argsFromSocketSendtoCalls[0],
            'Should send the expected message to /path/to/some.socket'
        );

        $this->assertCount(
            1,
            $spy->argsFromSocketCloseCalls,
            'Should call socket_close once'
        );

        $this->assertSame(
            $socketCreateReturnValue,
            $spy->socketCreateReturnValues[0],
            'Should close the socket previously created'
        );
    }

    public function testEventUdp()
    {
        $this->disableOriginDetectionLinux();

        $eventTitle = 'Some event title';
        $eventVals = array(
            'text'             => "Some event text\nthat spans 2 lines",
            'date_happened'    => $this->getDeterministicTimestamp(),
            'hostname'         => 'some.host.com',
            'aggregation_key'  => '83e2cf',
            'priority'         => 'normal',
            'source_type_name' => 'jenkins',
            'alert_type'       => 'warning',
            'tags'             => array(
                'chicken' => 'nachos',
            ),
        );

        $expectedUdpMessage = "_e{16,35}:Some event title|Some event text\\nthat spans 2 lines|d:1535776860|h:some.host.com|k:83e2cf|p:normal|s:jenkins|t:warning|#chicken:nachos";

        $dog = new DogStatsd(array("disable_telemetry" => false));

        $dog->event($eventTitle, $eventVals);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendto = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendto[1],
            "",
            array("events" => 1, "metrics" => 0)
        );
    }

    /**
     * todo This test is technically correct, but it points out a flaw in
     *       the way events are handled. It is probably best to return early
     *       and avoid sending an empty event payload if no meaningful data
     *       is passed.
     */
    public function testEventUdpWithEmptyValues()
    {
        $this->disableOriginDetectionLinux();

        $eventTitle = '';

        $expectedUdpMessage = "_e{0,0}:|";

        $dog = new DogStatsd(array("disable_telemetry" => false));

        $dog->event($eventTitle);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendto = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendto[1],
            "",
            array("events" => 1, "metrics" => 0)
        );
    }

    public function testGlobalTags()
    {
        $this->disableOriginDetectionLinux();

        $dog = new DogStatsd(array(
            'global_tags' => array(
                'my_tag' => 'tag_value',
            ),
            'disable_telemetry' => false
        ));
        $dog->timing('metric', 42, 1.0);
        $spy = $this->getSocketSpy();
        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );
        $expectedUdpMessage = 'metric:42|ms|#my_tag:tag_value';
        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1],
            "",
            array("tags" => "my_tag:tag_value")
        );
    }

    public function testGlobalTagsWithEntityIdFromEnvVar()
    {
        $this->disableOriginDetectionLinux();

        putenv("DD_ENTITY_ID=04652bb7-19b7-11e9-9cc6-42010a9c016d");
        $dog = new DogStatsd(array(
            'global_tags' => array(
                'my_tag' => 'tag_value',
            ),
            'disable_telemetry' => false
        ));
        $dog->timing('metric', 42, 1.0);
        $spy = $this->getSocketSpy();
        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );
        $expectedUdpMessage = 'metric:42|ms|#my_tag:tag_value,dd.internal.entity_id:04652bb7-19b7-11e9-9cc6-42010a9c016d';
        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1],
            "",
            array("tags" => "my_tag:tag_value,dd.internal.entity_id:04652bb7-19b7-11e9-9cc6-42010a9c016d")
        );
    }

    public function testGlobalTagsAreSupplementedWithLocalTags()
    {
        $this->disableOriginDetectionLinux();

        $dog = new DogStatsd(array(
            'global_tags' => array(
                'my_tag' => 'tag_value',
            ),
            'disable_telemetry' => false
        ));
        $dog->timing('metric', 42, 1.0, array('other_tag' => 'other_value'));
        $spy = $this->getSocketSpy();
        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );
        $expectedUdpMessage = 'metric:42|ms|#my_tag:tag_value,other_tag:other_value';
        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1],
            "",
            array("tags" => "my_tag:tag_value")
        );
    }


    public function testGlobalTagsAreReplacedWithConflictingLocalTags()
    {
        $this->disableOriginDetectionLinux();

        $dog = new DogStatsd(array(
            'global_tags' => array(
                'my_tag' => 'tag_value',
            ),
            'disable_telemetry' => false
        ));

        $dog->timing('metric', 42, 1.0, array('my_tag' => 'other_value'));
        $spy = $this->getSocketSpy();
        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );
        $expectedUdpMessage = 'metric:42|ms|#my_tag:other_value';
        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1],
            "",
            array("tags" => "my_tag:tag_value")
        );
    }

    public function testTelemetryDefault()
    {
        $this->disableOriginDetectionLinux();

        $dog = new DogStatsd();
        $dog->gauge('metric', 42);

        $this->assertSame(
            'metric:42|g',
            $this->getSocketSpy()->argsFromSocketSendtoCalls[0][1]
        );
    }

    public function testTelemetryEnable()
    {
        $this->disableOriginDetectionLinux();

        $dog = new DogStatsd(array("disable_telemetry" => false));
        $dog->gauge('metric', 42);

        $this->assertSameWithTelemetry(
            'metric:42|g',
            $this->getSocketSpy()->argsFromSocketSendtoCalls[0][1]
        );
    }

    public function testTelemetryAllDataType()
    {
        $this->disableOriginDetectionLinux();

        $dog = new DogStatsd(array("disable_telemetry" => false));

        $dog->timing('test', 21);
        $this->assertSameWithTelemetry('test:21|ms', $this->getSocketSpy()->argsFromSocketSendtoCalls[0][1]);

        $dog->gauge('test', 21);
        $this->assertSameWithTelemetry('test:21|g', $this->getSocketSpy()->argsFromSocketSendtoCalls[1][1], "", array("bytes_sent" => 675, "packets_sent" => 1));

        $dog->histogram('test', 21);
        $this->assertSameWithTelemetry('test:21|h', $this->getSocketSpy()->argsFromSocketSendtoCalls[2][1], "", array("bytes_sent" => 676, "packets_sent" => 1));

        $dog->distribution('test', 21);
        $this->assertSameWithTelemetry('test:21|d', $this->getSocketSpy()->argsFromSocketSendtoCalls[3][1], "", array("bytes_sent" => 676, "packets_sent" => 1));

        $dog->set('test', 21);
        $this->assertSameWithTelemetry('test:21|s', $this->getSocketSpy()->argsFromSocketSendtoCalls[4][1], "", array("bytes_sent" => 676, "packets_sent" => 1));

        $dog->increment('test');
        $this->assertSameWithTelemetry('test:1|c', $this->getSocketSpy()->argsFromSocketSendtoCalls[5][1], "", array("bytes_sent" => 676, "packets_sent" => 1));

        $dog->decrement('test');
        $this->assertSameWithTelemetry('test:-1|c', $this->getSocketSpy()->argsFromSocketSendtoCalls[6][1], "", array("bytes_sent" => 675, "packets_sent" => 1));

        $dog->event('ev', array('text' => 'text'));
        $this->assertSameWithTelemetry('_e{2,4}:ev|text', $this->getSocketSpy()->argsFromSocketSendtoCalls[7][1], "", array("bytes_sent" => 676, "packets_sent" => 1, "metrics" => 0, "events" => 1));

        $dog->service_check('sc', 0);
        $this->assertSameWithTelemetry('_sc|sc|0', $this->getSocketSpy()->argsFromSocketSendtoCalls[8][1], "", array("bytes_sent" => 682, "packets_sent" => 1, "metrics" => 0, "service_checks" => 1));

        # force flush to get the telemetry about the last message sent
        $dog->flush("");
        $this->assertSameWithTelemetry('', $this->getSocketSpy()->argsFromSocketSendtoCalls[9][1], "", array("bytes_sent" => 675, "packets_sent" => 1, "metrics" => 0));
    }

    public function testTelemetryNetworkError()
    {
        $this->disableOriginDetectionLinux();

        $dog = new DogStatsd(array("disable_telemetry" => false));
        $this->getSocketSpy()->returnErrorOnSend = true;

        $dog->gauge('test', 21);
        $dog->gauge('test2', 21);

        $this->getSocketSpy()->returnErrorOnSend = false;

        $dog->gauge('test', 22);
        $this->assertSameWithTelemetry('test:22|g', $this->getSocketSpy()->argsFromSocketSendtoCalls[0][1], "", array("metrics" => 3, "bytes_dropped" => 1351, "packets_dropped" => 2));

        # force flush to get the telemetry about the last message sent
        $dog->flush("");
        $this->assertSameWithTelemetry('', $this->getSocketSpy()->argsFromSocketSendtoCalls[1][1], "", array("bytes_sent" => 677, "packets_sent" => 1, "metrics" => 0));
    }

    public function testDecimalNormalization()
    {
        $this->disableOriginDetectionLinux();

        $dog = new DogStatsd(array("disable_telemetry" => false, "decimal_precision" => 5));

        $dog->timing('test', 21.00000);
        $this->assertSameWithTelemetry('test:21|ms', $this->getSocketSpy()->argsFromSocketSendtoCalls[0][1]);

        $dog->gauge('test', 21.222225);
        $this->assertSameWithTelemetry('test:21.22223|g', $this->getSocketSpy()->argsFromSocketSendtoCalls[1][1], "", array("bytes_sent" => 675, "packets_sent" => 1));

        $dog->gauge('test', 2000.00);
        $this->assertSameWithTelemetry('test:2000|g', $this->getSocketSpy()->argsFromSocketSendtoCalls[2][1], "", array("bytes_sent" => 682, "packets_sent" => 1));
    }

    public function testFloatLocalization()
    {
        $this->disableOriginDetectionLinux();

        $defaultLocale = setlocale(LC_ALL, 0);
        setlocale(LC_ALL, 'nl_NL');
        $dog = new DogStatsd(array("disable_telemetry" => false));

        $dog->timing('test', 21.21000);
        $this->assertSameWithTelemetry('test:21.21|ms', $this->getSocketSpy()->argsFromSocketSendtoCalls[0][1]);
        setlocale(LC_ALL, $defaultLocale);
    }

    public function testSampleRateFloatLocalization()
    {
        $this->disableOriginDetectionLinux();

        $defaultLocale = setlocale(LC_ALL, 0);
        setlocale(LC_ALL, 'de_DE');
        $dog = new DogStatsd(array("disable_telemetry" => true));

        while (!array_key_exists(0, $this->getSocketSpy()->argsFromSocketSendtoCalls)) {
            $dog->timing('test', 21.21000, 0.3);
        }

        $this->assertSame(
            'test:21.21|ms|@0.3',
            $this->getSocketSpy()->argsFromSocketSendtoCalls[0][1]
        );
        setlocale(LC_ALL, $defaultLocale);
    }

    public function testMetricPrefix()
    {
        $this->disableOriginDetectionLinux();

        $dog = new DogStatsd(array("disable_telemetry" => false, "metric_prefix" => 'test_prefix'));

        $dog->timing('test', 21.00);
        $this->assertSameWithTelemetry('test_prefix.test:21|ms', $this->getSocketSpy()->argsFromSocketSendtoCalls[0][1]);

        $dog->gauge('test', 21.22);
        $this->assertSameWithTelemetry('test_prefix.test:21.22|g', $this->getSocketSpy()->argsFromSocketSendtoCalls[1][1], "", array("bytes_sent" => 687, "packets_sent" => 1));

        $dog->gauge('test', 2000.00);
        $this->assertSameWithTelemetry('test_prefix.test:2000|g', $this->getSocketSpy()->argsFromSocketSendtoCalls[2][1], "", array("bytes_sent" => 691, "packets_sent" => 1));
    }

    public function testExternalEnv()
    {
        putenv("DD_EXTERNAL_ENV=cn-SomeKindOfContainerName");
        $this->disableOriginDetectionLinux();

        $dog = new DogStatsd(array("disable_telemetry" => false));
        $dog->gauge('metric', 42);
        $spy = $this->getSocketSpy();
        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );
        $expectedUdpMessage = 'metric:42|g|e:cn-SomeKindOfContainerName';
        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1],
            ""
        );
    }

    public function testExternalEnvInvalidCharacters()
    {
        // Environment var contains a new line and a | character..
        putenv("DD_EXTERNAL_ENV=it-false,\ncn-nginx-webserver,|pu-75a2b6d5-3949-4afb-ad0d-92ff0674e759");
        $this->disableOriginDetectionLinux();

        $dog = new DogStatsd(array("disable_telemetry" => false));
        $dog->gauge('metric', 42, 1.0, array('my_tag' => 'other_value'));
        $spy = $this->getSocketSpy();
        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );
        $expectedUdpMessage = 'metric:42|g|#my_tag:other_value|e:it-false,cn-nginx-webserver,pu-75a2b6d5-3949-4afb-ad0d-92ff0674e759';
        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1],
            ""
        );
    }

    public function testExternalEnvWithTags()
    {
        $this->disableOriginDetectionLinux();

        putenv("DD_EXTERNAL_ENV=it-false,cn-nginx-webserver,pu-75a2b6d5-3949-4afb-ad0d-92ff0674e759");
        $dog = new DogStatsd(array("disable_telemetry" => false));
        $dog->gauge('metric', 42, 1.0, array('my_tag' => 'other_value'));
        $spy = $this->getSocketSpy();
        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );
        $expectedUdpMessage = 'metric:42|g|#my_tag:other_value|e:it-false,cn-nginx-webserver,pu-75a2b6d5-3949-4afb-ad0d-92ff0674e759';
        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1],
            ""
        );
    }

    public function testDDTags()
    {
        putenv("DD_VERSION=1.2.3");
        putenv("DD_ENV=prod");
        putenv("DD_SERVICE=myService");
        $this->disableOriginDetectionLinux();
        $dog = new DogStatsd(array(
            'global_tags' => array(
                'my_tag' => 'tag_value',
            ),
            'disable_telemetry' => false
        ));
        $dog->timing('metric', 42, 1.0);
        $spy = $this->getSocketSpy();
        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );
        $expectedUdpMessage = 'metric:42|ms|#my_tag:tag_value,env:prod,service:myService,version:1.2.3';
        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1],
            "",
            array("tags" => "my_tag:tag_value,env:prod,service:myService,version:1.2.3")
        );
    }

    public function testCardinality()
    {
        $dog = new DogStatsd(array("disable_telemetry" => false));
        $dog->gauge('metric', 42, 1.0, null, "high");
        $spy = $this->getSocketSpy();
        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );
        $expectedUdpMessage = 'metric:42|g|card:high';
        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1],
            ""
        );
    }

    public function testGlobalCardinality()
    {
        $dog = new DogStatsd(array(
            "disable_telemetry" => false,
            "cardinality" => "orchestrator"
        ));
        $dog->gauge('metric', 42);
        $spy = $this->getSocketSpy();
        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );
        $expectedUdpMessage = 'metric:42|g|card:orchestrator';
        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1],
            ""
        );
    }

    public function testEnvVarCardinality()
    {
        putenv("DD_CARDINALITY=HIGH");
        $dog = new DogStatsd(array("disable_telemetry" => false));
        $dog->gauge('metric', 42);
        $spy = $this->getSocketSpy();
        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'

        );
        $expectedUdpMessage = 'metric:42|g|card:high';
        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1],
            ""
        );
    }

    public function testInvalidCardinalityIgnored()
    {
        $error = "";
        set_error_handler(function ($errno, $errstr) use (&$error) {
            if ($errno === E_USER_WARNING) {
                $error = $errstr;
                return true;
            }
            return false;
        });

        // Invalid cardinality will raise a warning.
        putenv("DD_CARDINALITY=sausage");
        $dog = new DogStatsd(array("disable_telemetry" => false));
        $dog->gauge('metric', 42);
        $spy = $this->getSocketSpy();
        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'

        );
        $expectedUdpMessage = 'metric:42|g';
        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSameWithTelemetry(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1],
            ""
        );

        restore_error_handler();
    }

    /**
     * Get a timestamp created from a real date that is deterministic in nature
     *
     * @return int
     */
    private function getDeterministicTimestamp()
    {
        $dateTime = DateTime::createFromFormat(
            DateTime::ATOM,
            '2018-09-01T4:41:00Z'
        );

        return $dateTime->getTimestamp();
    }
}
