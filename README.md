# PHP DataDog StatsD Client

[![Build Status](https://travis-ci.org/DataDog/php-datadogstatsd.svg?branch=master)](https://travis-ci.org/DataDog/php-datadogstatsd)
[![Author](https://img.shields.io/badge/author-@datadog-blue.svg?style=flat-square)](https://github.com/datadog)
[![Packagist Version](https://img.shields.io/packagist/v/datadog/php-datadogstatsd.svg?style=flat-square)](https://packagist.org/packages/datadog/php-datadogstatsd)
[![Total Downloads](https://img.shields.io/packagist/dt/datadog/php-datadogstatsd.svg?style=flat-square)](https://packagist.org/packages/datadog/php-datadogstatsd)

This is an extremely simple PHP [DogStatsD](https://docs.datadoghq.com/developers/dogstatsd/?tab=php) client.

**Requires PHP >= 5.6.0.**

See [CHANGELOG.md](CHANGELOG.md) for changes.

*For a Laravel-specific implementation that wraps this library, check out [laravel-datadog-helper](https://github.com/chaseconey/laravel-datadog-helper).*

## Installation

Add the following to your `composer.json`:

```
"datadog/php-datadogstatsd": "1.5.*"
```
The first version shipped in composer is *0.0.3*

Or manually clone this repository and set it up with `require './src/DogStatsd.php'.`

Once installed, turn on the socket extension to PHP which must be enabled at compile time by giving the `--enable-sockets` option to **configure**.

### Configuration

To instantiate a DogStatsd object using `composer`:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use DataDog\DogStatsd;

$statsd = new DogStatsd(
    array('host' => '127.0.0.1',
          'port' => 8125,
     )
  );
```

DogStatsd constructor, takes a configuration array. See the full list of available [DogStatsD Client instantiation parameters](https://docs.datadoghq.com/developers/dogstatsd/?tab=php#client-instantiation-parameters).

### Origin detection over UDP in Kubernetes

Origin detection is a method to detect which pod DogStatsD packets are coming from in order to add the pod's tags to the tag list.

To enable origin detection over UDP, add the following lines to your application manifest
```yaml
env:
  - name: DD_ENTITY_ID
    valueFrom:
      fieldRef:
        fieldPath: metadata.uid
```

The DogStatsD client attaches an internal tag, `entity_id`. The value of this tag is the content of the `DD_ENTITY_ID` environment variable, which is the pod’s UID.
The agent uses this tag to infer packets' origin, and tag their metrics accordingly.

## Usage

In order to use DogStatsD metrics, events, and Service Checks the Agent must be [running and available](https://docs.datadoghq.com/developers/dogstatsd/?tab=php).

### Metrics

After the client is created, you can start sending custom metrics to Datadog. See the dedicated [Metric Submission: DogStatsD documentation](https://docs.datadoghq.com/developers/metrics/dogstatsd_metrics_submission/?tab=php) to see how to submit all supported metric types to Datadog with working code examples:

* [Submit a COUNT metric](https://docs.datadoghq.com/developers/metrics/dogstatsd_metrics_submission/?tab=php#count).
* [Submit a GAUGE metric](https://docs.datadoghq.com/developers/metrics/dogstatsd_metrics_submission/?tab=php#gauge).
* [Submit a SET metric](https://docs.datadoghq.com/developers/metrics/dogstatsd_metrics_submission/?tab=php#set)
* [Submit a HISTOGRAM metric](https://docs.datadoghq.com/developers/metrics/dogstatsd_metrics_submission/?tab=php#histogram)
* [Submit a TIMER metric](https://docs.datadoghq.com/developers/metrics/dogstatsd_metrics_submission/?tab=php#timer)
* [Submit a DISTRIBUTION metric](https://docs.datadoghq.com/developers/metrics/dogstatsd_metrics_submission/?tab=php#distribution)

Some options are suppported when submitting metrics, like [applying a Sample Rate to your metrics](https://docs.datadoghq.com/developers/metrics/dogstatsd_metrics_submission/?tab=php#metric-submission-options) or [tagging your metrics with your custom tags](https://docs.datadoghq.com/developers/metrics/dogstatsd_metrics_submission/?tab=php#metric-tagging).

### Events

After the client is created, you can start sending events to your Datadog Event Stream. See the dedicated [Event Submission: DogStatsD documentation](https://docs.datadoghq.com/developers/events/dogstatsd/?tab=php) to see how to submit an event to your Datadog Event Stream.

### Service Checks

After the client is created, you can start sending Service Checks to Datadog. See the dedicated [Service Check Submission: DogStatsD documentation](https://docs.datadoghq.com/developers/service_checks/dogstatsd_service_checks_submission/?tab=php) to see how to submit a Service Check to Datadog.

## Roadmap

* Add a configurable timeout for event submission via TCP
* Write unit tests
* Document service check functionality

## Tests

```bash
composer test
```
