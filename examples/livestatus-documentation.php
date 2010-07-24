<?php

// this script execute a simple livestatus query on table columns
// columns describe all of the others tables of livestatus

require("../api/live.php");

$query = array("GET colmmumns");

// create live object
$live = new live("localhost", "6557");
// connect
if ( ! $live->connect() ){
    	die("Unable to connect");
}else{
	// query nagios/shinken data
	if (!$live->query($query)){
		die("Query Error");
	}else{
		if($live->responsecode != "200"){
			die("QUERY : ".$live->responsemessage." (".$live->responsecode.")");
		}else{
			// read response after query
			if (!$live->readresponse()){
			    die("Response Error");
			}else{
				// disconnect from livestatus socket
				$live->disconnect();
				// use data
				$response = json_decode($live->queryresponse);
				echo "<table>";
				foreach ($response as $line){
					// line
					echo "<tr>";
					// header
					foreach($line as $col){
						echo "<td style=\"border:1px solid black\">".$col."</td>";
					}
					echo "</tr>";
				}
				echo "</table>";
			}
		}
	}
}
?>
