<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Archive extends CI_Controller {

public function __construct() {
	parent::__construct();
}


public function install_test() {
	$this->load->model('archive_model');
	$this->archive_model->install();
	return;
	$this->load->model('predict_model');

	// TEST
	// will be triggered by the user
	$dev_id = 25;
	$limit = 96;
	$sdate = '02/01/2016';
	$predict_data = $this->predict_model->get_device_data($dev_id, $limit, $sdate );

	if ($predict_data->count > 0) {
		$sdate_sql = DateTime::createFromFormat('m/d/Y', $sdate)->format('Y-m-d');
		foreach ($predict_data->data as $data) {
			$this->archive_model->create($dev_id, $sdate_sql, $data->dateTimeRead, json_encode($data));
		}
	} else {
		echo 'ERROR MSG: ' . $predict_data->dost6arc->message;
	}
}

// BACKUP YESTERDAY'S DATE
public function cron_run_backup_all_yesterday() {
	$sdate = $this->__get_yesterdays_date();
	$this->cron_run_backup_date($sdate);
}

public function cron_run_backup_date($sdate=false, $dev_id=false) {
	if ($sdate == false) {
		return;
	}

	$sdate = DateTime::createFromFormat('mdY', $sdate)->format('m/d/Y');

	$this->load->model('device_model');

	$o_devices;
	$num_of_devices = 0;
	$num_of_devices_backup_successfull = 0;

	if ($dev_id == false || !is_numeric($dev_id)) {
		$o_devices = $this->device_model->devices();
	} else {
		$o_devices = $this->device_model->devices($dev_id);
	}

	//GET LIST OF DEVICES,	
	
	$num_of_devices = count($o_devices);
	$num_of_devices_backup_successfull = 0;

	$this->__log('-Backing up ' . $num_of_devices . ' devices date: '. $sdate);
	flush();

	if ($num_of_devices > 0) {
		for ($i = 0;$i<$num_of_devices;$i++) {
			$o_device = $o_devices[$i];

			$this->__log(sprintf('[%d/%d] backing up device id %d', $num_of_devices_backup_successfull, $num_of_devices, $o_device->dev_id), false);
			
			//DELAY change to actual code
			$backup_process = $this->__backup($o_device->dev_id, $sdate);// START backup evalutes to false if backup failed
			$data_count =  $backup_process[0];
			$data_count_success_insert =  $backup_process[1];
			if ($data_count >= 0 && $data_count !== null) { 
				//sleep(1);
				$this->__log('.OK (count:'.$data_count.'|inserted:'.$data_count_success_insert.')');
				$num_of_devices_backup_successfull += 1;
			} elseif ($data_count === false) {
				$this->__log('.ERROR invalid device id ');
			} elseif ($data_count == -1) {
				$this->__log('.ERROR Cannot reach predict ');
			} else {
				$this->__log('.ERROR');
			}

			
			//if ($i >= 10) break; //FOR DEBUGGING break early
		}
		
	} else {
		$this->__log('!Warning: No Device to backup');
	}

	$this->__log('Backup ' . $sdate . ' done. Process Terminated.');
}

//actual backing up code
//dev_id int
//sdate string MM/DD/YYYY
// RETURNS an integer equivalent to json count of data
// return false if failed or no dev_id
private function __backup($dev_id=false, $sdate=false) {
	if ($dev_id == false || !is_numeric($dev_id)) { // ERROR please specify dev_id
		return false;
	}
	if ($sdate == false) {
		$sdate = (new DateTime())->format('m/d/Y');
	}

	$this->load->model('predict_model');
	$this->load->model('archive_model');

	$o_json = $this->predict_model->get_device_data($dev_id, "", $sdate, $sdate);
	$success_full_insert = 0;

	if ($o_json->count >= 0) {
		if ($o_json->count == 0) {//SPECIAL CASE INSERT BLANK BACKUP
			$this->archive_model->create($dev_id, $sdate, "", "[]");
		} else {

			foreach ($o_json->data as $data) {
				$dateTimeRead = $data->dateTimeRead;

				if (!isset($data->dateTimeRead)) {
					$dateTimeRead = "";
				}

				$try_backup = $this->archive_model->create($dev_id, $sdate, $dateTimeRead, json_encode($data));

				if ($try_backup) {
					$success_full_insert += 1;
				}
			}
		}
		return array($o_json->count, $success_full_insert);
	} else {
		return false;
	}

	return true;
}

private function __log($s, $addnewline=true) {
	echo $s;
	if ($addnewline) {
		if (PHP_SAPI === 'cli') 
		{ 
		   echo PHP_EOL;
		} 
		else
		{
		   echo "<BR/>";
		}
	}

	flush();
}

private function __get_yesterdays_date() {
	$date = new DateTime();
	$date->sub(new DateInterval('P1D'));
	return $date->format('mdY');
}

// POST 
// patern = device id
// sdate = mm/dd/yyyy - based on asti api server, convert to yyyy-mm-dd before passing to getdata
// edate = sdate

public function getdata() {
	$dev_id = $this->input->post('dev_id');
	$sdate = $this->input->post('sdate');
	$edate = $this->input->post('edate');

	if ($dev_id == false) return;
	if ($sdate == false) $sdate = date('m/d/Y');

	$sdate = DateTime::createFromFormat('m/d/Y', $sdate)->format('Y-m-d');

	if ($edate == false) $edate = $sdate;

	$edate = DateTime::createFromFormat('m/d/Y', $edate)->format('Y-m-d');

	$this->load->model('archives_model');
	$sdata = $this->archives_model->GetData($dev_id, $sdate, $edate);

	if ($sdata) {
		print($sdata);
	} else {
		echo '{"device":[{"dev_id":'.$dev_id.'}],"count":-1}';
	}
}





}