# PHP DataDog StatsD Client

This is an extremely simple PHP [datadogstatsd](http://www.datadoghq.com/) client.
Requires PHP >= 5.3.0.

See [CHANGELOG.md](CHANGELOG.md) for changes.

## Installation

### Composer

Add the following to your `composer.json`:

```
"datadog/php-datadogstatsd": "0.3.*"
```

Note: The first version shipped in composer is 0.0.3


### Or manually

Clone repository at [github.com/DataDog/php-datadogstatsd](https://github.com/DataDog/php-datadogstatsd)

Setup: `require './libraries/datadogstatsd.php';`

## Usage

### Increment

To increment things:

``` php
Datadogstatsd::increment('your.data.point');
Datadogstatsd::increment('your.data.point', .5);
Datadogstatsd::increment('your.data.point', 1, array('tagname' => 'value'));
```

### Decrement

To decrement things:

``` php
Datadogstatsd::decrement('your.data.point');
```

### Timing

To time things:

``` php
$start_time = microtime(true);
run_function();
Datadogstatsd::timing('your.data.point', microtime(true) - $start_time);

Datadogstatsd::timing('your.data.point', microtime(true) - $start_time, 1, array('tagname' => 'value'));
```

### Submitting events

To submit events, you'll need to first configure the library with your
Datadog credentials, since the event function submits directly to Datadog
instead of sending to a local dogstatsd instance.

``` php
$apiKey = 'myApiKey';
$appKey = 'myAppKey';

Datadogstatsd::configure($apiKey, $appKey);
Datadogstatsd::event('A thing broke!', array(
	'alert_type'      => 'error',
	'aggregation_key' => 'test_aggr'
));
Datadogstatsd::event('Now it is fixed.', array(
	'alert_type'      => 'success',
	'aggregation_key' => 'test_aggr'
));
```

You can find your api and app keys in the [API tab](https://app.datadoghq.com/account/settings#api).

For more documentation on the optional values of events, see [http://docs.datadoghq.com/api/#events/](http://docs.datadoghq.com/api/#events/).

Note that while sending metrics with this library is fast since it's sending
locally over UDP, sending events will be slow because it's sending data
directly to Datadog over HTTP. We'd like to improve this in the near future.
