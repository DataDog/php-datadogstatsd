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