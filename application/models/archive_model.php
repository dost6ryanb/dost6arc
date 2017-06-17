<?php
if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Archive_model extends CI_Model
{
    private $tableName = "archive";

    public function __construct()
    {
        $this->load->database();
    }

    public function install()
    {
        $this->load->dbforge();

        $fields = array(
        'dev_id' => array(
        'type' => 'INT'
        )/*,
        'sdate' => array(
            'type' => 'VARCHAR(10)'
        )*/
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
        

        $this->dbforge->create_table($this->tableName, true);
        $this->db->query("ALTER TABLE $this->tableName ADD UNIQUE INDEX (dev_id, sdate_sql, datetimeread)");
    }

    public function start_trans()
    {
        $this->db->trans_start();
    }

    public function end_trans()
    {
        $this->db->trans_complete();
    }

    public function trans_status() {
        return $this->db->trans_status();
    }

    public function trans_begin() {
         $this->db->trans_begin();
    }

    public function trans_commit() {
        $this->db->trans_commit();
    }

    public function trans_rollback() {
        $this->db->trans_rollback();
    }

    public function create($dev_id, $sdate, $datetimeread, $device_data)
    {
        if ($this->isexist($dev_id, $sdate, $datetimeread)) {
            return false;
        } else {
            $sdate_sql = $this->_predict_to_mysql_date_str($sdate);
            $data = array(
            'dev_id' => $dev_id,
            // 'sdate' => $sdate,
            'datetimeread' => $datetimeread,
            'sdate_sql' => $sdate_sql,
            'data' => $device_data
            );

            $this->db->insert($this->tableName, $data);

            if ($this->db->affected_rows() > 0) {
                return true;
            } else {
                return false;
            }
        }
    }

    public function isexist($dev_id, $sdate, $datetimeread = '')
    {
        return false;
    }

    //GET daily data for the specific dev_id and start date to end date
    public function get($dev_id, $sdate, $edate, $limit, $summary = false)
    {
        $this->db->from($this->tableName);
        $this->db->where('dev_id', $dev_id);

        if ($sdate === $edate || empty($edate)) {
            // $this->db->where("sdate", $sdate);
            $sqldate_str = self::to_mysql_date_str($sdate);
            $this->db->where("sdate_sql", $sqldate_str);
        } else {
            $sqlsdate_str = self::to_mysql_date_str($sdate);
            $sqledate_str = self::to_mysql_date_str($edate);
            $this->db->where("sdate_sql BETWEEN '". $sqlsdate_str. "' AND '". $sqledate_str."'");
        }
        
        // $this->db->order_by('datetimeread', 'desc');
        $this->db->order_by('sdate_sql desc, UNIX_TIMESTAMP(datetimeread) desc');
        if (is_numeric($limit)) {
            $this->db->limit($limit);
        }

        $query = $this->db->get();
        $result = $query->result();
        if ($summary == 'lastdata') {
             return $this->extract_json_data_lastdata($result);
        } elseif ($summary == 'daily') {
            return $this->extract_json_data_daily($result);
        } elseif ($summary == 'monthly') {
            return $this->extract_json_data_monthly($result);
        } else {
            return $this->extract_json_data($result);
        }
    }

    //GET latest data (single) for all devices for a given date
    public function getLatestAll($sdate)
    {
        $sqldate = self::to_mysql_date_str($sdate);

        $this->db->select("dev_id, max(datetimeread) as maxdtr")
        ->from($this->tableName)
        ->where('sdate_sql', $sqldate)
        ->group_by('dev_id', 'sdate_sql');
        $subquery = $this->db->get_compiled_select();

        $this->db->reset_query();

        $this->db->select("*")
        ->from("$this->tableName as a")
        ->where('a.sdate_sql', $sqldate)
        ->where('a.datetimeread <>', "0000-00-00 00:00:00")
        ->join("($subquery) as b", 'a.dev_id = b.dev_id and a.datetimeread = b.maxdtr', 'inner');

        $query = $this->db->get();
        $result = $query->result();
        return $this->extract_json_data_addmeta($result);
    }
    
    //GET the latest daily data for a specific dev_id
    public function getLatest($dev_id, $limit = false)
    {
        $this->db->select('dev_id, max(sdate_sql) as maxdate')
        ->from($this->tableName)
        ->where('dev_id', $dev_id);
        $subq = $this->db->get_compiled_select();

        $this->db->reset_query();

        $this->db->select('a.*')
        ->from("$this->tableName as a")
        ->join("($subq) as b", 'a.dev_id= b.dev_id and a.sdate_sql = b.maxdate')
        ->order_by('datetimeread', 'desc');

        if (is_numeric($limit) && ($limit >= 0)) {
            $this->db->limit($limit);
        }

        $query = $this->db->get();
        $result = $query->result();
        return $this->extract_json_data($result);
    }


    private function extract_json_data($o)
    {
        $data_count = count($o);

        if ($data_count == 0) { //no archive
            return;
        }

        $buffer;
        for ($i=0; $i < $data_count; $i++) {
            $temp = json_decode($o[$i]->data);
            $buffer[] = $temp;
        }
        return $buffer;
    }

    private function extract_json_data_addmeta($o)
    {
        $data_count = count($o);
        $buffer;
        for ($i=0; $i < $data_count; $i++) {
            $temp = json_decode($o[$i]->data);
            if (empty($temp)) {
                $temp = new StdClass();
            }
            $temp->dev_id = $o[$i]->dev_id;
            $temp->sdate = $o[$i]->sdate_sql;

            $buffer[] = $temp;
        }
        return $buffer;
    }

    private function extract_json_data_lastdata($o)
    {
        $data_count = count($o);
        $buffer;

         
        if ($data_count == 0) { //no archive
            return;
        }

        $lastindex = $data_count - 1;
        $last_date;
        $last_json;
        for ($i=0; $i < $data_count; $i++) {
            $current_date = $o[$i]->sdate_sql;
            $json = json_decode($o[$i]->data);

            if ($i == 0) {
                $last_date = $current_date;
                $last_json = $json;
            }

            if ($last_date == $current_date) {
                //do nothing
            } else { // new date
                //writetobuffer
                $this->writetobuffer_lastdata($buffer, $last_date, $last_json);
                //reset last date to curent date
                $last_date = $current_date;
                //reset customfunc
                $last_json = $json;
            }

            if ($i == $lastindex) { //is last index
                //writetobuffer
                $this->writetobuffer_lastdata($buffer, $last_date, $last_json);
            }
        }
        return $buffer;
    }

    private function extract_json_data_daily($o)
    {
        $data_count = count($o);
        $buffer;
        $last_date;

        //other params custom
        $rain_total = 0.0;
        if ($data_count == 0) { //no archive
            //todo
            return;
        }

        $lastindex = $data_count - 1;
        for ($i=0; $i < $data_count; $i++) {
            $current_date = $o[$i]->sdate_sql;
            $json = json_decode($o[$i]->data);

            if ($i == 0) {
                $last_date = $current_date;
            }

            if (isset($json->rain_value)) {
                $current_rain = floatval($json->rain_value);
            } else {
                $current_rain = 0.0;
            }

            if ($last_date == $current_date) {
                //customfunc
                $rain_total += $current_rain;
            } else { // new date
                //writetobuffer
                $this->writetobuffer_rain($buffer, $last_date, $rain_total);
                //reset last date to curent date
                 $last_date = $current_date;
                //reset customfunc
                $rain_total = $current_rain;
            }

            if ($i == $lastindex) { //is last index
                //writetobuffer
                $this->writetobuffer_rain($buffer, $last_date, $rain_total);
            }
        }

        return $buffer;
    }

    private function extract_json_data_monthly($o)
    {
        $data_count = count($o);
        $buffer;
        $last_date_month;

        $rain_total = 0.0;
        if ($data_count == 0) {
            return;
        }

        $lastindex = $data_count - 1;

        for ($i=0; $i<$data_count; $i++) {
            $current_date_month = substr($o[$i]->sdate_sql, 0, 7);
            $json = json_decode($o[$i]->data);

            if ($i == 0) {
                $last_date_month = $current_date_month;
            }

            if (isset($json->rain_value)) {
                $current_rain = floatval($json->rain_value);
            } else {
                $current_rain = 0.0;
            }

            if ($last_date_month == $current_date_month) {
                $rain_total += $current_rain;
            } else {
                $this->writetobuffer_rain($buffer, $last_date_month, $rain_total);

                $last_date_month = $current_date_month;

                $rain_total = $current_rain;
            }

            if ($i == $lastindex) { //is last index
                //writetobuffer
                $this->writetobuffer_rain($buffer, $last_date_month, $rain_total);
            }
        }

          return $buffer;
    }

    private function writetobuffer_lastdata(& $b, $date, $json)
    {
        if (!empty($json)) {
            $b[] = $json;
        } else {
            //return something useful when $json is empty like a date or something
            $b[] = array("dateTimeRead" => $date);
        }
    }

    private function writetobuffer_rain(& $b, $date, $rain)
    {
        $b[] = array(
            "date" => $date,
            'total_rain' => round($rain, 2)
        );
    }

    private function to_mysql_date_str($date)
    {
        if ($date) {
            return  $date->format('Y-m-d');
        } else {
            $now = new \DateTime();
            return $now->format('Y-m-d');
        }
    }

    //TODO Remove uses replace with to_mysql_date_str
    private function _predict_to_mysql_date_str($date)
    {
        $sqldate = \DateTime::createFromFormat('m/d/Y', $date);
        if ($sqldate) {
            return  $sqldate->format('Y-m-d');
        } else {
            $now = new \DateTime();
            return $now->format('Y-m-d');
        }
    }
}
