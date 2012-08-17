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

### Submitting events

To submit events, you'll need to first configure the library with your
Datadog credentials, since the event function submits directly to Datadog
instead of sending to a local dogstatsd instance.

``` php
$apiKey = 'myApiKey';
$appKey = 'myAppKey';

DataDogStatsD::configure($apiKey, $appKey);
DataDogStatsD::event('A thing broke!', array(
	'alert_type'      => 'error',
	'aggregation_key' => 'test_aggr'
));
DataDogStatsD::event('Now it is fixed.', array(
	'alert_type'      => 'success',
	'aggregation_key' => 'test_aggr'
));
```

You can find your api and app keys in the [API tab](https://app.datadoghq.com/account/settings#api).

For more documentation on the optional values of events, see [http://api.datadoghq.com/events/](http://api.datadoghq.com/events/).

Note that while sending metrics with this library is fast since it's sending
locally over UDP, sending events will be slow because it's sending data
directly to Datadog over HTTP. We'd like to improve this in the near future.

## Author

Alex Corley - anthroprose@gmail.com