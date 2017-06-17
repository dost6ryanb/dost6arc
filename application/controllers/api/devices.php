<?php
require(APPPATH . 'libraries/REST_Controller.php');

use Restserver\Libraries\REST_Controller;

class Devices extends REST_Controller {
    
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url');
        $this->load->library('parser');
    }
    
    public function index_get() {
            $this->load->model('device_model');

            //GET LIST OF DEVICES,
            $o_devices;
            
            $dev_id = $this->get('dev_id');
           
            if (!isset($dev_id)) {
                // SHOW ALL DEVICES
                $o_devices = $this->device_model->devices();
            } elseif (is_numeric($dev_id)){
                $o_devices = $this->device_model->devices($dev_id);
            } else {
                $this->response(array(), 200);
                return;
            }

            
            if ($o_devices != false) {
                $num_of_devices = count($o_devices);

                $response = array(
                    'devices' => $o_devices,
                    'count' => $num_of_devices
                );

                $this->response($response, 200);
                 
            } else { // no device from db
                $this->response(array(), 200);
            }

        }

    public function test_get() {
        $this->response(array(
            'test' => 'HEllO WolRd!'
        ), 200);
    }
    
}