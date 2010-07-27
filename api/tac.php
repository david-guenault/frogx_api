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
    
    /**
     * Class Constructor
     * @param array params array of parameters (if socket is in the keys then the connection is UNIX SOCKET else it is TCP SOCKET)
     * @param int buffer used to read query response
     */
    public function __construct($params,$buffer=1024) {
            // fucking php limitation on declaring multiple constructors !
            if(isset($params["socket"])){
                $this->socketpath = $params["socket"];
                $this->buffer = $buffer;
            }else{
                $this->host   = $params["host"];
                $this->port   = $params["port"];
                $this->buffer = $buffer;
            }
            $this->getLivestatusVersion();
    }
    /**
     * Class destructor
     */
    public function __destruc(){
        
    }
    /**
     * This method get the overall status of all services and hosts. if $sites is an array of sites names it will only retrieve the tac for the specified sites.
     * @param array $sites
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
            "Columns: state",
            "Filter: state_type = 1"
        );
        
        // get overall hosts states (HARD STATES ONLY)
        $queryhosts = array(
            "GET hosts",
            "Columns: state",
            "Filter: state_type = 1"
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
            if(!$this->execQuery($queryservices)){
                // one ore more sites failed to execute the query
                // we keep a trace
                $failedservices[$site]=array("code"=>$this->responsecode,"message"=>$this->responsemessage);
            }else{
                $result=json_decode($this->queryresponse);
                foreach($result as $state){
                    switch($state[0]){
                        case "0":
                            $services["OK"]++;
                            $services["ALL TYPES"]++;
                            break;
                        case "1":
                            $services["WARNING"]++;
                            $services["ALL PROBLEMS"]++;
                            $services["ALL TYPES"]++;
                            break;
                        case "2":
                            $services["CRITICAL"]++;
                            $services["ALL PROBLEMS"]++;
                            $services["ALL TYPES"]++;
                            break;
                        case "3":
                            $services["UNKNOWN"]++;
                            $services["ALL PROBLEMS"]++;
                            $services["ALL TYPES"]++;
                            break;
                    }
                }
            }
            if(!$this->execQuery($queryhosts)){
                // one ore more sites failed to execute the query
                // we keep a trace
                $failedhosts[$site]=array("code"=>$this->responsecode,"message"=>$this->responsemessage);
            }else{
                $result=json_decode($this->queryresponse);
                foreach($result as $state){
                    switch($state[0]){
                        case "0":
                            $hosts["UP"]++;
                            $hosts["ALL TYPES"]++;
                            break;
                        case "1":
                            $hosts["DOWN"]++;
                            $hosts["ALL PROBLEMS"]++;
                            $hosts["ALL TYPES"]++;
                            break;
                        case "2":
                            $hosts["UNREACHABLE"]++;
                            $hosts["ALL PROBLEMS"]++;
                            $hosts["ALL TYPES"]++;
                            break;
                    }
                }

            }
        }
        return json_encode(array(
            "hosts"=>$hosts,
            "services"=>$services
        ));
    }



}
