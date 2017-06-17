<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Archive extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
    }


    public function install_test()
    {
        $this->load->model('archive_model');
        $this->archive_model->install();
        return;
        $this->load->model('predict_model');

        // TEST
        // will be triggered by the user
        $dev_id = 25;
        $limit = 96;
        $sdate = '01/01/2016';
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
    public function cron_run_backup_all_yesterday()
    {
        $sdate = $this->__get_yesterdays_date();
        $this->cron_run_backup_date($sdate);
    }


    //USES transactions to make batch inserts faster
    //but must not be used for updating partially backuped data
    //for it the whole insert batch will fail
    /*
    public function cron_run_backup_date($sdate = false, $start = 0, $dev_id = false)
    {
        if ($sdate == false) {
            $this->__log('sdate required.');
            return false;
        }
        
        try {
            $sdate = DateTime::createFromFormat('mdY', $sdate)->format('m/d/Y');
        } catch (Exception $e) {
            $this->__log('Invalid start/end date');
            return false;
        }

        $this->load->model('device_model');

        $o_devices;

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

        if (!is_numeric($start) || $start > $num_of_devices) {
            $start = 0;
        }

        if ($num_of_devices > 0) {
            for ($i = $start; $i<$num_of_devices; $i++) {
                $o_device = $o_devices[$i];

                $this->__log(sprintf('[%d-%d/%d] backing up device id %d', $i, $num_of_devices_backup_successfull, $num_of_devices, $o_device->dev_id));
            
                $backup_process = $this->__backup($o_device->dev_id, $sdate);// START backup evalutes to false if backup failed
                $data_count =  $backup_process[0];
                $data_count_success_insert =  $backup_process[1];
                if ($data_count >= 0 && $data_count !== null) {
                    //sleep(1);
                    $this->__log('.OK (count:'.$data_count.'|inserted:'.$data_count_success_insert.')');
                    $num_of_devices_backup_successfull += 1;
                } elseif ($data_count == -1) {
                    $this->__log('.ERROR. 404 Predict');
                } else {
                    $this->__log('.ERROR');
                }

            
                //if ($i >= 10) break; //FOR DEBUGGING break early
            }
        } else {
            $this->__log('!Warning: No Device to backup');
        }

        $this->__log(sprintf('[%d/%d] Backup %s done.', $num_of_devices_backup_successfull, $num_of_devices, $sdate));
    }
    */
    //update by fetching the last data (limit = 1) from predict
    public function cron_quick_update($sdate = false, $start = 0, $dev_id = false)
    {
    }

    public function cron_update_batch_device($sdate = false, $skipto = 0)
    {
        try {
            $sdate = DateTime::createFromFormat('mdY', $sdate)->format('m/d/Y');
        } catch (Exception $e) {
            $this->__log('sdate exception');
            return false;
        }

        $this->load->model('device_model');

        $o_devices = $this->device_model->devices();
        $o_devices_count = count($o_devices);
        $device_backup_success_count = 0;

        $this->__log('>Batch backup ' . $o_devices_count . ' devices Date: '. $sdate);

        if (!is_numeric($skipto) || $skipto > $o_devices_count) {
            $skipto = 0;
        }

        if ($o_devices_count > 0) {
            for ($i = $skipto; $i<$o_devices_count; $i++) {
                $device = $o_devices[$i];

                $this->__log(sprintf('>[%d-%d/%d] dev_id: %d', $i, $device_backup_success_count, $o_devices_count, $device->dev_id));
                $backup = $this->_backup($device->dev_id, $sdate);

                $backup_json_count = $backup[0];
                $backup_insert_count = $backup[1];

                if ($backup_json_count == 0) {
                    $this->__log('.OK Empty Data');
                    $device_backup_success_count += 1;
                } elseif ($backup_json_count > 0) {
                    $this->__log('.OK (count:'.$backup_json_count.'|inserted:'. $backup_insert_count.')');
                    $device_backup_success_count += 1;
                } else {
                    $this->__log('.ELSE ERR');
                }
            }
        } else {
            $this->__log('!Warning: No Device to backup');
        }

        $this->__log(sprintf('[%d/%d] Backup %s done.', $device_backup_success_count, $o_devices_count, $sdate));
    }

    public function cron_update_batch_date($dev_id = false, $sdate = false, $edate = false, $limit = 720)
    {
        if ($dev_id == false || !is_numeric($dev_id)) { // ERROR please specify dev_id
            $this->__log('Invalid Device ID');
            return false;
        }
        if ($sdate == false) {
            $this->__log('Date Required');
            return false;
        }

        if ($edate == false) {
            $edate = $sdate;
        }

        $beginDate;
        $daterange;
        $endDate;

        try {
            $beginDate = DateTime::createFromFormat("mdY", $sdate);
            $endDate = DateTime::createFromFormat("mdY", $edate);
            $interval = new DateInterval('P1D');
            $adjustedEndDate = clone $endDate;
            $adjustedEndDate->add($interval);
            $daterange = new DatePeriod($beginDate, $interval, $adjustedEndDate);
        } catch (Exception $e) {
            $this->__log('Invalid start/end date');
            return false;
        }

            $this->__log(sprintf("Backing Up %d: %s - %s", $dev_id, $beginDate->format('m/d/Y'), $endDate->format('m/d/Y')), true);

            $device_backup_success_count = 0;
            $total_dates = iterator_count($daterange);

        foreach ($daterange as $date) {
            $datePredict = $date->format('m/d/Y');
            $this->__log(sprintf('[%d-%d] %s', $device_backup_success_count, $total_dates, $datePredict), true);

            $backup = $this->_backup($dev_id, $datePredict);

            $backup_json_count = $backup[0];
            $backup_insert_count = $backup[1];

            if ($backup_json_count == 0) {
                $this->__log('.OK Empty Data');
                $device_backup_success_count += 1;
            } elseif ($backup_json_count > 0) {
                $this->__log('.OK (count:'.$backup_json_count.'|inserted:'. $backup_insert_count.')');
                $device_backup_success_count += 1;
            } else {
                $this->__log('.ELSE ERR');
            }
        }
    
        $this->__log(sprintf("BackedUp %d: %s - %s Done.", $dev_id, $beginDate->format('m/d/Y'), $endDate->format('m/d/Y')));
    }

    private function _backup($dev_id, $sdate, $limit = 720)
    {
        $this->load->model('predict_model');
        $this->load->model('archive_model');

        $json_count = 0;
        $insert_count = 0;
        $fetching_success = false;
        while ($fetching_success == false) {
            $this->__log('Fetching... ', false);
            $time_pre = microtime(true);
            $o_json = $this->predict_model->get_device_data($dev_id, $limit, $sdate, $sdate);
        
            if ($o_json->count == 0 || $o_json->count >= 0) {
                $time_post = microtime(true);
                $this->__log($time_post - $time_pre, true);
                $fetching_success = true;
                //$json_count = $o_json->count; //less reliable duh predict
                $json_count = count($o_json->data);
            } else {
                $this->__log('Network Error');
                sleep(1);    
            }
        }

        
        $this->archive_model->trans_begin();

        if ($o_json->count == 0) {
            $this->archive_model->create($dev_id, $sdate, "", "[]");
            $this->archive_model->trans_commit();
        } else {
            $this->__log('Inserting... ', false);
            $time_pre = microtime(true);

            foreach ($o_json->data as $data) {
                   $dateTimeRead = substr($data->dateTimeRead, 0, 19);
                   $try_backup = $this->archive_model->create($dev_id, $sdate, $dateTimeRead, json_encode($data));
             
                if ($try_backup) {
                    $insert_count += 1;
                }
            }
            $this->archive_model->trans_commit();
            $time_post = microtime(true);
            $this->__log($time_post - $time_pre, true);
        }

         return [$json_count, $insert_count];
    }

//actual backing up code
//dev_id int
//sdate string MM/DD/YYYY
// RETURNS an integer equivalent to json count of data
// return false if failed or no dev_id
 /*
    private function __backup($dev_id = false, $sdate = false, $dotrans = false)
    {
        if ($dev_id == false || !is_numeric($dev_id)) { // ERROR please specify dev_id
            return false;
        }
        if ($sdate == false) {
            $sdate = (new DateTime())->format('m/d/Y');
        }

        $this->load->model('predict_model');
        $this->load->model('archive_model');
    
        do {
            $success = false;
            $this->__log('Fetching... ', false);
            $time_pre = microtime(true);
            $o_json = $this->predict_model->get_device_data($dev_id, "720", $sdate, $sdate); //CHECK THIS
            $time_post = microtime(true);
            $exec_time = $time_post - $time_pre;
            $this->__log($exec_time, true);

            $success_full_insert = 0;

            if ($o_json->count == 0) {
                $this->archive_model->start_trans();
                $this->archive_model->create($dev_id, $sdate, "", "[]");
                $this->archive_model->end_trans();
                $success = true;
                return [0, 0];
            } elseif ($o_json->count >= 0) {
                $this->__log('Inserting... ', false);
                $time_pre = microtime(true);

                $this->archive_model->start_trans();
                foreach ($o_json->data as $data) {
                    $dateTimeRead = substr($data->dateTimeRead, 0, 19);

                    if (!isset($data->dateTimeRead)) {
                        $dateTimeRead = "";
                    }

                    //$this->__log("#" . $dateTimeRead, false);

                    $try_backup = $this->archive_model->create($dev_id, $sdate, $dateTimeRead, json_encode($data));

                    if ($this->archive_model->trans_status() === false) {
                        $this->__log("#" . $dateTimeRead . " trans failed");
                    }

                    if ($try_backup) {
                        $success_full_insert += 1;
                        //$this->__log("OK#");
                    } else {
                        //$this->__log("ERROR#");
                    }
                }
                $this->archive_model->end_trans();

                $time_post = microtime(true);
                $exec_time = $time_post - $time_pre;
                $this->__log($exec_time, true);

                $success = true;
                return [$o_json->count, $success_full_insert];
            } else {
                //return $o_json; //SIMILAR to try catch throw. let the caller handle errors
                $success = false;
                //return [-1, 0];
            }
        } while ($success == false);
    }
*/
    private function __log($s, $addnewline = true)
    {
        echo $s;
        if ($addnewline) {
            if (PHP_SAPI === 'cli') {
                echo PHP_EOL;
            } else {
                echo "<BR/>";
            }
        }

        flush();
    }

    private function __get_yesterdays_date()
    {
        $date = new DateTime();
        $date->sub(new DateInterval('P1D'));
        return $date->format('mdY');
    }

// POST 
// patern = device id
// sdate = mm/dd/yyyy - based on asti api server, convert to yyyy-mm-dd before passing to getdata
// edate = sdate

    public function getdata()
    {
        $dev_id = $this->input->post('dev_id');
        $sdate = $this->input->post('sdate');
        $edate = $this->input->post('edate');

        if ($dev_id == false) {
            return;
        }
        if ($sdate == false) {
            $sdate = date('m/d/Y');
        }

        $sdate = DateTime::createFromFormat('m/d/Y', $sdate)->format('Y-m-d');

        if ($edate == false) {
            $edate = $sdate;
        }

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
