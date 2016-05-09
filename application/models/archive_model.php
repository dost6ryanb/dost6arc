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
        )
        ,
        'sdate_sql' => array(
                'type' =>'DATE',
                'null' => true
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
			$sdate_sql = $this->_predict_to_mysql_date_str($sdate);
			$data = array(
		        'dev_id' => $dev_id,
		        'sdate' => $sdate,
		        'datetimeread' => $datetimeread,
		        'sdate_sql' => $sdate_sql,
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

	public function get($dev_id, $limit, $sdate, $edate) {
		$this->db->from('archive');
		$this->db->where('dev_id', $dev_id);
		if ($sdate == $edate) {
			$this->db->where("sdate", $sdate);
		} else {
			$this->db->where("sdate_sql BETWEEN '".  $this->_predict_to_mysql_date_str($sdate). "' AND '". $this->_predict_to_mysql_date_str($edate)."'");
		}
		
		$this->db->order_by('datetimeread', 'desc');
		if (is_numeric($limit)) {
			$this->db->limit($limit);
		}

		$query = $this->db->get();
		return $query->result();
	}

	private function _predict_to_mysql_date_str($date) {
		return \DateTime::createFromFormat('m/d/Y', $date)->format('Y-m-d');
	}

}
?>