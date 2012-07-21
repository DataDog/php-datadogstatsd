# PHP DataDog StatsD Client

This is an extremely simple PHP [datadogstatsd](http://www.datadoghq.com/) client

## Installation

Clone repository at [github.com/anthroprose/php-datadogstatsd](https://github.com/anthroprose/php-datadogstatsd)

## Setup

`require './libraries/datadogstatsd.php';`
 
## Usage

### Increment

To increment things:

``` php
DataDogStatsD::increment('your.data.point');
DataDogStatsD::increment('your.data.point', .5);
DataDogStatsD::increment('your.data.point', 1, array('tagname' => 'value'));
```

### Decrement

To decrement things:

``` php
DataDogStatsD::decrement('your.data.point');
```

### Timing

To time things:

``` php
$start_time = microtime(true);
run_function();
DataDogStatsD::timing('your.data.point', microtime(true) - $start_time);

DataDogStatsD::timing('your.data.point', microtime(true) - $start_time, 1, array('tagname' => 'value'));
```

## Author

Alex Corley - anthroprose@gmail.com

## License

(The MIT License)

Copyright © 2012 Alex Corley

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the ‘Software’), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED ‘AS IS’, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE. MIT License.  See attached LICENSE
