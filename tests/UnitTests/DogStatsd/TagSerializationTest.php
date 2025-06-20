<?php

namespace DataDog\UnitTests\DogStatsd;

use DataDog\DogStatsd;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class TagSerializationTest extends TestCase
{
    private $oldVar;

    private function callPrivate($object, $method, $params) {
      $reflectionMethod = new \ReflectionMethod(get_class($object), $method);
      $reflectionMethod->setAccessible(true);
      return $reflectionMethod->invoke($object, $params);
    }

    // Ensure DD_EXTERNAL_ENV is not set when we run these tests.
    protected function set_up() {
        parent::set_up();

        $this->oldVar = getenv("DD_EXTERNAL_ENV");
        putenv("DD_EXTERNAL_ENV");
    }

    protected function tear_down() {
        if ($this->oldVar) {
            putenv("DD_EXTERNAL_ENV=" . $this->old);
        }

        parent::tear_down();
    }

  /**
   * @dataProvider tagProvider
   *
   * @param $tags
   * @param $expected
   */
    public function testTagSerialization($tags, $expected) {
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
                ''
            ],
            'one string' => [
                ['foo' => 'bar'],
                '|#foo:bar'
            ],
            'two strings' => [
                ['foo' => 'bar', 'baz' => 'blam'],
                '|#foo:bar,baz:blam'
            ],
            'one string one int' => [
                ['foo' => 'bar', 'baz' => 42],
                '|#foo:bar,baz:42'
            ],
            // https://github.com/DataDog/php-datadogstatsd/issues/118
            'one string one true boolean' => [
                ['foo' => 'bar', 'baz' => true],
                '|#foo:bar,baz:true'
            ],
            'one string one false boolean' => [
                ['foo' => 'bar', 'baz' => false],
                '|#foo:bar,baz:false'
            ],
            'grab bag' => [
                ['foo' => 'bar', 'baz' => false, 'nullValue' => null, 'blam' => 1, 'blah' => 0],
                '|#foo:bar,baz:false,nullValue,blam:1,blah:0'
            ],
            'string element' => [
                ['foo:bar'],
                '|#foo:bar'
            ]
        ];
    }
}
