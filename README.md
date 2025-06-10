# PHP DataDog StatsD Client

[![CircleCI](https://circleci.com/gh/DataDog/php-datadogstatsd/tree/master.svg?style=svg)](https://circleci.com/gh/DataDog/php-datadogstatsd/tree/master)
[![Author](https://img.shields.io/badge/author-@datadog-blue.svg?style=flat-square)](https://github.com/datadog)
[![Packagist Version](https://img.shields.io/packagist/v/datadog/php-datadogstatsd.svg?style=flat-square)](https://packagist.org/packages/datadog/php-datadogstatsd)
[![Total Downloads](https://img.shields.io/packagist/dt/datadog/php-datadogstatsd.svg?style=flat-square)](https://packagist.org/packages/datadog/php-datadogstatsd)

This is an extremely simple PHP [DogStatsD](https://docs.datadoghq.com/developers/dogstatsd/?code-lang=php) client.

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

## Configuration

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

DogStatsd constructor, takes a configuration array. See the full list of available [DogStatsD Client instantiation parameters](https://docs.datadoghq.com/developers/dogstatsd/?code-lang=php#client-instantiation-parameters).

## Usage

In order to use DogStatsD metrics, events, and Service Checks the Agent must be [running and available](https://docs.datadoghq.com/developers/dogstatsd/?code-lang=php).

### Metrics

After the client is created, you can start sending custom metrics to Datadog. See the dedicated [Metric Submission: DogStatsD documentation](https://docs.datadoghq.com/metrics/dogstatsd_metrics_submission/?code-lang=php) to see how to submit all supported metric types to Datadog with working code examples:

* [Submit a COUNT metric](https://docs.datadoghq.com/metrics/dogstatsd_metrics_submission/?code-lang=php#count).
* [Submit a GAUGE metric](https://docs.datadoghq.com/metrics/dogstatsd_metrics_submission/?code-lang=php#gauge).
* [Submit a SET metric](https://docs.datadoghq.com/metrics/dogstatsd_metrics_submission/?code-lang=php#set)
* [Submit a HISTOGRAM metric](https://docs.datadoghq.com/metrics/dogstatsd_metrics_submission/?code-lang=php#histogram)
* [Submit a TIMER metric](https://docs.datadoghq.com/metrics/dogstatsd_metrics_submission/?code-lang=php#timer)
* [Submit a DISTRIBUTION metric](https://docs.datadoghq.com/metrics/dogstatsd_metrics_submission/?code-lang=php#distribution)

Some options are suppported when submitting metrics, like [applying a Sample Rate to your metrics](https://docs.datadoghq.com/metrics/dogstatsd_metrics_submission/?code-lang=php#metric-submission-options) or [tagging your metrics with your custom tags](https://docs.datadoghq.com/metrics/dogstatsd_metrics_submission/?code-lang=php#metric-tagging).

### Events

After the client is created, you can start sending events to your Datadog Event Stream. See the dedicated [Event Submission: DogStatsD documentation](https://docs.datadoghq.com/developers/events/dogstatsd/?code-lang=php) to see how to submit an event to your Datadog Event Stream.

### Service Checks

After the client is created, you can start sending Service Checks to Datadog. See the dedicated [Service Check Submission: DogStatsD documentation](https://docs.datadoghq.com/developers/service_checks/dogstatsd_service_checks_submission/?code-lang=php) to see how to submit a Service Check to Datadog.

### Origin detection in Kubernetes

Origin detection is a method to detect which pod DogStatsD packets are coming from in order to add the pod's tags to the tag list.

#### Tag cardinality

The tags that can be added to metrics can be found [here][tags]. The cardinality can be specified globally by setting the `DD_CARDINALITY`
environment or by passing a `'cardinality'` field to the constructor. Cardinality can also be specified per metric by passing the value
in the `$cardinality` parameter. Valid values for this parameter are `"none"`, `"low"`, `"orchestrator"` or `"high"`. If an invalid 
value is passed, an `E_USER_WARNING` error is raised. If this error is handled with `set_error_handler` the metric is sent without a 
cardinality field.

Origin detection is achieved in a number of ways:

#### CGroups

On Linux the container ID can be extracted from `procfs` entries related to `cgroups`. The client reads from `/proc/self/cgroup` or `/proc/self/mountinfo` to attempt to parse the container id. 

In cgroup v2, the container ID can be inferred by resolving the cgroup path from `/proc/self/cgroup`, combining it with the cgroup mount point from `/proc/self/mountinfo]`. The resulting directory's inode is sent to the agent. Provided the agent is on the same node as the client, this can be used to identify the pod's UID.

#### Over UDP 

To enable origin detection over UDP, add the following lines to your application manifest
```yaml
env:
  - name: DD_ENTITY_ID
    valueFrom:
      fieldRef:
        fieldPath: metadata.uid
```

The DogStatsD client attaches an internal tag, `entity_id`. The value of this tag is the content of the `DD_ENTITY_ID` environment variable, which is the pod's UID.
The agent uses this tag to infer packets' origin, and tag their metrics accordingly.

#### DD_EXTERNAL_ENV

If the pod is annotated with the label:

```
admission.datadoghq.com/enabled: "true"
```

The [admissions controller] injects an environment variable `DD_EXTERNAL_ENV`. 
The value of this is sent in a field with the metric which can be used by the 
agent to determine the metrics origin.

## Tests

```bash
composer test
```

### Lint

```bash
composer lint
```

### Fix lint

```bash
composer fix-lint
```

[admissions controller]: https://docs.datadoghq.com/containers/cluster_agent/admission_controller/?tab=datadogoperator
[tags]: https://docs.datadoghq.com/containers/kubernetes/tag/?tab=datadogoperator
