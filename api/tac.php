<?php
/**
 * base class to use live status implementation in shinken and nagios
 *
 * @package     livestatusapi
 * @author      David GUENAULT (dguenault@monitoring-fr.org)
 * @copyright   (c) 2010 Monitoring-fr Team / Shinken Team (http://monitoring-fr.org / http://www.shinken-monitoring.org)
 * @license     Afero GPL
 * @version     0.1
 */

class tac extends live{
    
    public $sites = null;

    public function __construct(){
    	
    }
    
    public function __destruct(){
    	
    }
    
    /**
     * This method get the overall status of all services and hosts. if $sites is an array of sites names it will only retrieve the tac for the specified sites.
     * @param array sites an array off all of the monitoring hosts (see conf/sites.inc.php)
     * @return string   the json encoded tac values
     */
    public function getOverallStates($sites){
        // check sites validity
        if($sites == null || count($sites) < 1){
            $this->responsecode = "603";
            $this->responsemessage = "[SITES] invalid sites definition";
            return false;
        }
    
        // get overall services states (HARD STATES ONLY)
        $queryservices = array(
			"GET services",
			"Stats: state = 0",
			"Stats: state = 1",
			"Stats: state = 2",
			"Stats: state = 3",  
        );
        
        // get overall hosts states (HARD STATES ONLY)
        $queryhosts = array(
			"GET hosts",
			"Stats: state = 0",
			"Stats: state = 1",
			"Stats: state = 2"
        );
        
        $failedservices = array();
        $failedhosts = array();
        
        $hosts=array(
            "UP"=>0,
            "DOWN"=>0,
            "UNREACHABLE"=>0,
            "ALL PROBLEMS"=>0,
            "ALL TYPES"=>0
        );        
        
        $services=array(
            "OK"=>0,
            "WARNING"=>0,
            "CRITICAL"=>0,
            "UNKNOWN"=>0,
            "ALL PROBLEMS"=>0,
            "ALL TYPES"=>0
        );        
        
        foreach($sites as $site){
        	if($this->connectSite($site)){
        		$result=$this->execQuery($queryservices);
	            if($result == false){
	                // one ore more sites failed to execute the query
	                // we keep a trace
	                $failedservices[]=array("site"=>$site,"code"=>$this->responsecode,"message"=>$this->responsemessage);
	                return false;
	            }else{
	                $states=json_decode($this->queryresponse);
	                // OK
	                $services["OK"] += $states[0][0];
	                // WARNING
	                $services["WARNING"] += $states[0][1];
	                // CRITICAL
	                $services["CRITICAL"] += $states[0][2];
	                // UNKNOWN
	                $services["UNKNOWN"] += $states[0][3];
	                // ALL TYPES
	                $services["ALL TYPES"] += $services["OK"]+$services["WARNING"]+$services["CRITICAL"]+$services["UNKNOWN"];
	                // ALL PROBLEMS
	                $services["ALL PROBLEMS"] += $services["WARNING"]+$services["CRITICAL"]+$services["UNKNOWN"];
	            }
	            $result=$this->execQuery($queryhosts);
	            if(!$result){
	                // one ore more sites failed to execute the query
	                // we keep a trace
	                $failedhosts[]=array("site"=>$site,"code"=>$this->responsecode,"message"=>$this->responsemessage);
	            }else{
	                $states=json_decode($this->queryresponse);
	                // UP
	                $hosts["UP"] += $states[0][0];
	                // DOWN
	                $hosts["DOWN"] += $states[0][1];
	                // UNREACHABLE
                	$hosts["UNREACHABLE"] += $states[0][2];
	                // ALL TYPES
	                $hosts["ALL TYPES"] += $hosts["UP"]+$hosts["DOWN"]+$hosts["UNREACHABLE"];
	                // ALL PROBLEMS
	                $hosts["ALL PROBLEMS"] += $hosts["DOWN"]+$hosts["UNREACHABLE"];
	            }
        	}else{
	            // one ore more sites failed to connect 
	            // we keep a trace
	            $failedhosts[]=array("site"=>$site,"code"=>$this->responsecode,"message"=>$this->responsemessage);
        	}
        }
		return json_encode(array(
			"hosts"=>$hosts,
		    "services"=>$services
		)); 	            
    }

	private function connectSite($site){
		switch($site["type"]){
			case "TCP":
				$this->host = $site["host"];
				$this->port = $site["port"];
				break;
			case "UNIX":
				$this->socketpath = $site["socket"];
				break;
			default:
				break;
		}
		return $this->connect();
	}

}
