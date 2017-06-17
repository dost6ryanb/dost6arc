<?php
require(APPPATH . 'libraries/REST_Controller.php');

use Restserver\Libraries\REST_Controller;

class Predict extends REST_Controller
{
    
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url');
    }
    
    public function index_get()
    {
        $this->load->library('parser');
        
        $data = array(
        'title' => 'Predict',
        'url' => 'api/predict',
        'links' => array(
        array('title' => 'Get device info', 'url' => 'api/predict/device/:dev_id', 'href' => base_url('api/predict/device/:dev_id'))
        )
        );
        
        $this->parser->parse('api_template', $data);
    }
    
    public function device_get($dev_id=false)
    {

        if (($dev_id == false) || !is_numeric($dev_id)) {
            $this->response([
            'success' => false,
            'error_message' => 'dev_id required'
            ], REST_Controller::HTTP_BAD_REQUEST);
            return;
        }
        
        $this->load->model('predict_model');
        $output = $this->predict_model->get_device_info($dev_id);
        
        $data['json'] = $output;
        //$this->load->view('json_view', $data);
        $this->set_response($data['json'], REST_Controller::HTTP_OK);
    }
    
    //accessible thru POST, proxy to http://fmon.asti.dost.gov.ph/weather/home/index.php/device/getData/
        //USES class predict_model, function get_device_data
    public function getData_post()
    {

        $dev_id = $this->post('dev_id');
        if (!isset($dev_id) || !is_numeric($dev_id)) {
            //redirect('api/index');
            $this->response([
                'status' => false,
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
}
