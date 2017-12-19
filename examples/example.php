<?php


require '../libraries/datadogstatsd.php';


Datadogstatsd::increment('web.page_views');
Datadogstatsd::histogram('web.render_time', 15);
Datadogstatsd::set('web.uniques', 3 /* a unique user id */);
Datadogstatsd::service_check('my.service.check', Datadogstatsd::CRITICAL);


//All the following metrics will be sent in a single UDP packet to the statsd server
BatchedDatadogstatsd::increment('web.page_views');
BatchedDatadogstatsd::histogram('web.render_time', 15);
BatchedDatadogstatsd::set('web.uniques', 3 /* a unique user id */);
BatchedDatadogstatsd::flush_buffer(); // Necessary
