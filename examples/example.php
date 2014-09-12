<?php


require '../libraries/datadogstatsd.php';


DataDogStatsD::increment('web.page_views');
DataDogStatsD::histogram('web.render_time', 15);
DataDogStatsD::set('web.uniques', 3 /* a unique user id */);


//All the following metrics will be sent in a single UDP packet to the statsd server
BatchedDatadogStatsD::increment('web.page_views');
BatchedDatadogStatsD::histogram('web.render_time', 15);
BatchedDatadogStatsD::set('web.uniques', 3 /* a unique user id */);
BatchedDatadogStatsD::flush_buffer(); // Necessary

?>
