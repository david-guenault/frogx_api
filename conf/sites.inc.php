<?php
/**
 * This config file is used to define the nagios/shinken collectors
 * 
 * @global array $GLOBALS['config']
 * @name $config
 */
$config["sites"]=array(
    "frogx"=>array(
        "type"=>"TCP",
        "address"=>"localhost",
        "port"=>"5667"
    )
);

?>
