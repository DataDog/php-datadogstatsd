<?php

namespace DataDog\UnitTests\DogStatsd;

use PHPUnit\Framework\TestCase;
use DataDog\DogStatsd;

class TagSerializationTest extends TestCase
{
    private function callPrivate($object, $method, $params) {
      $reflectionMethod = new \ReflectionMethod(get_class($object), $method);
      $reflectionMethod->setAccessible(true);
      return $reflectionMethod->invoke($object, $params);
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
            ]
        ];
    }
}
