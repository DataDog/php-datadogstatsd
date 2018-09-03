<?php

require './src/DogStatsd.php';

use StatsDC\CareStats;

$statsd = new CareStats();
$statsd->increment('web.page_views');
$statsd->histogram('web.render_time', 15);
$statsd->distribution('web.render_time', 15);
