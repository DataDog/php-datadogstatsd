<?php

require './src/DogStatsd.php';

use DataDog\DogStatsd;

$statsd = new DogStatsd();
$statsd->increment('web.page_views');
$statsd->histogram('web.render_time', 15);
$statsd->distribution('web.render_time', 15);
