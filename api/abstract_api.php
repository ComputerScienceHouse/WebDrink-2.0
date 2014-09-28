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
	// Request params/data
	protected $request = array();
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

		// Grab the HTTP method
		$this->method = $_SERVER['REQUEST_METHOD'];
        if ($this->method == 'POST' && array_key_exists('HTTP_X_HTTP_METHOD', $_SERVER)) {
            if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'DELETE') {
                $this->method = 'DELETE';
            } else if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'PUT') {
                $this->method = 'PUT';
            } else {
                throw new Exception("Unexpected Header");
            }
        }

        // Grab the API key
		$this->api_key = (array_key_exists("api_key", $_REQUEST)) ? htmlentities($_REQUEST["api_key"]) : false;
	}

	// Call the endpoint method in the concrete class
	public function processAPI() {
		switch($this->method) {
        case 'DELETE':
        case 'POST':
            $this->request = $this->_sanitizeInput($_POST);
            break;
        case 'GET':
            $this->request = $this->_sanitizeInput($_GET);
            break;
        case 'PUT':
            $this->request = $this->_sanitizeInput($_GET);
            $this->file = file_get_contents("php://input");
            break;
        default:
            return $this->_response('Invalid Method', 405);
            break;
        }

		if ((int) method_exists($this, $this->endpoint) > 0) 
			return $this->_response($this->{$this->endpoint}($this->args));
		
		return $this->_response($this->_result(false, "No Endpoint: $this->endpoint", false), 404);
	}

	// Format the result of an API call
	protected function _result($status, $message, $data) {
		return array(
			"status" => $status,
			"message" => $message,
			"data" => $data
		);
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
            200 => 'OK',
            404 => 'Not Found',   
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
        ); 
        return ($status[$code])?$status[$code]:$status[500]; 
    }
}

?>