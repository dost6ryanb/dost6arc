<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// ALWAYS insures to return a valid json OBJECT
class Predict_model extends CI_Model {

	const ERROR_PREDICT_404 = 'Cannot reach PREDICT';
	const ERROR_PREDICT_RETURN_NONJSON = 'PREDICT returned an invalid json string';

	public function __construct()
	{
		$this->load->database();
	}

	public function get_device_data($dev_id, $limit="", $sdate = "", $edate = "") {
		$str_device_data_json = $this->get_device_data_s($dev_id, $limit, $sdate, $edate);

		$o_device_data = json_decode($str_device_data_json);

		switch (json_last_error()) {
			case JSON_ERROR_NONE:
            	//echo ' - No errors';
				return $o_device_data;
	        	break;
	        case JSON_ERROR_DEPTH:
	            //echo ' - Maximum stack depth exceeded';
	       		//break;
	        case JSON_ERROR_STATE_MISMATCH:
	            //echo ' - Underflow or the modes mismatch';
	        	//break;
	        case JSON_ERROR_CTRL_CHAR:
	            //echo ' - Unexpected control character found';
	        	//break;
	        case JSON_ERROR_SYNTAX:
	            //echo ' - Syntax error, malformed JSON';
	        	//break;
	        case JSON_ERROR_UTF8:
	            //echo ' - Malformed UTF-8 characters, possibly incorrectly encoded';
	        	//break;
	        default:
	            //echo ' - Unknown error';
	            return json_decode($this->_generate_error_json($dev_id, self::ERROR_PREDICT_RETURN_NONJSON));
	        	break;
		}
	}

	// RETURNS: json string
	// WARNING: please do use this directly from controller since it doesn't ALWAYS return a valid json string incase of an error from external server (Predict)
	// speed-hack to avoid json string to json object back to json string convertion
	// USES:
	// debugging, for faster string json output
	//
	// use get_device_data() to return object usefull for internal system stuffs
	public function get_device_data_s($dev_id, $limit, $sdate = "", $edate = "") {
		return $this->_get_api_post_output($dev_id, $limit, $sdate, $edate);
	}

	public function get_device_info($dev_id) {
		$str_device_data_json = $this->_get_api_post_output($dev_id, '1', "", "");

		$o_device_data = json_decode($str_device_data_json);
		switch (json_last_error()) {
			case JSON_ERROR_NONE:
            	//echo ' - No errors';
				$o_device_info = $o_device_data->device[0];
				return $o_device_info;
	        	break;
	        case JSON_ERROR_DEPTH:
	            //echo ' - Maximum stack depth exceeded';
	       		//break;
	        case JSON_ERROR_STATE_MISMATCH:
	            //echo ' - Underflow or the modes mismatch';
	        	//break;
	        case JSON_ERROR_CTRL_CHAR:
	            //echo ' - Unexpected control character found';
	        	//break;
	        case JSON_ERROR_SYNTAX:
	            //echo ' - Syntax error, malformed JSON';
	        	//break;
	        case JSON_ERROR_UTF8:
	            //echo ' - Malformed UTF-8 characters, possibly incorrectly encoded';
	        	//break;
	        default:
	            //echo ' - Unknown error';
	            return json_decode($this->_generate_error_json($dev_id, self::ERROR_PREDICT_RETURN_NONJSON));
	        	break;
		}

	}


	//REQUIRES
	// dev_id - int not null
	// limit - int
	// sdate - string date (MM/dd/YYYY) (default '' interpreted as current metrological date by predict server) e.g. 01/01/2011 08:00 - 01/02/2011 7:59 belongs to 01/01/2011
	// edate - same as sdate
	//RETURNS: string json from external source (predict's rest api server)
	// ON SUCCESS :
	// common format.
	// {
	//	'data' : [{key1:value1, key2:value2}],
	//	'device' : [{key1:value1, key2:value2}],
	//	'column' : [value1, value2, value3],
	//	'count': '2'
	// }
	//
	// ON FAILURE:
	// {
	// 		'device' : [{'dev_id' : $dev_id}],
	//		'count' : -1
	//
	// }
	private function _get_api_post_output($dev_id, $limit, $sdate, $edate) {

		$url = 'http://fmon.asti.dost.gov.ph/weather/home/index.php/device/getData/';
		$data = array('start' => '0', 'limit' => $limit, 'sDate' => $sdate, 'eDate' => $edate, 'pattern' => $dev_id);

		$options = array(
			'http' => array(
				'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
				'method'  => 'POST',
				'content' => http_build_query($data),
				// 'proxy' => 'tcp://127.0.0.1:8888'
				),
		);

		$context  = stream_context_create($options);
		$result = @file_get_contents($url, false, $context);

		if ($result == FALSE) {
			return $this->_generate_error_json($dev_id, self::ERROR_PREDICT_404);
		} else {
			return $result;
		}
	}

	private function _generate_error_json($dev_id, $error_str) {
		return '{"device":[{"dev_id":' . $dev_id . '}],"count":-1, "dost6arc": {"message":"' . $error_str . '"}}';
	}

}
?>