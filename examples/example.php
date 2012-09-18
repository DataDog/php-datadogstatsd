<?php


require '../libraries/datadogstatsd.php';


DataDogStatsD::increment('web.page_views');
DataDogStatsD::histogram('web.render_time', 15);
DataDogStatsD::set('web.uniques', 3 /* a unique user id */);

?>
