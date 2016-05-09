<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// ALWAYS insures to return a valid json OBJECT
class Device_model extends CI_Model {


	public function __construct()
	{
		$this->load->database();
	}

	public function devices($dev_id=false) {

		$this->db->select('d.*, t.name as type_id');
		$this->db->from('devices as d');
		$this->db->join('types as t', 'd.type_id = t.id', 'left outer');

		if ($dev_id != false) {
			$this->db->where('d.dev_id', $dev_id);
		}
		$this->db->order_by('d.dev_id', 'ASC');

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