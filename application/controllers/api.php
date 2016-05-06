<?php

require(APPPATH . '/libraries/REST_Controller.php');

class Api extends REST_Controller {


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
                    .'<bold>'.site_url('api/arhive').'</bold>'
                    .'<ul>'
                    .'<li><a href="'. base_url('api/arhive') . '">api/arhive</a></li>'
                    .'<li><a href="'. base_url('api/arhive/dev_id/25') . '">api/arhive/dev_id/25</a></li>'
                    .'</ul>';

        }

        //accessible thru POST, proxy to http://fmon.asti.dost.gov.ph/weather/home/index.php/device/getData/
        //USES class predict_model, function get_device_data
        public function predict_post() {
            $dev_id = $this->post('dev_id');
            if (!isset($dev_id) || !is_numeric($dev_id)) {
                //redirect('api/index');
                $this->response([
                    'status' => FALSE,
                    'error' => 'dev_id required'
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            $limit = $this->post('limit');

            if (!isset($limit) || !is_numeric($limit)) {
               $limit = 100;
            } 

            $sdate = $this->post('sdate');

            if (!isset($sdate)) {
               $sdate = '';
            } 

            $edate = $this->post('edate');

            if (!isset($edate)) {
               $edate = $sdate;
            } 

            $this->load->model('predict_model');
            // SOME TEST
            $output = $this->predict_model->get_device_data($dev_id, $limit, $sdate, $edate);

            $data['json'] = $output;
            //$this->load->view('json_view', $data);
            $this->set_response($data['json'], REST_Controller::HTTP_OK);
        }

        public function predict_device_info_get() {
            $dev_id = $this->get('dev_id');
            if (!isset($dev_id) || !is_numeric($dev_id)) {
                //redirect('api/index');
                $this->response([
                    'status' => FALSE,
                    'error' => 'dev_id required'
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            $this->load->model('predict_model');
            $output = $this->predict_model->get_device_info($dev_id);

            $data['json'] = $output;
            //$this->load->view('json_view', $data);
            $this->set_response($data['json'], REST_Controller::HTTP_OK);
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
                $limit = false;
            }

            $edate = $this->post('edate');

            if (!isset($edate)) {
               $edate =  $sdate;
            }



            $this->load->model('archive_model');

             // SOME TEST
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