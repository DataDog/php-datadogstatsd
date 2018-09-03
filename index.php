<?php

require './src/CareStats.php';

use StatsDC\CareStats;

$statsd = new CareStats();
$statsd->increment('FinalCodeLibrary.local.PHP.farrukh_testing_PHPStatsD');
$statsd->decrement('decrementCodeLibrary.local.PHP.farrukh_testing_PHPStatsD');
