# PHP StatsD Client

This is an extremely simple PHP StatsD client for [caremerge](http://caremerge.com/) built on top of [php-statsd](https://github.com/seejohnrun/php-statsd) and [php-datadogstatsd](https://github.com/DataDog/php-datadogstatsd)

Requires PHP >= 5.3.0.

## Installation

### Composer
Use the composer.json file 
```
composer install
```

Note: The first version shipped in composer is 0.0.3

### Or manually

Clone repository at [github.com/FarrukhNaeem/php-statsd](https://github.com/FarrukhNaeem/php-statsd)

Setup: `require './src/CareStats.php';`

## Usage

### instantiation

To instantiate a DogStatsd object using `composer`:

```php
require __DIR__ . '/vendor/autoload.php';

use StatsDC\CareStats;

$statsd = new CareStats();
```

CareStats constructor, takes a configuration array. The configuration can take any of the following values (all optional):

- `host`: the host of your Statsd server, default to `localhost`
- `port`: the port of your Statsd server. default to `8125`

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
