<?php
// +----------------------------------------------------------------------
// | BeauytSoft
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2012 http://beauty-soft.net All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: ceiba <kf@86055.com>
// +----------------------------------------------------------------------
defined('THINK_PATH') or exit();
class DbLogstore extends Db {
    protected $_dbName          =   ''; // dbName
    protected $_topic = '';
    /**
     * 架构函数 读取数据库配置信息
     * @access public
     * @param array $config 数据库配置数组
     */
    public function __construct($config = ''){
        $this->config = $config;
        $this->_topic  = C('SITE_AUTH_KEY');
    }
    
    //是否需要连接数据库
    public function needConnectDB($linkNum = 0){
        return !$this->connected;
    }
    
    /**
     * 连接数据库方法
     * @access public
     */
    public function connect($config = '', $linkNum = 0) {
        if ( !isset($this->linkID[$linkNum]) ) {
            if(empty($config))
                $config = $this->config;
                
                try{
                    $log_client = new Aliyun_Log_Client('http://'. $config['hostname'], 
                        $config['username'], $config['password'], '');
                    $this->_dbName = $config['database'];
                    $this->linkID[$linkNum] = $log_client;
                    $this->connected = true;
                } catch (\Exception $e) {
                    $this->connected = false;
                    $this->error = $e->getMessage();
                }
        }
        return $this->linkID[$linkNum];
    }
    
    private function parseQuery($options) {
        if (!isset($options['where']['__date__'][0]) 
            || strtolower($options['where']['__date__'][0]) != 'between'
            || !isset($options['where']['__date__'][1])
            || count($options['where']['__date__'][1]) != 2
            ) {// $cond["__date__"] = ['between', ['2018-02-04 00:00:00', '2018-02-08 00:00:00']];
                throw_exception("where error __date__ is must be between");
            }
        $where = '';    
        foreach ($options['where'] as $key => $val) {
            if (!is_string($key)) throw_exception("where key error");
            if ($key == '__date__') continue;
            
            if ($where != '') $where .= " and ";
            if (is_array($val) && isset($val[0]) && is_string($val[0]) && isset($val[1])) {
                $w = strtolower($val[0]);
                if ($w == 'between' && is_array($val[1]) && isset($val[1][0]) && isset($val[1][1])) {
                    //$cond['CityId'] = ['between', [1, 10]];
                    $where .= "$key>\"" . $val[1][0] . "\" and $key<\"" . $val[1][1] . '"';
                } else if ($w == 'in') {
                    if (is_array($val[1])) {
                        // $cond['CityId'] = array('IN', [12,33]);
                        $where .= "($key:" . implode(" or $key:", next($val)) . ')';
                    } else { 
                        //$cond['CityId'] = array('IN', "12,33");
                        $in = explode(',', $val[1]);
                        $where .= "($key:" . implode(" or $key:", $in) . ')';
                    }
                } else {
                    throw_exception("$key where error not support");
                }
            } else {//$cond["DeviceNumber"] = 'A00000000012';
                $where .= "$key:$val";
            }
        }
        return $where;
    }
    
    function count($options) { 
        try {
            if (!$this->connected ) {
                $this->initConnect();
            }
            $query = $this->parseQuery($options);
            $from = strtotime($options['where']['__date__'][1][0]);
            $to = strtotime($options['where']['__date__'][1][1]);
            $request = new Aliyun_Log_Models_GetHistogramsRequest($this->_dbName, 
                $options['table'], $from, $to, $this->_topic, $query);
        
       
            $response = $this->linkID[0]->getHistograms($request);
            $i = 0;
            while (!$response->isCompleted()) {
                $response = $this->linkID[0]->getHistograms($request);
                $i++;
                if ($i == 10) {
                    throw_exception("count error");
                }
            }
            return $response->getTotalCount();
        } catch (Exception $ex) {
            $this->error = $ex->getMessage();
            return false;
        }
    }
    
    public function select($options = Array()) {
        try {
            if (!$this->connected ) {
                $this->initConnect();
            }
            $query = $this->parseQuery($options);
            $from = strtotime($options['where']['__date__'][1][0]);
            $to = strtotime($options['where']['__date__'][1][1]);
            
            if (isset($options['limit']) && !empty($options['limit'])) {
                $limit = $this->parseLimit($options['limit']);
            } else {
                $limit = array("0" => 0, "1" => 20);
            }
            
            $order = (!empty($options['order']) && strpos(strtolower($options['order']), 'asc') !== false) == 'asc' ? false : true;
            
            $request = new Aliyun_Log_Models_GetLogsRequest($this->_dbName, $options['table'], $from, 
                $to, $this->_topic, $query, $limit[1], $limit[0], $order);
            
            $response = $this->linkID[0]->getLogs($request);
            $data = [];
            foreach($response->getLogs() as $log) {
                $item = [];
                $item['time'] = $log->getTime();
                foreach($log->getContents() as $key => $value){
                    if (strpos($key, '__') === 0) continue;
                    $item[$key] = $value;
                }
                $data[] = $item;
            }
            return $data;
        } catch (Exception $ex) {
            $this->error = $ex->getMessage();
            return false;
        }
    }
    
    public function batchAdd($options, $data) {
        try {
            if (!$this->connected ) {
                $this->initConnect();
            }
            $logitems = [];
            foreach ($data as $val) {
                $logItem = new Aliyun_Log_Models_LogItem();
                $logItem->setTime(time());
                $logItem->setContents($val);
                $logitems[] = $logItem;
            }
            $request = new Aliyun_Log_Models_PutLogsRequest($this->_dbName, $options['table'],
                C('SITE_AUTH_KEY'), Aliyun_Log_Util::getLocalIp(), $logitems);
            $response = $this->linkID[0]->putLogs($request);
            return true;
        } catch (Exception $ex) {
            $this->error = $ex->getMessage();
            return false;
        }
    }
    /**
     * 关闭数据库
     * @access public
     */
    public function close() {
        if($this->_linkID) {
            $this->_linkID = null;
        }
    }
    
    /**
     * limit分析
     * @access protected
     * @param mixed $limit
     * @return array
     */
    protected function parseLimit($limit) {
        if(strpos($limit,',')) {
            $array  =  explode(',',$limit);
        }else{
            $array   =  array(0,$limit);
        }
        return $array;
    }
    
    /**
     * field分析
     * @access protected
     * @param mixed $fields
     * @return array
     */
    public function parseField($fields){
        if (is_array($fields)){
            return $fields;
        }
        else{
            return $fields;
        }
    }
    /**
     * 取得数据表的字段信息
     * @access public
     * @return array
     */
    public function getFields($keyname=''){
        return [];
        N('db_query',1);
        if(C('DB_SQL_LOG')) {
            //$this->queryStr   =  $this->_dbName.'.'.$this->_collectionName.'.findOne()';
        }
        try{
            // 记录开始执行时间
            G('queryStartTime');
            $result   =  $this->_linkID->hkeys($this->_keyname);
            $this->debug();
        } catch (Exception $e) {
            throw_exception($e->getMessage());
        }
        if($result) { // 存在数据则分析字段
            $info =  array();
            foreach ($result as $key=>$val){
                $info[$key] =  array(
                    'name'=>$key,
                    'type'=>getType($val),
                );
            }
            return $info;
        }
        // 暂时没有数据 返回false
        return false;
    }
}
?>