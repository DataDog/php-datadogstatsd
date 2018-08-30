# PHP DataDog StatsD Client

[![Build Status](https://travis-ci.org/DataDog/php-datadogstatsd.svg?branch=master)](https://travis-ci.org/DataDog/php-datadogstatsd)

This is an extremely simple PHP [datadogstatsd](http://www.datadoghq.com/) client.
Requires PHP >= 5.3.0.

See [CHANGELOG.md](CHANGELOG.md) for changes.

*For a Laravel-specific implementation that wraps this library, check out [laravel-datadog-helper](https://github.com/chaseconey/laravel-datadog-helper).*

## Installation

### Composer

Add the following to your `composer.json`:

```
"datadog/php-datadogstatsd": "1.0.*"
```

Note: The first version shipped in composer is 0.0.3

### Or manually

Clone repository at [github.com/DataDog/php-datadogstatsd](https://github.com/DataDog/php-datadogstatsd)

Setup: `require './src/DogStatsd.php';`

## Usage

### instantiation

To instantiate a DogStatsd object using `composer`:

```php
require __DIR__ . '/vendor/autoload.php';

use DataDog\DogStatsd;

$statsd = new DogStatsd();
```

DogStatsd constructor, takes a configuration array. The configuration can take any of the following values (all optional):

- `host`: the host of your DogStatsd server, default to `localhost`
- `port`: the port of your DogStatsd server. default to `8125`

### Tags

The 'tags' argument can be a array or a string. Value can be set to `null`.

### Increment

To increment things:

``` php
$statsd->increment('your.data.point');
$statsd->increment('your.data.point', .5);
```

### Decrement

To decrement things:

``` php
$statsd->decrement('your.data.point');
```

### Timing

To time things:

``` php
$start_time = microtime(true);
run_function();
$statsd->microtiming('your.data.point', microtime(true) - $start_time);

$statsd->microtiming('your.data.point', microtime(true) - $start_time, 1, array('tagname' => 'value'));
```

## Roadmap

- Add a configurable timeout for event submission via TCP
- Write unit tests
- Document service check functionality
