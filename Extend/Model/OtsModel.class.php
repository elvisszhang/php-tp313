<?php
class OtsModel extends Model{
	private $ots_options = array();
	private $total = 0;
	public function __construct($tableName = '', $tablePrefix = '', $connection = '') {
	    if (empty($connection)) {
	        $connection = C('OTS_DSN');
	    }
	    if (empty($connection)) {
	        $this->error = "tablestore OTS_DSN not configured";
	        return;
	    }
	    if (empty($tableName)) {
	        $this->error = "tablename is empty";
	        return;
	    }
	    parent::__construct($tableName, $tablePrefix, $connection);
	}
	
	function _initialize(){
		
	}
    /**
     +----------------------------------------------------------
     * 利用__call方法实现一些特殊的Model方法
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $method 方法名称
     * @param array $args 调用参数
     +----------------------------------------------------------
     * @return mixed
     +----------------------------------------------------------
     */
    public function __call($method,$args) {   
        if(in_array(strtolower($method),array('column','order','limit', 'field'),true)) {
            // 连贯操作的实现
            $this->options[strtolower($method)] =   $args[0];
            $this->ots_options[strtolower($method)] =  $args[0];
            return $this;
        }else{
            throw_exception(__CLASS__.':'.$method.L('_METHOD_NOT_EXIST_'));
            return;
        }
    }
    
    public function count() {
        return $this->total;
    }
	public function select($options=array()) {
 		try{
 		    if (empty($this->db)) {
 		        return false;
 		    }
			$options =  $this->_parseOptions($this->ots_options);
			
			$limit = $options['limit'];
			$limit = strpos($limit, ',') ? explode(',',$limit)[1] : $limit;
			$pk = $this->page2TablestorePage('', intval(I("get.start", $_POST['start'])), $limit);
			if (!empty($pk)) {
			    $options['nextPk'] = $pk;
			} else {
				if (isset($_GET['start']))
					$_GET['start'] = 0;
				if (isset($_POST['start']))
					$_POST['start'] = 0;
			}
			$data = $this->db->select($options);

			if ($data != false)
			{
			     $this->total = $this->setPageCache('', intval(I("get.start", $_POST['start'])),
			    $limit, count($data['rows']), json_encode($data['curr_start_primary_key']), 
			    json_encode($data['next_start_primary_key']));
			}
			return $data['rows'];
			
 		}catch(ThinkException $e){
            $this->error = $e->getMessage();
			return false;
		}
	}
	public function add($data='',$options=array(),$replace=false){
	    return $this->batchAdd($data, $options);
	}
	
	/**
	 * 设置分页到tablestore的pk值
	 * @param string $cacheId
	 * @param int $start 页开始 start:0 length:15, start:15 length:15, start:30 length:15
	 * @param int $length 每页大小
	 * @param int $records 每页大小，最后一页长度可能不是length
	 * @param string $nextPk tablestore需要查询的当前页主键开始值
	 * @param string $nextPk tablestore需要查询的下一页主键开始值
	 * @return int 总记录数
	 */
	private function setPageCache($key, $start, $length, $records, $currentPk, $nextPk) {
	    if ($currentPk == "null") $currentPk = '';
	    if ($nextPk == "null") $nextPk = null;
	    if ($start == 0) $currentPk = '';//数据增加时 保证第一页是最新数据
	    
	    $order = !empty($this->ots_options['order']) && $this->ots_options['order'] == 'asc' ? 'asc' : 'desc';
	    $cacheId = I("get._guid", $_POST['_guid']) . $key . $order;
	    $data = S($cacheId);/*
	    ['length'=>$length, 'order'=>'asc|desc','nextPk' => ['k'=>, 'v'=>], 'total'=>$,'end'=>true|false, 'pages'=>[$start=>pk,$start=>pk,...]]
	    nextPk是最新一页的pk
	    
	    判断总数变化的情况                   第二页请求时，总数变化 怎么办
	    重新考虑总数变化的情况
	    */
	    $records = intval($records);
	    if ($data === false || $length != $data['length']) {
	        $data = ['length' => $length,  'total' => intval($records), 'end' => false];
	    } else {
	        if (!isset($data['pages'][$start])) {
	            
	            $data['total'] += $records;
	        } else {
	            if ($records > $data['total']) {//实时监控 长度实时变化 只考虑第一页增加的情况
	                $data['total'] = $records;
	            }
	            if ($data['pages'][$start] != $currentPk || (!isset($data['pages'][$start + $length]) && !empty($nextPk)) //最后一页空是不会写入pages里面的
	                || (isset($data['pages'][$start + $length]) && $data['pages'][$start + $length] != $nextPk)
	                ) {//总页数有变化 只考虑总数变多的情况 数据只是增加
	                    //$data['total'] = $start / $length * $length + $records;
	                    //$data['total'] =  $records;
	                    $data['end'] = false;
	            }
	        }
	    }
	    if (empty($nextPk)) {
	        $data['end'] = true;
	    }
	    $data['pages'][$start] = $currentPk;
	    $k = intval($start) + intval($length);
	    if (!isset($data['nextPk']) || $k >= $data['nextPk']['k'])
	        $data['nextPk'] = ['k' => $k, 'v' => $nextPk];
	        
	        S($cacheId, $data, 60 * 60 * 5);//5小时过期
	        
	        /*
	         * 知道总页数(已经点击过最后一页)    不需要加1
	         * 不知道总页数 有下一页需要加1 没有下一页不需要加1
	         */
	        if ($data['end']) {
	            return $data['total'];
	        } else {
	            return !empty($nextPk) ? $data['total'] + 1 : $data['total'];
	        }
	}
	/**
	 * 转换分页到tablesstore对应的pk值
	 * @param string $cacheId全局唯一
	 * @param int $start
	 * @param int $length
	 * @return boolean|[] false无值 数组[pk值, 已查询到的总的记录数]
	 */
	private function page2TablestorePage($key, $start, $length) {
	    
	    $order = !empty($this->ots_options['order']) && $this->ots_options['order'] == 'asc' ? 'asc' : 'desc';
	    $cacheId =I("get._guid", $_POST['_guid']) . $key . $order;
	    $data = S($cacheId);
	    if ($data !== false && $length == $data['length']) {
	        if (isset($data['pages'][$start])) {
	            return $data['pages'][$start];
	        }
	        if (!empty($data['nextPk']['v']) && $data['nextPk']['k'] == $start) {
                return $data['nextPk']['v'];
            }
	    }
	    return  false;
	}

	public function batchAdd($data='',$options=array(),$replace=false){
		try{
		    if (empty($this->db)) {
		        return false;
		    }
		    if (empty($data)) {
		        throw_exception("data is empty");
		    }
		    if (count($data) == count($data, 1)) {//一维数组
		        $data = array($data);
		    }
		    
			$options =  $this->_parseOptions($this->ots_options);
			$ret = $this->db->batchAdd($options,$data);
			return $ret;
		}catch(ThinkException $e){
		    $this->error = $e->getMessage();
		    return false;
		}
    }
	//删除键
	/*public function delete(){
		try{
			// 分析表达式
			$options =  $this->_parseOptions($this->ots_options);
			$this->db->delete($options);
			return true;
		}catch(ThinkException $e){
			return false;
		}
    }*/
}

?>