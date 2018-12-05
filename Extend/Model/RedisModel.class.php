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
// php redis函数： http://www.cnblogs.com/weafer/archive/2011/09/21/2184059.html
class RedisModel extends Model{
	private $redis_options = array('prefix'=>'');
	function _initialize(){
		$this->autoCheckFields = false;
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
	 type: 数据结构类型
	 limit: 范围（不同数据结构定义不一样）
	 table: 表名
	 prefix:表前缀
	 size: 定长控制（queue,stack才有效）
	 range: 分值范围控制(zset才有效)
     */
    public function __call($method,$args) {    	
        if(in_array(strtolower($method),array('type','order','limit','page','table','prefix','size','range'),true)) {
            // 连贯操作的实现
            $this->options[strtolower($method)] =   $args[0];
            $this->redis_options[strtolower($method)] =  $args[0];
            return $this;
        }else{
            throw_exception(__CLASS__.':'.$method.L('_METHOD_NOT_EXIST_'));
            return;
        }
    }
	
    /**
     * 得到完整的数据表名
     * @access public
     * @return string
     */
    public function getTableName() {
        if(empty($this->trueTableName)) {
            $tableName  = !empty($this->redis_options['prefix']) ? $this->redis_options['prefix'] : '';
            if(!empty($this->tableName)) {
                $tableName .= $this->tableName;
            }else{
                $tableName .= parse_name($this->name);
            }
            $this->trueTableName    =   strtolower($tableName);
        }
        return (!empty($this->dbName)?$this->dbName.'.':'').$this->trueTableName;
    }
	
    /**
     +----------------------------------------------------------
     * count统计 配合where连贯操作
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @return integer
     +----------------------------------------------------------
     */
	public function drop($table){
 		try{
			if($table == '[all]')
				$this->db->flushDB();
			return true;
		}catch(ThinkException $e){
			return false;
		}
	}
	public function tables($match = '*'){
 		try{
			return $this->db->tables($match);
		}catch(ThinkException $e){
			return false;
		}
	}
	public function last($options=array()) {
        $options            =   $this->_parseOptions($this->redis_options);
        $options['limit']   =   '0,0';
        $resultSet          =   $this->db->select($options);
        if(false === $resultSet) {
            return false;
        }
        if(empty($resultSet)) {// 查询结果为空
            return null;
        }
        $this->data         =   $resultSet[0];
        $this->_after_find($this->data,$options);
        if(!empty($this->options['result'])) {
            return $this->returnResult($this->data,$this->options['result']);
        }
        return json_decode($this->data,true) ? : $this->data;
    }
    public function count(){
 		try{
			$options =  $this->_parseOptions($this->redis_options);
			return $this->db->count($options);
		}catch(ThinkException $e){
			return 0;
		}
    }
	public function select($options=array()) {
 		try{
			$options =  $this->_parseOptions($this->redis_options);
			return $this->db->select($options);
		}catch(ThinkException $e){
			return false;
		}
	}
	public function get($fields){
 		try{
			$options =  $this->_parseOptions($this->redis_options);
			return $this->db->get($options,$fields);
		}catch(ThinkException $e){
			return false;
		}
	}
	public function add($data='',$options=array(),$replace=false){
		try{
			$options =  $this->_parseOptions($this->redis_options);
			$this->db->add($options,$data);
			return true;
		}catch(ThinkException $e){
			return false;
		}
    }
	public function push($data){
		$this->add($data);
	}
	public function lpush($data){
		if(is_array($data))
			$data = json_encode($data);
		try{
			$options =  $this->_parseOptions($this->redis_options);
			$this->db->lpush($options,$data);
			return true;
		}catch(ThinkException $e){
			return false;
		}
	}
	public function rpush($data){
		if(is_array($data))
			$data = json_encode($data);
		try{
			$options =  $this->_parseOptions($this->redis_options);
			$this->db->rpush($options,$data);
			return true;
		}catch(ThinkException $e){
			return false;
		}
	}
	public function exists($key){
		try{
			// 分析表达式
			$options =  $this->_parseOptions($this->redis_options);
			return $this->db->exists($options,$key);
		}catch(ThinkException $e){
			return false;
		}
	}
	//获取分值(仅对zset有效)
	public function score($value){
		try{
			// 分析表达式
			$options =  $this->_parseOptions($this->redis_options);
			return $this->db->score($options,$value);
		}catch(ThinkException $e){
			return false;
		}
	}
	//获取最小值(仅对zset有效)
	public function min($opt = array()){
		try{
			// 分析表达式
			$options =  $this->_parseOptions($this->redis_options);
			$options = array_merge($options,$opt);
			return $this->db->min($options);
		}catch(ThinkException $e){
			return false;
		}
	}
	
	//获取最大值(仅对zset有效)
	public function max($opt = array()){
		try{
			// 分析表达式
			$options =  $this->_parseOptions($this->redis_options);
			$options = array_merge($options,$opt);
			return $this->db->max($options);
		}catch(ThinkException $e){
			return false;
		}
	}
	//删除键
	public function delete($way=""){
		try{
			// 分析表达式
			$options =  $this->_parseOptions($this->redis_options);
			$this->db->delete($options,$way);
			return true;
		}catch(ThinkException $e){
			return false;
		}
    }
	public function rnd($data){
        // 分析表达式
		try{
			$options =  $this->_parseOptions($this->redis_options);
			return $this->db->rnd($options,$data);
		}catch(ThinkException $e){
			return null;
		}
    }
	public function pop(){
        try{
			$options =  $this->_parseOptions($this->redis_options);
			$data = $this->db->pop($options);
			return json_decode($data,true) ? : $data;
		}catch(ThinkException $e){
			return null;
		}
	}
	public function lpop(){
		try{
			$options =  $this->_parseOptions($this->redis_options);
			$data = $this->db->lpop($options);
			return json_decode($data,true) ? : $data;
		}catch(ThinkException $e){
			return null;
		}
	}
	public function rpop(){
		try{
			$options =  $this->_parseOptions($this->redis_options);
			$data = $this->db->rpop($options);
			return json_decode($data,true) ? : $data;
		}catch(ThinkException $e){
			return null;
		}
	}
	public function incrBy($inc){
        // 分析表达式
		try{
			$options =  $this->_parseOptions($this->redis_options);
			return $this->db->incrBy($options,$inc);
		}catch(ThinkException $e){
			return null;
		}
	}
	public function info(){
		try{
			$options =  $this->_parseOptions($this->redis_options);
			$data = $this->db->info($options);
			return $data;
		}catch(ThinkException $e){
			return null;
		}
	}
}

?>