# PHP StatsD Client

This is an extremely simple PHP [statsd](https://github.com/etsy/statsd.git) client and [CI spark](http://getsparks.org).

## Installation

1.  _With Sparks_ - `$ php tools/spark install statsd`
2.  _Without Sparks_ - Clone repository at [github.com/seejohnrun/php-statsd](https://github.com/seejohnrun/php-statsd)

## Setup

1.  _With Sparks_ - `$this->load->spark('statsd');`
2.  _Without Sparks_ - `require './libraries/statsd.php';`

## Usage

### Counting

To count things:

``` php
$stats = new StatsD();
$stats->counting('numpoints', 123);
```

### Timing

Record timings:

``` php
$stats = new StatsD();
$stats->timing('timething', 123);
```

### Time Block

And a convenience mechanism for timing:

``` php
$stats = new StatsD();
$stats->time_this('timething', function() {
    sleep(1);
});
```

## Configuration

### Host and Port

``` php
$stats = new StatsD('localhost', 7000); // default localhost:8125
```

### Sample Rate

Any of the methods descriped in the usage section can take an optional third argument `$rate`, which is the sample rate:

``` php
$stats = new StatsD();
$stats->counting('numpoints', 123, 0.1);
```

## As a CodeIgniter library

``` php
$this->load->library('statsd');
$this->statsd->counting('numpoints', 123);
```

## Author

John Crepezzi - john.crepezzi@gmail.com

## License

(The MIT License)

Copyright © 2012 John Crepezzi

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the ‘Software’), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED ‘AS IS’, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE. MIT License.  See attached LICENSE
