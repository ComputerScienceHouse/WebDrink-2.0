<?php

/*
*	Abstract API class to handle incoming requests, pass info on to concrete classes, and send a response
* 	Based heavily off of this example: http://coreymaynard.com/blog/creating-a-restful-api-with-php/
*/
abstract class API 
{
	// HTTP method used to make the request (GET, POST, PUT, DELETE)
	protected $method = '';
	// The model requested
	protected $endpoint = '';
	// The specific action 
	protected $verb = '';
	// Paramaters passed with the request
	protected $args = array();
	// API Key
	protected $api_key = false;

	// Constructor
	public function __construct($request) {
		// Set appropriate HTTP headers
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
		header("Access-Control-Allow-Origin: *");
		header("Access-Control-Allow-Methods: *");
		header("Content-Type: application/json");

		// Get the components of the request
		$this->args = explode('/', rtrim($request, '/'));
		$this->endpoint = array_shift($this->args);
		if (array_key_exists(0, $this->args) && !is_numeric($this->args[0])) {
			$this->verb = array_shift($this->args);
		}
		$this->method = $_SERVER["REQUEST_METHOD"];

		// Check for an API key
		//die(var_dump($this->args));
		for ($i = 0; $i < count($this->args); $i++) {
			if ($this->args[$i] == "api_key" && array_key_exists($i+1, $this->args)) {
				$this->api_key = $this->args[$i+1];
				array_slice($this->args, $i, 2);
				break;
			}
		}

		// Sanitise the input
		if ($this->method == "POST") 
			$this->request = $this->_sanitizeInput($_POST);
		else if ($this->method == "GET")
			$this->request = $this->_sanitizeInput($_GET);
		else
			$this->_response("Invalid Method", 405);
	}

	// Call the endpoint method in the concrete class
	public function processAPI() {
		if ((int) method_exists($this, $this->endpoint) > 0) 
			return $this->_response($this->{$this->endpoint}($this->args));
		return $this->_response("", 400);
	}

	// Return the JSON-encoded response from the API
	protected function _response($data, $status = 200) {
		header("HTTP/1.1 " . $status . " " . $this->_requestStatus($status));
		return json_encode($data);
	}

	// Sanitize paramaters passed in the request
	private function _sanitizeInput($data) {
		$clean = array();
		if (is_array($data)) {
			foreach ($data as $k => $v) {
				$clean[$k] = $this->_sanitizeInput($v);
			}
		}
		else {
			$clean = trim(strip_tags($data));
		}
		return $clean;
	}

	// Look up the corresponding name for a status code
	private function _requestStatus($code) {
        $status = array( 
            100 => 'Continue',   
            101 => 'Switching Protocols',   
            200 => 'OK', 
            201 => 'Created',   
            202 => 'Accepted',   
            203 => 'Non-Authoritative Information',   
            204 => 'No Content',   
            205 => 'Reset Content',   
            206 => 'Partial Content',   
            300 => 'Multiple Choices',   
            301 => 'Moved Permanently',   
            302 => 'Found',   
            303 => 'See Other',   
            304 => 'Not Modified',   
            305 => 'Use Proxy',   
            306 => '(Unused)',   
            307 => 'Temporary Redirect',   
            400 => 'Bad Request',   
            401 => 'Unauthorized',   
            402 => 'Payment Required',   
            403 => 'Forbidden',   
            404 => 'Not Found',   
            405 => 'Method Not Allowed',   
            406 => 'Not Acceptable',   
            407 => 'Proxy Authentication Required',   
            408 => 'Request Timeout',   
            409 => 'Conflict',   
            410 => 'Gone',   
            411 => 'Length Required',   
            412 => 'Precondition Failed',   
            413 => 'Request Entity Too Large',   
            414 => 'Request-URI Too Long',   
            415 => 'Unsupported Media Type',   
            416 => 'Requested Range Not Satisfiable',   
            417 => 'Expectation Failed',   
            500 => 'Internal Server Error',   
            501 => 'Not Implemented',   
            502 => 'Bad Gateway',   
            503 => 'Service Unavailable',   
            504 => 'Gateway Timeout',   
            505 => 'HTTP Version Not Supported'
        ); 
        return ($status[$code]) ? $status[$code] : $status[500]; 
    }
}

?>