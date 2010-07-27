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
class live {
        /**
         * the socket used to communicate 
         * @var ressource
         */
        protected	$socket         = null;
        /**
         * path to the livestatus unix socket 
         * @var string 
         */
        protected       $socketpath     = null;
        /**
         * host name or ip address of the host that serve livestatus queries
         * @var string 
         */
        protected   	$host           = null;
        /**
         * TCP port of the remote host that serve livestatus queries
         * @var int
         */
        protected   	$port           = null;
        /**
         * the socket buffer read length
         * @var int
         */
        protected   	$buffer         = null;
        /**
         * the cahracter used to define newlines (livestatus use double newline character end of query definition)
         * @var char
         */
        protected   	$newline        = "\n";
        /**
         * this is the version of livestatus it is automaticaly filed by the getLivestatusVersion() method
         * @var string
         */
	protected   	$livever	= null;	
        /**
         * default headersize (in bytes) returned after a livestatus query (always 16)
         * @var int
         */
        protected   	$headersize     = 16;
        /**
         *
         * @commands array list all authorized commands
         */
        protected       $commands = null;
        /**
         * this define query options that will be added to each query (default options)
         * @var array
         */
        public   	$defaults       = array(
                        	                "ColumnHeaders: on",
                        	                "ResponseHeader: fixed16",
                                              //  "KeepAlive: on",
                        	                "OutputFormat: json"
                        	          );
	/**
         * used to make difference between pre 1.1.3 version of livestatus return code and post 1.1.3
         * @var array
         */
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


        /**
         * json response of the query
         * @var string
         */
        public	$queryresponse  = null;
        /**
         * response code returned after query
         * @var int
         */
	public	$responsecode = null;
        /**
         * response message returned after query
         */
	public  $responsemessage = null; 

        /**
         * Class Constructor
         * @param array params array of parameters (if socket is in the keys then the connection is UNIX SOCKET else it is TCP SOCKET)
         * @param int buffer used to read query response
         */
	public function __construct($params,$buffer=1024)
	{
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
        public function  __destruct() {
            $this->disconnect();
            $this->queryresponse = null;
        }


        /**
         * execute a livestatus query and return result as json
         * @param array $query elements of the querry (ex: array("GET services","Filter: state = 1","Filter: state = 2", "Or: 2"))
         * @return string the json encoded result of the query
         */
        public function execQuery($query){
            if($this->socket){
                $this->disconnect();
            }
            if(!$this->connect()){
                $this->responsecode = "501";
                $this->responsemessage="[SOCKET] ".$this->getSocketError();
                return false;
            }else{
                if(!$this->query($query)){
                    $this->responsecode = "501";
                    $this->responsemessage="[SOCKET] ".$this->getSocketError();
                    $this->disconnect();
                    return false;
                }else{
                    if(!$this->readresponse()){
                        $this->disconnect();
                        return false;
                    }else{
                        $this->disconnect();
                        return $this->queryresponse;
                    }
                }
            }
        }

        /**
         * This method submit an external command to nagios through livestatus socket.
         * @param array $command an array describing the command array("COMMANDNAME",array("paramname"=>"value","paramname"=>"value",...)
         * @return bool true if success false il failed
         */
        public function sendExternalCommand($command){
            if(!$this->parseCommand($command)){
                return false;
            }else{
                if(!$this->submitExternalCommand($command)){
                    return false;
                }else{
                    return true;
                }
            }
        }

        /**
         * load commands defined in commands.inc.php
         */
        public function getCommands($commands){
            $this->commands = $commands;
        }


/**
 * PRIVATE METHODS
 */

        /**
         * Abstract method that choose wich connection method we should use.....
         */
        private function connect(){
            if(is_null($this->socketpath)){
                return $this->connectTCP();
            }else{
                return $this->connectUNIX();
            }
	}
        /**
         * connect to livestatus through TCP.
         * @return bool true if success false if fail
         */
        private function connectTCP(){
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

        private function connectUNIX(){
            $this->socket = @socket_create(AF_UNIX, SOCK_STREAM, SOL_SOCKET);
            if( $this->socket == false){
                $this->socket = false;
                return false;
            }
            $result = @socket_connect($this->socket, $this->socketpath);
            if ($result == false){
                $this->socket = null;
                return false;
            }
            return true;
        }

        private function connectSSH(){
            die("Not implemented");
        }


	private function disconnect(){
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
	/**
	 * get livestatus version and put it in livever class property
	 */
	protected function getLivestatusVersion(){
		$query = array(
			"GET status",
			"Columns: livestatus_version",
		);
                $this->execQuery($query);
		$result = json_decode($this->queryresponse);

		$this->livever = $result[1][0];
                $this->responsecode=null;
                $this->responsemessage=null;
                $this->queryresponse=null;
	}

        private function query($elements,$default="json") {
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


	private function readresponse(){
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
            return $errormsg;
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


        /**
         * This function submit an external command to the livestatus enabled host
         * @param array $command an array defining the command array("commandname",array("argname"=>"value","argname"=>"value",...))
         * @return bool true if success, false if failed
         */
        private function submitExternalCommand($command){
            $arguments = "";
            foreach(array_keys($command[1]) as $key){
                
                $arguments .= $command[1][$key].";";
            }
            $arguments = substr($arguments, 0, strlen($arguments)-1);
            $commandline = "COMMAND [".time()."] ".$command[0].";".$arguments;
            if($this->socket){
                $this->disconnect();
            }
            if(!$this->connect()){
                $this->responsecode = "501";
                $this->responsemessage="[SOCKET] ".$this->getSocketError();
                return false;
            }else{
                if(!$this->query($commandline)){
                    $this->responsecode = "501";
                    $this->responsemessage="[SOCKET] ".$this->getSocketError();
                    $this->disconnect();
                    return false;
                }else{
                    $this->responsecode = null;
                    $this->responsemessage = null;
                    return true;
                }
            }
        } 
        /**
         * This function is used to parse and validate commands before submit them.
         * @param array $command an array defining the command array("commandname",array("argname"=>"value","argname"=>"value",...))
         * @return bool true id ok false if not. the raison is stored in responsecode and response message class properties.
         */
        private function parseCommand($command){

            // check if there is 2 elements in the array
            if(count($command) != 2){
                $this->responsecode = "602";
                $this->responsemessage = "Invalid message definition (wrong number of entries in \$command)";
            }else{
                // check if first element exist as a key in commands definition
                if(!array_key_exists($command[0], $this->commands)){
                    $this->responsecode = "602";
                    $this->responsemessage = "Invalid message definition (command ".$command[0]." not found)";
                }else{
                    // check number of arguments against command definition
                    if(count($this->commands[$command[0]]) != count($command[1])){
                        $this->responsecode = "602";
                        $this->responsemessage = "Invalid number of arguments (required : ".count($this->commands[$command[0]]).", provided : ".count($command[1]).")";
                    }else{
                        // check argument's names
                        $defined_keys = $this->commands[$command[0]];
                        $provided_keys = array_keys($command[1]);
                        $diff_keys = array_diff($defined_keys, $provided_keys);
                        if ( count($diff_keys) > 0 ){
                            $this->responsecode = "602";
                            $this->responsemessage = "The arguments provided doesn't match the required arguments (".implode(", ", $diff_keys).")";
                        }else{
                            $this->responsecode = null;
                            $this->responsemessage = null;
                            return true;
                        }
                    }
                }
            }
            return false;
        }
}
