<?php

namespace DataDog\TestHelpers;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

$curlSpy = new CurlSpy();

class CurlSpyTestCase extends TestCase
{
    /**
     * Set up a spy object to capture calls to built in curl functions
     */
    protected function set_up()
    {
        global $curlSpy;

        $curlSpy = new CurlSpy();
        parent::set_up();
    }

    /**
     * @return \DataDog\TestHelpers\CurlSpy
     */
    protected function getCurlSpy()
    {
        global $curlSpy;

        return $curlSpy;
    }
}
