<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Archive_model extends CI_Model {
	public function __construct()
	{
		$this->load->database();
	}

	public function install() {
		$this->load->dbforge();

		$fields = array(
        'dev_id' => array(
                'type' => 'INT'
        ),
        'sdate' => array(
                'type' => 'VARCHAR(10)'
        ),
        'datetimeread' => array(
                'type' =>'DATETIME',
                'null' => true
        ),

        'data' => array(
                'type' => 'TEXT'
        ),
		);

		$this->dbforge->add_field('id');
		$this->dbforge->add_field($fields);
		

		$this->dbforge->create_table('archive', TRUE);
		$this->db->query('ALTER TABLE archive ADD UNIQUE INDEX (dev_id, sdate, datetimeread)');

	}

	public function create($dev_id, $sdate, $datetimeread, $device_data) {
		if ($this->isexist($dev_id, $sdate, $datetimeread)) {
			return false;
		} else {
			$data = array(
		        'dev_id' => $dev_id,
		        'sdate' => $sdate,
		        'datetimeread' => $datetimeread,
		        'data' => $device_data
				);

			$this->db->insert('archive', $data);

			if ($this->db->affected_rows() > 0) {
				return true;
			} else {
				return false;	
			}
			
		}
	}

	public function isexist($dev_id, $sdate, $datetimeread = '') {
		return false;
	}

	public function get($dev_id, $limit=false, $sdate="", $edate="") {
		$this->db->from('archive');
		$this->db->where(array('dev_id' => $dev_id, 'sdate' => $sdate));
		
		$this->db->order_by('datetimeread', 'desc');
		if (is_numeric($limit)) {
			$this->db->limit($limit);
		}

		$query = $this->db->get();

		return $query->result();
	}

}
?>