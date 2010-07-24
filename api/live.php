<?php
/**
 * base class to use live status implementation in shinken
 *
 * @package     shinkenapi
 * @author      David GUENAULT (dguenault@monitoring-fr.org)
 * @copyright   (c) 2010 Monitoring-fr Team / Shinken Team (http://monitoring-fr.org / http://www.shinken-monitoring.org)
 * @license     Affero GPL
 * @version     0.1
 */
class live {

        protected	$socket         = null;
        protected   	$host           = null;
        protected   	$port           = null;
        protected   	$buffer         = null;
        protected   	$newline        = "\n";
	protected   	$livever	= null;	
        protected   	$headersize     = 16;
	// this define query options that will be added to each query (default options)
        public   	$defaults       = array(
                        	                "ColumnHeaders: on",
                        	                "ResponseHeader: fixed16",
                        	                "OutputFormat: json"
                        	          );
	// this is used to define which error code are used
	protected $messages = array(
		"versions" => array(
			"1.0.19" => "old",
			"1.0.20" => "old",
			"1.0.21" => "old",
			"1.0.22" => "old",
			"1.0.23" => "old",
			"1.0.24" => "old",
			"1.0.25" => "old",
			"1.0.26" => "old",
			"1.0.27" => "old",
			"1.0.28" => "old",
			"1.0.29" => "old",
			"1.0.30" => "old",
			"1.0.31" => "old",
			"1.0.32" => "old",
			"1.0.33" => "old",
			"1.0.34" => "old",
			"1.0.35" => "old",
			"1.0.36" => "old",
			"1.0.37" => "old",
			"1.0.38" => "old",
			"1.0.39" => "old",
			"1.1.0" => "old",
			"1.1.1" => "old",
			"1.1.2" => "old",
			"1.1.3" => "new",
			"1.1.4" => "new",
			"1.1.5i0" => "new",
			"1.1.5i1" => "new",
			"1.1.5i2" => "new",
			"1.1.5i3" => "new",
			"1.1.6b2" => "new",
			"1.1.6b3" => "new",
			"1.1.6rc1" => "new",
			"1.1.6rc2" => "new",
			"1.1.6rc3" => "new",
			"1.1.6p1" => "new",
			"1.1.7i1" => "new",
			"1.1.7i2" => "new"
		),
		"old" => array(
			"200"=>"OK. Reponse contains the queried data.",
			"401"=>"The request contains an invalid header.",
			"402"=>"The request is completely invalid.",
			"403"=>"The request is incomplete",
			"404"=>"The target of the GET has not been found (e.g. the table).",
			"405"=>"A non-existing column was being referred to"
		),
		"new" => array(
			"200"=>"OK. Reponse contains the queried data.",
			"400"=>"The request contains an invalid header.",
			"403"=>"The user is not authorized (see AuthHeader)",
			"404"=>"The target of the GET has not been found (e.g. the table).",
			"450"=>"A non-existing column was being referred to",
			"451"=>"The request is incomplete.",
			"452"=>"The request is completely invalid."
		)
	);


        public	$queryresponse  = null;
	public	$responsecode = null; 
	public  $responsemessage = null; 




	public function __construct($host,$port,$buffer=1024)
	{
                $this->host   = $host;
                $this->port   = $port;
                $this->buffer = $buffer;
		$this->getLivestatusVersion();
	}

        public function  __destruct() {
            $this->disconnect();
            $this->queryresponse = null;
        }

	public function connect(){
            $this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if( $this->socket == false){
                $this->socket = false;
                return false;
            }
            $result = @socket_connect($this->socket, $this->host,$this->port);
            if ($result == false){
                $this->socket = null;
                return false;
            }
            return true;
	}

	public function disconnect(){
            if ( ! is_null($this->socket)){
                // disconnect gracefully
                socket_shutdown($this->socket,2);
                socket_close($this->socket);
                $this->socket = null;
                return true;
            }else{
                return false;
            }

	}

	public function query($elements,$default="json") {
            $query = $this->preparequery($elements,$default);
            foreach($query as $element){
                if(($this->socketwrite($element.$this->newline)) == false){
                    return false;
                }
            }
            // finalize query
            if(($this->socketwrite($this->newline)) == false){
                return false;
            }
            return true;
	}


	public function readresponse(){
            $this->queryresponse="";

            if ( ! is_null( $this->socket ) ){
                $headers = $this->getHeaders();
                $code = $headers["statuscode"];
                $size = $headers["contentlength"];

		$this->responsecode = $code;
		$this->responsemessage = $this->code2message($code);
		if($code != "200"){ 
			return false; 
		}
                $this->queryresponse = $this->socketread($size);
                return true;
            }else{
                return false;
            }
        }


/**
 * PRIVATE METHODS
 */

	private function getLivestatusVersion(){
		$query = array(
			"GET status",
			"Columns: livestatus_version",
		);
		$this->connect(); 
		$this->query($query); 
		$this->readresponse();
		$this->disconnect(); 
		$result = json_decode($this->queryresponse);
		$this->livever = $result[1][0];
	}

	private function code2message($code){
		if ( ! isset($this->messages["versions"][$this->livever])){
			// assume new
			$type = "new";
		}else{
			$type = $this->messages["versions"][$this->livever];
		}
		$message = $this->messages[$type][$code];
		return $message;
	}




        private function socketread($size){
            if ( ! is_null( $this->socket ) ){
                $buffer = $this->buffer;
                $socketData = "";
                if($size <= $buffer){
                    $socketData = @socket_read($this->socket,$size);
                }else{
                    while($read = @socket_read($this->socket, $buffer)){
                        $socketData .= $read;
                    }
                }
		return $socketData;
            }else{
                return false;
            }
        }

	private function socketwrite($data){
            if ( ! is_null( $this->socket ) ){
                if (socket_write($this->socket, $data) === false){
                    return false;
                }else{
                    return true;
                }
            }else{
                return false;
            }
            return true;
	}

        public function getSocketError(){
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            return array(
                "code"=>$errorcode,
                "message"=>$errormsg
            );
        }

        private function getHeaders(){
            if ( ! is_null( $this->socket ) ){
                $rawHeaders = @socket_read($this->socket, $this->headersize);
                return array(
                    "statuscode" => substr($rawHeaders, 0, 3),
                    "contentlength" => intval(trim(substr($rawHeaders, 4, 11)))
                );
            }else{
                return false;
            }
        }

        private function preparequery($elements){
            $query=$this->defaults;
            return array_merge((array)$elements,(array)$query);
        }
}
