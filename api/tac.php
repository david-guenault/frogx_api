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
require_once './live.php';

class tac extends live{
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
    public function getTac($sites=null){
        
        return true;
    }

}
