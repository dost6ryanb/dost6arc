<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// ALWAYS insures to return a valid json OBJECT
class Device_model extends CI_Model {


	public function __construct()
	{
		$this->load->database();
	}

	public function devices($dev_id=false) {

		$this->db->from('devices');

		if ($dev_id != false) {
			$this->db->where('dev_id', $dev_id);
		}

		//EXECUTE the above query
		$query = $this->db->get();

		$n = $query->num_rows();

		if ($n > 0) {
			return $query->result();
		} else {
			return false;
		}

	}

}
?>