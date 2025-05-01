<?php


namespace DataDog\UnitTests\DogStatsd;

use PHPUnit\Framework\TestCase;
use DataDog\DogStatsd;

class ExternalEnvOriginDetectionTest extends TestCase
{
    private $oldVar;

    private function callPrivate($object, $method, $params) {
      $reflectionMethod = new \ReflectionMethod(get_class($object), $method);
      $reflectionMethod->setAccessible(true);
      return $reflectionMethod->invoke($object, $params);
    }

    protected function setUp() {
        $this->oldVar = getenv("DD_EXTERNAL_ENV");
        putenv("DD_EXTERNAL_ENV=cn-SomeKindOfContainerName");
    }

    protected function tearDown() {
        if ($this->oldVar) {
            putenv("DD_EXTERNAL_ENV=" . $this->oldVar);
        } else {
            putenv("DD_EXTERNAL_ENV");
        }
    }

    /**
     * @dataProvider tagProvider
     *
     * @param $tags
     * @param $expected
     */
    public function testSomething($tags, $expected) {
        $dog = new DogStatsd();
        $this->assertsame(
            $expected,
            $this->callPrivate($dog, 'serializeTags', $tags)
        );
    }

    public function tagProvider()
    {
        return [
            'without tags' => [
                [],
                '|#e:cn-SomeKindOfContainerName'
            ],
            'with tags' => [
                ['foo' => 'bar'],
                '|#e:cn-SomeKindOfContainerName,foo:bar'
            ]
        ];
    }
}
