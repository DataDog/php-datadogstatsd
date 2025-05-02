<?php

namespace DataDog\UnitTests\DogStatsd;

use DataDog\DogStatsd;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class ExternalEnvOriginDetectionTest extends TestCase
{
    private $oldVar;

    private function callPrivate($object, $method, $params) {
      $reflectionMethod = new \ReflectionMethod(get_class($object), $method);
      $reflectionMethod->setAccessible(true);
      return $reflectionMethod->invoke($object, $params);
    }

    protected function set_up() {
        parent::set_up();

        $this->oldVar = getenv("DD_EXTERNAL_ENV");
        putenv("DD_EXTERNAL_ENV=cn-SomeKindOfContainerName");
    }

    protected function tear_down() {
        if ($this->oldVar) {
            putenv("DD_EXTERNAL_ENV=" . $this->oldVar);
        } else {
            putenv("DD_EXTERNAL_ENV");
        }

        parent::tear_down();
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
