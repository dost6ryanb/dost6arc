<?php

require(APPPATH . 'libraries/REST_Controller.php');
use Restserver\Libraries\REST_Controller;

class Index extends REST_Controller {


		public function __construct()
        {
                parent::__construct();
                $this->load->helper('url');
                
                // Your own constructor code
        }

        public function index_get()
        {
              echo '<h2>Welcome to DOST VI ASTI Weather Data Archiving api<h2/>'
                    .'<h3>Options:</h3>'
                    .'<bold>'.site_url('api/predict_proxy').'</bold>'
                    .'<ul>'
                    .'<li><a href="'. base_url('api/predict_proxy/dev_id/25') . '">api/predict_proxy/dev_id/25</a></li>'
                    .'<li><a href="'. base_url('api/predict_proxy/dev_id/25/limit/1') . '">api/predict_proxy/dev_id/25/limit/1</a></li>'
                    .'</ul>'
                    .'<bold>'.site_url('api/devices').'</bold>'
                    .'<ul>'
                    .'<li><a href="'. base_url('api/devices') . '">api/devices</a></li>'
                    .'<li><a href="'. base_url('api/devices/dev_id/25') . '">api/devices/dev_id/25</a></li>'
                    .'</ul>'
                    .'<bold>'.site_url('api/archive/latest').'</bold>'
                    .'<ul>'
                    .'<li><a href="'. base_url('api/archive/latest') . '">api/archive</a></li>'
                    .'<li><a href="'. base_url('api/archive/latest/dev_id/25') . '">api/archive/dev_id/25</a></li>'
                    .'</ul>';

        }

        

        public function archive_post() {
            $dev_id = $this->post('pattern');
            if (!isset($dev_id) || !is_numeric($dev_id)) {
                redirect('api/index');
            }

            $sdate = $this->post('sdate');

            if (!isset($sdate)) {
               $sdate = (new DateTime())->format('m/d/Y');
            }

            $limit = $this->post('limit');
            if (!isset($limit) || !is_numeric($limit)) {
                $limit = 100;
            }

            $edate = $this->post('edate');

            if (!isset($edate)) {
               $edate =  $sdate;
            }



            $this->load->model('archive_model');

             // Fetch from archive then merge to a single json response mimicking fmon.asti predict format (see predict_model)
            $o_archives = $this->archive_model->get($dev_id, $limit, $sdate, $edate);
            $o_data_new = new stdClass();
            $o_data_new->data = [];
            $o_data_new->device = [];
            $o_data_new->device[] = array('dev_id'=>$dev_id);
             $o_data_new->count = 0;
            foreach ($o_archives as $o_archive) {
                $data_tmp = json_decode($o_archive->data);
                if (!empty($data_tmp)){
                    $o_data_new->data[] =  $data_tmp;
                    $o_data_new->count++;
                }
            } 


            $data['json'] = json_encode($o_data_new);
            $this->load->view('json_view', $data);
        }

        public function archive_all_post() {

            $sdate = $this->post('sdate');

            if (!isset($sdate)) {
               $sdate = (new DateTime())->format('m/d/Y');
            }

            $this->load->model('archive_model');

            $o_archives = $this->archive_model->get_all($sdate);
            $num = count($o_archives);

            if ($num > 0) {
                echo '['; // START JSON ARRAY
                $buffer = "";
                for ($i=0;$i<$num;$i++) {
                    $cur = $o_archives[$i];
                    $dev_id = $cur->dev_id;
                    $sdate_sql = $cur->sdate_sql;
                    $sdate = \DateTime::createFromFormat('Y-m-d',$sdate_sql)->format('m/d/Y');
                    $metadata = '"dev_id":"' . $dev_id . '", "sdate":"'.$sdate.'",';
                    $data = $cur->data;

                    $temp = substr_replace($data, $metadata, 1, 0);

                    $buffer .= $temp;

                    if ($i < $num - 1) { // IF NOT LAST ARRAY, PRINT COMMAS
                        $buffer .= ',';
                    }
                }
                echo $buffer;
                echo ']'; // END JSON ARRAY
            } else { // no data
                echo '[]';
            }
            $this->output->set_header('Content-type: application/json');
            
            return;
            $data['json'] = json_encode($o_archives);
            $this->load->view('json_view', $data);
        }

        public function devices_get() {
            $this->load->model('device_model');

            //GET LIST OF DEVICES,
            $o_devices;

            $dev_id = $this->get('dev_id');
            if (!isset($dev_id) || !is_numeric($dev_id)) {
                // SHOW ALL DEVICES
                $o_devices = $this->device_model->devices();
            } else {
                $o_devices = $this->device_model->devices($dev_id);
            }

            
            if ($o_devices != false) {
                echo '['; // START JSON ARRAY


                $num_of_devices = count($o_devices);

                for ($i=0;$i<$num_of_devices;$i++) {
                    $o_device = $o_devices[$i];

                    echo '{'; // START JSON PROPERTY
                    echo '"dev_id":"' . $o_device->dev_id . '",';
                    echo '"posx":"' . $o_device->posx . '",';
                    echo '"posy":"' . $o_device->posy . '"';
                    echo '"type_id":"' . $o_device->type_id . '"';
                    echo '}'; // END JSON PROPERTY

                    if ($i < $num_of_devices - 1) { // IF NOT LAST ARRAY, PRINT COMMAS
                        echo ',';
                    }
                }



                echo ']'; // END JSON ARRAY
            } else { // no device from db
                echo '[]';
            }

        }


}


?>