<?php

//Use autoload if installed with composer
//require '../vendor/autoload.php'; Uncomment this if using composer

//OR use direct path if not used composer
require './src/CareStats.php';

use StatsDC\CareStats;

$statsd = new CareStats();
$statsd->increment('web.page_views');
$statsd->histogram('web.render_time', 15);
$statsd->distribution('web.render_time', 15);

