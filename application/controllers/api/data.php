<?php
require(APPPATH . 'libraries/REST_Controller.php');

use Restserver\Libraries\REST_Controller;

/*

//GET latest data
//@param dev_id
select * from archive a inner join (SELECT dev_id, max(datetimeread) as maxdate FROM `archive` WHERE dev_id=27) b on a.dev_id = b.dev_id and a.datetimeread = b.maxdate

//GET Latest daily data expanded
select * from archive a
inner join
(SELECT dev_id, max(sdate_sql) as maxdate FROM `archive` WHERE dev_id=27) b
on a.dev_id = b.dev_id and a.sdate_sql = b.maxdate
order by datetimeread desc
limit ?

//GET data by dev_id & date

*/
class Data extends REST_Controller
{
    
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url');
        $this->load->library('parser');
    }
    
    public function help_get()
    {
        $data = array(
        'title' => 'Data',
        'url' => 'api/data',
        'links' => array(
        array('title' => 'GET api/data', 'url' => 'api/data/:dev_id[?start=:start][&end=:end]', 'href' => base_url('api/data')),
        )
        );
        
        $this->parser->parse('api_template', $data);
    }

    /*
    //pathParams data/:dev_id/:sdate[dd-mm-yyyy]/:edate[dd-mm-yyyy]
    //
    //query
    // ?limit
    // ?summary=[lastdata|daily|monthly|yearly]

    */
    public function index_get($dev_id = false, $sdate_str = false, $edate_str = false)
    {
        if (($dev_id === false) || !is_numeric($dev_id)) {
            $this->response([
            'success' => false,
            'error_message' => 'dev_id required'
            ], REST_Controller::HTTP_BAD_REQUEST);
            return;
        }
        if (($sdate_str === false)) {
            $this->response([
            'success' => false,
            'error_message' => 'sdate required'
            ], REST_Controller::HTTP_BAD_REQUEST);
            return;
        }

        $sdate;
        try {
             $sdate = new \DateTime($sdate_str);
        } catch (Exception $e) {
            $this->response([
            'success' => false,
            'error_message' => $e->getMessage()
            ], REST_Controller::HTTP_BAD_REQUEST);
            return;
        }

        $edate;
        if ($edate_str === false) {
            $edate = false;
        } else {
            try {
                $edate = new \DateTime($edate_str);
            } catch (Exception $e) {
                $this->response([
                'success' => false,
                'error_message' => $e->getMessage()
                ], REST_Controller::HTTP_BAD_REQUEST);
                return;
            }
        }
        

        $limit = $this->get('limit');
        $summary = $this->get('summary');
        
        $this->load->model('archive_model');
        $model = $this->archive_model->get($dev_id, $sdate, $edate, $limit, $summary);
        

        $output = array(
            'dev_id'=>$dev_id,
            'sdate' => $sdate_str, //TODO remove or not
            'edate' => $edate_str, //TODO remove or not
            'limit' => $limit,
            'summary' => $summary,
            'data'=>$model,
            'count'=>count($model)
        );

        $this->response($output, 200);
    }

    public function csv_get($dev_id = false, $sdate_str = false, $edate_str = false)
    {
        if (($dev_id === false) || !is_numeric($dev_id)) {
            $this->response([
            'success' => false,
            'error_message' => 'dev_id required'
            ], REST_Controller::HTTP_BAD_REQUEST);
            return;
        }
        if (($sdate_str === false)) {
            $this->response([
            'success' => false,
            'error_message' => 'sdate required'
            ], REST_Controller::HTTP_BAD_REQUEST);
            return;
        }

        $sdate;
        try {
             $sdate = new \DateTime($sdate_str);
        } catch (Exception $e) {
            $this->response([
            'success' => false,
            'error_message' => $e->getMessage()
            ], REST_Controller::HTTP_BAD_REQUEST);
            return;
        }

        $edate;
        if ($edate_str === false) {
            $edate = false;
        } else {
            try {
                $edate = new \DateTime($edate_str);
            } catch (Exception $e) {
                $this->response([
                'success' => false,
                'error_message' => $e->getMessage()
                ], REST_Controller::HTTP_BAD_REQUEST);
                return;
            }
        }
        

        $limit = $this->get('limit');
        $summary = $this->get('summary');
        
        $this->load->model('archive_model');
        $model = $this->archive_model->get($dev_id, $sdate, $edate, $limit, $summary);

        $output = $model;
        $stream = fopen('php://output', 'w');
        for ($i=0;$i<count($output);++$i) {
            $dis = get_object_vars($output[$i]);
            if ($i == 0) {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename='."$dev_id-$sdate_str-$edate_str".'.csv');
                $tis = array_keys($dis);

                fputcsv($stream, $tis);
            }
            fputcsv($stream, $dis);
        }

    }

    
    public function latest_get($dev_id = false)
    {
        if (($dev_id === false) || !is_numeric($dev_id)) {
            $this->response([
            'success' => false,
            'error_message' => 'dev_id required'
            ], REST_Controller::HTTP_BAD_REQUEST);
            return;
        }

        $limit = $this->get('limit');

        $this->load->model('archive_model');
        $model = $this->archive_model->getLatest($dev_id, $limit);

        $output = array(
            'dev_id'=>$dev_id,
            'data'=>$model,
            'count'=>count($model)
        );
        $this->response($output, 200);
    }

    public function all_last_get($sdate_str=false) {
        $this->load->model('archive_model');

        if (($sdate_str === false)) {
            $this->response([
            'success' => false,
            'error_message' => 'sdate required'
            ], REST_Controller::HTTP_BAD_REQUEST);
            return;
        }

        $sdate;
        try {
             $sdate = new \DateTime($sdate_str);
        } catch (Exception $e) {
            $this->response([
            'success' => false,
            'error_message' => $e->getMessage()
            ], REST_Controller::HTTP_BAD_REQUEST);
            return;
        }

        $limit = $this->get('limit');

        $model = $this->archive_model->getLatestAll($sdate);

        $output = array(
            'data'=>$model,
            'count'=>count($model)
        );
        $this->response($output, 200);
    }

    public function index_post()
    {
        $dev_id = $this->input->post('pattern');
        $sdate = $this->input->post('sdate');
        $edate = $this->input->post('edate');
        $limit = $this->input->post('limit');

        if ($dev_id == false) {
            return;
        }
        if ($sdate == NULL) {
            $sdate = new \DateTime('NOW');
        } else {
            $sdate = DateTime::createFromFormat('m/d/Y', $sdate);
        }

        if ($edate == NULL) {
            $edate = $sdate;
        } else {
            $edate = DateTime::createFromFormat('m/d/Y', $edate);
        }

        $this->load->model('archive_model');
        $model = $this->archive_model->get($dev_id, $sdate, $edate, $limit);

        $output = array(
            'data'=>$model,
            'count'=>count($model),
            'device'=>[array('dev_id' => $dev_id)]
        );
        $this->response($output, 200);
    }


}
