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

The 'tags' argument can be a array or a string. Value can be set to `null`.

```php
# Both call will send the "app:php1" and "beta" tags.
Datadogstatsd::increment('your.data.point', 1, array('app' => 'php1', 'beta' => null));
Datadogstatsd::increment('your.data.point', 1, "app:php1,beta");
```

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

For documentation on the values of events, see 
[http://docs.datadoghq.com/api/#events/](http://docs.datadoghq.com/api/#events/).

**Submitting events via TCP vs UDP**

* **TCP** - High-confidence event submission. Will log errors on event submission error.
* **UDP** - "Fire and forget" event submission. Will **not** log errors on event submission error. No acknowledgement 
of submitted event from Datadog.

_[Differences between TCP/UDP](http://stackoverflow.com/a/5970545)_

##### UDP Submission to local dogstatsd

Since the UDP method uses the a local dogstatsd instance we don't need to setup any additional application/api access.

```php
Datadogstatsd::event('Fire and forget!', array(
    'text'       => 'Sending errors via UDP is faster but less reliable!',
	'alert_type' => 'success'
));
```

* Default method
* No configuration
* Faster
* Less reliable
* No logging on communication errors with Datadog (fire and forget)


##### TCP Submission to Datadog API

To submit events via TCP, you'll need to first configure the library with your
Datadog credentials, since the event function submits directly to Datadog
instead of sending to a local dogstatsd instance.

You can find your api and app keys in the [API tab](https://app.datadoghq.com/account/settings#api).

```php
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

* Slower
* More reliable
* Logging on communication errors with Datadog (uses cURL for API request)
* Logs via error_log and try/catch block to not throw warnings/errors on communication issues with API


## Roadmap

* Add a configurable timeout for event submission via TCP
* Write unit tests
* Document service check functionality
