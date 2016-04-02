<?php


require '../vendor/autoload.php';

use DataDog\DogStatsd;
use DataDog\BatchedDogStatsd;

DogStatsd::increment('web.page_views');
DogStatsd::histogram('web.render_time', 15);
DogStatsd::set('web.uniques', 3 /* a unique user id */);
DogStatsd::serviceCheck('my.service.check', DogStatsd::CRITICAL);


//All the following metrics will be sent in a single UDP packet to the statsd server
BatchedDogStatsd::increment('web.page_views');
BatchedDogStatsd::histogram('web.render_time', 15);
BatchedDogStatsd::set('web.uniques', 3 /* a unique user id */);
BatchedDogStatsd::flushBuffer(); // Necessary
