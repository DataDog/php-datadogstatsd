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
$numPoints = getNumberOfPoints();
Stats::counting('numpoints', $numPoints);
```

### Timing

Record timings:

``` php
$timing = getTiming();
Stats::timing('timething', $timing);
```

### Time Block

And a convenience mechanism for timing:

``` php
Stats::time_this('timething', function() {
    sleep(1);
});
```

## Configuration

### Host and Port

``` php
Stats::setHost('localhost'); // default localhost
Stats::setPort(7000); // default 8125
```

### Sample Rate

Any of the methods descriped in the usage section can take an optional third argument `$rate`, which is the sample rate:

``` php
Stats::counting('numpoints', 123, 0.1);
```

## Author

John Crepezzi - john.crepezzi@gmail.com

## License

MIT License.  See attached LICENSE
