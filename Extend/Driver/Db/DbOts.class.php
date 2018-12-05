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
class DbOts extends Db{
    protected $_tablename      =   null; // Redis Key
    protected $_dbName          =   ''; // dbName
    protected $_cursor          =   null; // Reids Cursor Object
    /**
     * 架构函数 读取数据库配置信息
     * @access public
     * @param array $config 数据库配置数组
     */
    public function __construct($config=''){
        //require (__DIR__ . "../../../../vendor/autoload.php");//debug
        vendor("OTS211/Autoloader");
        $this->config = $config;
    }
    
    //是否需要连接数据库
    public function needConnectDB($linkNum=0){
        return !$this->connected;
    }
    
    /**
     * 连接数据库方法
     * @access public
     */
    public function connect($config='',$linkNum=0) {
        if ( !isset($this->linkID[$linkNum]) ) {
            if(empty($config))
                $config = $this->config;
                
                try{
                    $ots_client = new \Aliyun\OTS\OTSClient(array(
                        'EndPoint' => 'http://'. $config['hostname'],
                        'AccessKeyID' => $config['username'],
                        'AccessKeySecret' => $config['password'],
                        'InstanceName' => $config['database'],
                        // 以下是可选参数
                        'ConnectionTimeout' => 2.0, // 与OTS建立连接的最大延时，默认 2.0秒
                        'SocketTimeout' => 2.0, // 每次请求响应最大延时，默认2.0秒
                        // Error级别日志处理函数，用来打印OTS服务端返回错误时的日志
                        // 如果设置为null则为关闭log
                        'ErrorLogHandler' => function ($message) {
                        //错误都会throw,这段不需要了
                        },
                        // Debug级别日志处理函数，用来打印正常的请求和响应信息
                        // 如果设置为null则为关闭log
                        'DebugLogHandler' => function ($message) {
                        }
                        ));
                    $this->linkID[$linkNum] = $ots_client;
                    $this->connected = true;
                } catch (\Exception $e) {
                    $this->connected = false;
                    $this->error = $e->getMessage();
                }
        }
        return $this->linkID[$linkNum];
    }
    /**
     * 释放查询结果
     * @access public
     */
    public function free() {
        $this->_cursor = null;
    }
    /**
     * 关闭数据库
     * @access public
     */
    public function close() {
        if($this->_linkID) {
            $this->_linkID = null;
            $this->_ots = null;
            $this->_keyname =  null;
            $this->_cursor = null;
        }
    }
    private $writeError = '';
    private function processWriteError($response, $data) {
        $hasError = 0;
        // 处理返回的每个表
        $k = -1;
        foreach ($response['tables'] as $tableData) {
            $k++;
            // 处理这个表下的PutRow返回的结果
            $putRows = $tableData['put_rows'];
            foreach ($putRows as $rowData) {
                if ($rowData['is_ok']) {
                    continue;
                }
                $hasError++;
                $data[$k]['error'] = $rowData['error']['message'];
                $this->writeError .= json_encode($data[$k]);
            }
        }
        return count($data) - $hasError;
    }
    //返回成功个数 , 可能会部分成功
    public function batchAdd($options, $data) {
        try {
            if (!$this->connected ) {
                $this->initConnect();
            }
            $this->writeError = '';
            $dbStructure = require(CONF_PATH . 'db_structure.php');
            $structure = $dbStructure[$options['model']]['field_list'];
            $primaryKey = [];
            foreach ($structure as $key => $val) {
                if ($val['IsPrimaryKey']) {
                    $primaryKey[$key] = $val['FieldType'];
                }
            }
            if (count($primaryKey) < 2) {
                throw_exception("db_structure not configured");
            }
            
            
            $request = array(
                'tables' => array(
                    array(
                        'table_name' => $options['model'],
                    )
                )
            );
            
            $m = 0;
            $successNum = 0;
            foreach ($data as $value) {
                
                foreach ($primaryKey as $k => $v) {
                    if (!isset($value[$k])) {
                        throw_exception("primaryKey " . $k . " not exist");
                    }
                    $type = gettype($value[$k]);
                    if ($v != $type) {
                        throw_exception("primaryKey " . $k . " type must be " . $v);
                    }
                    $item['primary_key'][$k] = $value[$k];
                    unset($value[$k]);
                }
                
                if (!empty($value['condition'])) {
                    $item['condition'] = $value['condition'];
                } else {
                    $item['condition'] = \Aliyun\OTS\RowExistenceExpectationConst::CONST_EXPECT_NOT_EXIST;//主键不存在时添加，存在出错
                }
                
                if (count($value) > 0) {
                    $item['attribute_columns'] = $value;
                }
                $request['tables'][0]['put_rows'][] = $item;
                $m++;
                if ($m != 200) {//最多200条数据
                    continue;
                }
                $m = 0;
                $response = $this->linkID[0]->batchWriteRow($request);
                $successNum += $this->processWriteError($response, $data);
                unset($request['tables'][0]['put_rows']);
            }
            
            
            if ($m != 0) {
                $response = $this->linkID[0]->batchWriteRow($request);
                $successNum + $this->processWriteError($response, $data);
            }
            if (!empty($this->writeError)) {
                $this->error = $this->writeError;
            }
            return $successNum;
        } catch (Exception $e) {
            
            $this->error = $e->getMessage();
            return false;
            
        }
    }
    /**
     * 查找记录
     * @access public
     * @param array $options 表达式
     * @return iterator
     */
    public function select($options = array()) {
        try{
            if (!$this->connected ) {
                $this->initConnect();
            }
            if (empty($options['where'])) {
                throw_exception("where is empty");
            }
            
            //解析 limit
            if (isset($options['limit']) && !empty($options['limit'])){
                $limit = $this->parseLimit($options['limit']);
            } else {
                $limit = array("0" => 0, "1" => 20);
            }
            $order = !empty($options['order']) && $options['order'] == 'asc' ? 'asc' : 'desc';
            
            //组装 $startPK $endPK
            list($startPK, $endPK) = $this->parsePrimaryKey($options, $order);
            
            //组装 $column_filter
            $columnFilter = null;
            if (!empty($options['where']['_column_filter']))
                $columnFilter = $this->parseColumnFilter($options['where']['_column_filter']);
                
                //获取排序
                $direction = $order == 'asc' ? \Aliyun\OTS\DirectionConst::CONST_FORWARD :
                \Aliyun\OTS\DirectionConst::CONST_BACKWARD;
                
                //组装ots查询request
                $request = array(
                    'table_name' => $options['model'],
                    // FORWARD  升序 inclusive_start_primary_key < exclusive_end_primary_key(不变 最大)
                    // BACKWARD 降序 inclusive_start_primary_key > exclusive_end_primary_key(不变 最小)
                    'direction' => $direction,
                    'inclusive_start_primary_key' => $startPK,
                    'exclusive_end_primary_key' => $endPK,
                    'limit' => $limit[1]
                );
                if ($columnFilter) {
                    $request['column_filter'] = $columnFilter;
                }
                
                if (!empty($options['field'])) {
                    if (!is_array($options['field'])) {
                        $options['field'] = explode(",", $options['field']);
                    }
                    $request['columns_to_get'] = $options['field'];
                }
                if (!empty($options['column'])) {
                    if (!is_array($options['column'])) {
                        $options['column'] = explode(",", $options['column']);
                    }
                    $request['columns_to_get'] = $options['column'];
                }
                
                //echo 'request:' . json_encode($request);
                $response = $this->linkID[0]->getRange($request);
                //echo '<br/>reponse:' . json_encode($response);
                unset($response['consumed']);
                $ret = [];
                
                $ret['next_start_primary_key'] = null;
                $ret['curr_start_primary_key'] = $startPK;
                if (!empty($response['next_start_primary_key']) && !empty($response['rows'])) {
                    $ret['next_start_primary_key'] = $response['next_start_primary_key'];
                }
                
                
                foreach ($response['rows'] as $val) {
                    $ret['rows'][] = array_merge($val['primary_key_columns'],
                        $val['attribute_columns']);
                }
                if (!isset($ret['rows'])) {
                    $ret['rows'] = [];
                }
                return $ret;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }
    
    function parseLogicSymbol($symbol, $defaultVaue = null)
    {
        switch (strtolower($symbol))
        {
            case 'gt':
                return Aliyun\OTS\ComparatorTypeConst::CONST_GREATER_THAN;
            case 'egt':
                return Aliyun\OTS\ComparatorTypeConst::CONST_GREATER_EQUAL;
            case 'lt':
                return Aliyun\OTS\ComparatorTypeConst::CONST_LESS_THAN;
            case 'elt':
                return Aliyun\OTS\ComparatorTypeConst::CONST_LESS_EQUAL;
                
            case 'neq':
                return Aliyun\OTS\ComparatorTypeConst::CONST_NOT_EQUAL;
            case 'eq':
                return Aliyun\OTS\ComparatorTypeConst::CONST_EQUAL;
            case 'or':
                return Aliyun\OTS\LogicalOperatorConst::CONST_OR;
			case 'in':
                return Aliyun\OTS\LogicalOperatorConst::CONST_OR;
            case 'and':
                return Aliyun\OTS\LogicalOperatorConst::CONST_AND;
            case 'INF_MIN':
                return ['type' => \Aliyun\OTS\ColumnTypeConst::CONST_INF_MIN];
            case 'INF_MAX':
                return ['type' => \Aliyun\OTS\ColumnTypeConst::CONST_INF_MAX];
            default:
                if ($defaultVaue !== null)
                    return $defaultVaue;
                    throw_exception("temporarily not supported symbol " . $symbol);
        }
    }
    protected function parsePrimaryKey($options, $order) {//可以添加主键列字段类型判断
        $where = $options['where'];
        if (!is_array($where)) {
            throw_exception("where only supported between");
        }
        $startPK = array();
        $endPK = array();
        foreach ($where as $key=>$row){
            if($key == '_column_filter') {
                continue;
            }
            if (!is_array($row)) {
                $row = ['between', [$row, $row]];
            }
            if (!is_array($row) || count($row) != 2
                || !is_array($row[1]) || count($row[1]) != 2
                || $row[0] != 'between') {
                    throw_exception("where only supported between");
                }
                $startPK[$key] = $this->parseLogicSymbol($row[1][0], $row[1][0]);
                $endPK[$key] = $this->parseLogicSymbol($row[1][1], $row[1][1]);;
        }
        if (isset($options['nextPk']) && !empty($options['nextPk'])) {
            $order == 'asc' ? $startPK = json_decode($options['nextPk'], true)
            : $endPK = json_decode($options['nextPk'], true);
        }
        if ($order == 'asc')
            return array($startPK, $endPK);
            else
                return array($endPK, $startPK);
    }
    
    /*
		$columnFilter查询格式示例：
		$columnFilter = array( 'CityId'  => array('eq','1')  );
		$columnFilter = array( 'CityId'  => array('in','1,2')  );
		$columnFilter = array( 'CityId'  => array('in',array('1','2'))   );
	*/
    private function parseColumnFilter($columnFilter)
    {
        if (!is_array($columnFilter))
			throw_exception("_column_filter must be array");
		$cf = [];
		if (1 == count($columnFilter)) {//单列
			$tp_cond = current($columnFilter);
			if (count($tp_cond) != 2) {
				throw_exception("_column_filter value must be array1");
			}
			//单列多个值
			if($tp_cond[0]=='in'){
				$tmp = $tp_cond[1];
				if(!is_array($tmp)){
					$tmp = explode(',',trim($tmp,','));
				}
				//echo count($tmp);exit;
				//var_dump($tmp);exit;
				if (count($tmp)>1){
					$cf['logical_operator'] =  $this->parseLogicSymbol($tp_cond[0]);
					foreach ($columnFilter as $key => $value) {
						if ($key === '_logic') continue;
						if (count($value) != 2) {
							throw_exception("_column_filter param format error!!");
						}
						$v_list = $value[1];
						if(!is_array($v_list)){
							$v_list = explode(',',trim($v_list,','));
						}
						foreach($v_list as $v){
							$cf['sub_conditions'][] = array(
								'column_name' => $key,
								'value' => $v,
								'comparator' => $this->parseLogicSymbol('eq')
							);
						}
						
					}//var_dump($cf);exit;
				} else{
					if(count($tmp)!=1){
						throw_exception("_column_filter param error");
					}
					$cf['column_name'] = key($columnFilter);
					$cf['value'] = $tmp[0];
					$cf['comparator'] = $this->parseLogicSymbol('eq');
				}
				
			}else{
				$cf['column_name'] = key($columnFilter);
				$cf['value'] = $tp_cond[1];
				$cf['comparator'] = $this->parseLogicSymbol($tp_cond[0]);
			}
			
		} else {
			if (empty($columnFilter['_logic'])) {
				$columnFilter['_logic'] = 'and';
			}
			$cf['logical_operator'] =  $this->parseLogicSymbol($columnFilter['_logic']);
			
			foreach ($columnFilter as $key => $value) {
				if ($key === '_logic') continue;					
				$key = key($value);
				$value = current($value);
				if (count($value) != 2) {
					throw_exception("_column_filter value must be array");
				}
				$cf['sub_conditions'][] = array(
					'column_name' => $key,
					'value' => $value[1],
					'comparator' => $this->parseLogicSymbol($value[0]),
					'pass_if_missing' => false
				);
				
			}
		}
		
		return $cf;
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