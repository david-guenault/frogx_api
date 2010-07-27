<?php
/**
 * get overall hosts and services states (as known as tactical overview)
 * this introduce the concept of multisite. Each site is an entry in the array config["sites"]
 */
require_once("/var/www/frogx/conf/sites.inc.php");
require_once("/var/www/frogx/api/live.php");
require_once("/var/www/frogx/api/tac.php");

$tac = new tac(array("host"=>"localhost", "port"=>"6557"));
print_r(json_decode($tac->getOverallStates($config["sites"])));

?>
