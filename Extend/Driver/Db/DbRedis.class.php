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
class DbRedis extends Db{
	protected $_redis           =   null; // Redis Object
    protected $_keyname      =   null; // Redis Key
    protected $_dbName          =   ''; // dbName
    protected $_cursor          =   null; // Reids Cursor Object
    /**
     * 架构函数 读取数据库配置信息
     * @access public
     * @param array $config 数据库配置数组
     */
    public function __construct($config=''){
        if ( !class_exists('redis') ) {
            throw_exception(L('_NOT_SUPPERT_').':redis');
        }  
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
            $redis = new Redis();  
			$redis->connect($config["hostname"],$config["hostport"]?:6379);  
			$redis->auth($config["password"]?: ""); 
			$redis->select($config['database'] ? : 0);
			$info=$redis->info();
            // 标记连接成功
            if (!empty($info["redis_version"])){
            	$this->linkID[$linkNum] = $redis;
            	$this->connected    =   true;
            }
			else{
				echo 'connect to redis failed';
				exit;
			}
        }
        return $this->linkID[$linkNum];
    }
    /**
     * 切换当前操作的Db和redis key
     * @access public
     * @param string $keyname  redis key
     * @param string $db  db
     * @param boolean $master 是否主服务器
     * @return void
     */
    public function switchKey($keyname,$db='',$master=true){
        // 当前没有连接 则首先进行数据库连接
        if ( !$this->_linkID ) 
			$this->initConnect($master);
        try{
            if(!empty($db)) { // 传入Db则切换数据库
                // 当前Db对象
                $this->_dbName  =  $db;
                $this->_redis = $this->_linkID->select($db);
            }
            // 当前MongoCollection对象
            if(C('DB_SQL_LOG')) {
                $this->queryStr   =  $this->_dbName.'.getKey('.$keyname.')';
            }
            if($this->_keyname != $keyname) {
                N('db_read',1);
                // 记录开始执行时间
                G('queryStartTime');
                $this->debug();
                $this->_keyname  = $keyname;
            }
        }catch (Exception $e){
            throw_exception($e->getMessage());
        }
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
            $this->_linkID->close();
            $this->_linkID = null;
            $this->_redis = null;
            $this->_keyname =  null;
            $this->_cursor = null;
        }
    }
    /**
     * 查找记录
     * @access public
     * @param array $options 表达式
     * @return iterator
     */
    public function select($options=array()) { 	
        if(isset($options['table'])) {
            $this->switchKey($options['prefix'] . $options['table'],'',false);
        }
        $cache  =  isset($options['cache'])?$options['cache']:false;
        if($cache) { // 查询缓存检测
            $key =  is_string($cache['key'])?$cache['key']:md5(serialize($options));
            $value   =  S($key,'','',$cache['type']);
            if(false !== $value) {
                return $value;
            }
        }
        $this->model  =   $options['model'];
        N('db_query',1);
        //$query  =  $this->parseWhere($options['where']);
        $field =  $this->parseField($options['field']);
        try{
            if(C('DB_SQL_LOG')) {
                $this->queryStr   =  $this->_dbName.'查询出错:'.$field;
            }
            // 记录开始执行时间
            G('queryStartTime');

        	if ($options['limit']){
                $limit=$this->parseLimit($options['limit']);
            }else{
            	$limit=array("0"=>0,"1"=>20);
            }
            if($options['type']) {
                if ($options["type"]==strtolower("list")){
                	$_cursor   = $this->_linkID->lRange($this->_keyname, $limit[0],$limit[1]);
                }
				else if ($options["type"]==strtolower("queue")){
                	$_cursor   = $this->_linkID->lRange($this->_keyname, $limit[0],$limit[1]);
                }
				else if ($options["type"]==strtolower("stack")){
                	$_cursor   = $this->_linkID->lRange($this->_keyname, $limit[0],$limit[1]);
                }
				elseif ($options["type"]==strtolower("set")){
                	//集合
                	
                	switch (strtolower($options["where"])) {
                		case "sinterstore":
                			//求交集
                			$_cursor   = $this->_linkID->sInter($field);	                		
                			break;
                		case "sunion":
                			//求并集
                			$_cursor   = $this->_linkID->sUnion($field);
                			break;
                		case "sdiff":
                			//求差值
                			$_cursor   = $this->_linkID->sDiff($field);
                			break;
                		default:
                			$_cursor   = $this->_linkID->sMembers($this->_keyname);           				
                		
                	}
                }elseif ($options["type"]==strtolower("zset")){
                	//有序集合
					if($options["where"]){
						$opts = array('withscores'=>true);
						$zsets=$options["order"][0];
						switch (strtolower($zsets)) {
							case strtolower("desc"):
								$_cursor   = $this->_linkID->zRevRangeByScore($this->_keyname, $options["where"][0],$options["where"][1],$opts);
							break;
							default:
								$_cursor   = $this->_linkID->zRangeByScore($this->_keyname, $options["where"][0],$options["where"][1],$opts);
							break;
						}
					}
					else{
						$zsets=$options["order"][0]; 
						switch (strtolower($zsets)) {
							case strtolower("zRevRange"):
								$_cursor   = $this->_linkID->zRevRange($this->_keyname, $limit[0],$limit[1],$options["order"][1]);
							break;
							default:
								$_cursor   = $this->_linkID->zRange($this->_keyname, $limit[0],$limit[1],true);
							break;
						}
					}
                }elseif ($options["type"]==strtolower("string")){
                	//字符串
                	$_cursor   = $this->_linkID->mget($field);
                }elseif ($options["type"]==strtolower("hash")){
                	//HASH
                	if (empty($field)){
                		$_cursor   = $this->_linkID->hGetAll($this->_keyname);
                	}
					else if(is_array($field)){
                		$_cursor   = $this->_linkID->hmGet($this->_keyname,$field);
                	}
					else{
						$_cursor   = $this->_linkID->hGet($this->_keyname,$field);
					}
                }
            }else{
            	$_cursor   = $this->_linkID->lRange($this->_keyname, $limit[0],$limit[1]);
            }
            $this->debug();
            $this->_cursor =  $_cursor;
            $resultSets  =  $_cursor;
            if($cache && $resultSet ) { // 查询缓存写入
                S($key,$resultSet,$cache['expire'],$cache['type']);
            }
            return $resultSets;
        } catch (Exception $e) {
            throw_exception($e->getMessage());
        }
    }
	
	public function get($options=array(),$field){
	    if(isset($options['table'])) {
            $this->switchKey($options['prefix'] . $options['table'],'',false);
        }
		if ($options["type"]==strtolower("zset")){
			return $this->_linkID->zScore($this->_keyname, $field);
		}
		else if ($options["type"]==strtolower("hash")){
			if (empty($field)){
				return $this->_linkID->hGetAll($this->_keyname);
			}
			else if(is_array($field)){
				return $this->_linkID->hmGet($this->_keyname,$field);
			}
			else{
				return $this->_linkID->hGet($this->_keyname,$field);
			}
		}
		else{
			throw_exception('NOT_SUPPORT_TYPE');
		}
	}
	
	public function score($options=array(),$field){
	    if(isset($options['table'])) {
            $this->switchKey($options['prefix'] . $options['table'],'',false);
        }
		if ($options["type"]==strtolower("zset")){
			return $this->_linkID->zScore($this->_keyname, $field);
		}
		else{
			throw_exception('NOT_SUPPORT_TYPE');
		}
	}
	
	public function exists($options=array(),$key){
	    if(isset($options['table'])) {
            $this->switchKey($options['prefix'] . $options['table'],'',false);
        }
		if ($options["type"]==strtolower("zset")){
			return $this->_linkID->zScore($this->_keyname, $key) !== false;
		}
		else{
			throw_exception('NOT_SUPPORT_TYPE');
		}
	}
	
	public function min($options=array()){
	    if(isset($options['table'])) {
            $this->switchKey($options['prefix'] . $options['table'],'',false);
        }
		if ($options["type"]==strtolower("zset")){
			$start = $options['start'] ? : 0;
			$end = $options['end'] ? : 0;
			$result  = $this->_linkID->zRange($this->_keyname,$start,$end,true);
			foreach($result as $value=>$score){
				return array($value,$score);
			}
			return false;
		}
		else{
			throw_exception('NOT_SUPPORT_TYPE');
		}
	}
	
	public function max($options=array()){
	    if(isset($options['table'])) {
            $this->switchKey($options['prefix'] . $options['table'],'',false);
        }
		if ($options["type"]==strtolower("zset")){
			$start = $options['start'] ? : 0;
			$end = $options['end'] ? : 0;
			$result  = $this->_linkID->zRevRange($this->_keyname, $start,$end,true);
			foreach($result as $value=>$score){
				return array($value,$score);
			}
			return false;
		}
		else{
			throw_exception('NOT_SUPPORT_TYPE');
		}

	}
	
    /**
     * 统计记录数
     * @access public
     * @param array $options 表达式
     * @return iterator
     */
    public function count($options=array()){
    	$count=0; 	
        if(isset($options['table'])) {
            $this->switchKey($options['prefix'] . $options['table'],'',false);
        }
        $this->model  =   $options['model'];
        N('db_query',1);
        //$query  =  $this->parseWhere($options['where']);
        $field =  $this->parseField($options['field']);
        try{
            if(C('DB_SQL_LOG')) {
                $this->queryStr   =  $this->_dbName.'查询出错:'.$field;
            }
            // 记录开始执行时间
            G('queryStartTime');
            
        	if ($options['limit']){
                $limit=$this->parseLimit($options['limit']);
            }else{
            	$limit=null;
            }
            if($options['type']) {
                if ($options["type"]==strtolower("list")){
                	$count   = $this->_linkID->lSize($this->_keyname);
                }
				else if ($options["type"]==strtolower("queue")){
                	$count   = $this->_linkID->lSize($this->_keyname);
                }
				else if ($options["type"]==strtolower("stack")){
                	$count   = $this->_linkID->lSize($this->_keyname);
                }
				elseif ($options["type"]==strtolower("set")){
                	//集合
                	$count   = $this->_linkID->sCard($this->_keyname);
                }elseif ($options["type"]==strtolower("zset")){
                	//有序集合
                	if (empty($limit)){
                		$count   = $this->_linkID->zSize($this->_keyname);
						
                	}else {
                		$count   = $this->_linkID->zCount($this->_keyname,$limit[0],$limit[1]);
                	}
                }elseif ($options["type"]==strtolower("string")){
                	//字符串
                }elseif ($options["type"]==strtolower("hash")){
                	//HASH
                	$count   = $this->_linkID->hLen($this->_keyname);
                }
            }else{
            	$count   = $this->_linkID->lSize($this->_keyname);
            }
            $this->debug();
            return $count;
        } catch (Exception $e) {
            throw_exception($e->getMessage());
        }
    
    
    	
    }
	
	//清空当前数据库
	public function flushDB(){
		if ( !$this->_linkID ) 
			$this->initConnect(false);
		return $this->_linkID->flushDB();
	}
	
	//获取redis信息
	public function info(){
		if ( !$this->_linkID ) 
			$this->initConnect(false);
		return $this->_linkID->info();
	}
	
	public function tables($match = '*'){
		if ( !$this->_linkID ) 
			$this->initConnect(false);
		return $this->_linkID->keys($match);
	}
	//随机获取一个数据
	public function rnd($options=array(),$data){
        if(isset($options['table'])) {
            $this->switchKey($options['prefix'] . $options['table'],'',false);
        }
        $this->model  =   $options['model'];
        N('db_query',1);
        //$query  =  $this->parseWhere($options['where']);
        $field =  $this->parseField($options['field']);
        try{
            if(C('DB_SQL_LOG')) {
                $this->queryStr   =  $this->_dbName.'查询出错:'.$field;
            }
            // 记录开始执行时间
            G('queryStartTime');           
            if($options['type']) {
                if ($options["type"]==strtolower("list")){
                }
				else if ($options["type"]==strtolower("queue")){
                }
				else if ($options["type"]==strtolower("stack")){
                }
				elseif ($options["type"]==strtolower("set")){
					$rnd  = $this->_linkID->sRandMember($this->_keyname);
                }
				elseif ($options["type"]==strtolower("zset")){
                }
				elseif ($options["type"]==strtolower("string")){
                }
				elseif ($options["type"]==strtolower("hash")){
                }
            }else{
            }
            $this->debug();
            return $rnd;
        } catch (Exception $e) {
            throw_exception($e->getMessage());
        }    		
	}
	//获取一个数据，并删除
	public function rpop($options){
        if(isset($options['table'])) {
            $this->switchKey($options['prefix'] . $options['table'],'',false);
        }
		$this->model = $options['model'];
        N('db_query',1);
        //$query  =  $this->parseWhere($options['where']);
        $field =  $this->parseField($options['field']);
        try{
            if(C('DB_SQL_LOG')) {
                $this->queryStr = $this->_dbName.'查询出错:'.$field;
            }
            // 记录开始执行时间
            G('queryStartTime');           
			if ($options["type"]==strtolower("list")){
				$pop = $this->_linkID->rPop($this->_keyname);
			}
			else{
				throw_exception('NOT_SUPPORT_TYPE');
			}
            $this->debug();
            return $pop;
        } catch (Exception $e) {
            throw_exception($e->getMessage());
        }
	}
	//获取一个数据，并删除
	public function lpop($options){
        if(isset($options['table'])) {
            $this->switchKey($options['prefix'] . $options['table'],'',false);
        }
		$this->model = $options['model'];
        N('db_query',1);
        //$query  =  $this->parseWhere($options['where']);
        $field =  $this->parseField($options['field']);
        try{
            if(C('DB_SQL_LOG')) {
                $this->queryStr = $this->_dbName.'查询出错:'.$field;
            }
            // 记录开始执行时间
            G('queryStartTime');           
			if ($options["type"]==strtolower("list")){
				$pop = $this->_linkID->lPop($this->_keyname);
			}
			else{
				throw_exception('NOT_SUPPORT_TYPE');
			}
            $this->debug();
            return $pop;
        } catch (Exception $e) {
            throw_exception($e->getMessage());
        }
	}
	//获取一个数据，并删除
	public function pop($options){
        if(isset($options['table'])) {
            $this->switchKey($options['prefix'] . $options['table'],'',false);
        }
        $this->model = $options['model'];
        N('db_query',1);
        //$query  =  $this->parseWhere($options['where']);
        $field =  $this->parseField($options['field']);
        try{
            if(C('DB_SQL_LOG')) {
                $this->queryStr = $this->_dbName.'查询出错:'.$field;
            }
            // 记录开始执行时间
            G('queryStartTime');    
            if($options['type']) {
                if ($options["type"]==strtolower("list")){
					$pop = $this->_linkID->lPop($this->_keyname);
                }
                elseif ($options["type"]==strtolower("queue")){
					$pop = $this->_linkID->rPop($this->_keyname);
                }
                elseif ($options["type"]==strtolower("stack")){
					$pop = $this->_linkID->lPop($this->_keyname);
                }
				elseif ($options["type"]==strtolower("set")){
					$pop  = $this->_linkID->sPop($this->_keyname);
                }
				elseif ($options["type"]==strtolower("zset")){
                }
				elseif ($options["type"]==strtolower("string")){
                }
				elseif ($options["type"]==strtolower("hash")){
                }
            }else{
            }
            $this->debug();
            return $pop;
        } catch (Exception $e) {
            throw_exception($e->getMessage());
        }    		
	}
	
	//
	public function incrBy($options=array(),$inc){
		if(isset($options['table'])) {
            $this->switchKey($options['prefix'] . $options['table'],'',false);
        }
        $this->model  =  $options['model'];
        N('db_query',1);
        //$query  =  $this->parseWhere($options['where']);
        $field =  $this->parseField($options['field']);
        try{
            if(C('DB_SQL_LOG')) {
                $this->queryStr   =  $this->_dbName.'查询出错:'.$field;
            }
            // 记录开始执行时间
            G('queryStartTime');           
            if($options['type']) {
                if ($options["type"]==strtolower("list")){
                }
				elseif ($options["type"]==strtolower("set")){
                }
				elseif ($options["type"]==strtolower("zset")){
					$result = $this->_linkID->zIncrBy($this->_keyname,$inc,$field);
                	
                }
				elseif ($options["type"]==strtolower("string")){
                }
				elseif ($options["type"]==strtolower("hash")){
                }
            }
            $this->debug();
            return $result;
        } catch (Exception $e) {
            throw_exception($e->getMessage());
        }  
	}
	//添加一个数据
	public function lpush($options=array(),$data){
        if(isset($options['table'])) {
            $this->switchKey($options['prefix'] . $options['table'],'',false);
        }
		$this->model = $options['model'];
        N('db_query',1);
        //$query  =  $this->parseWhere($options['where']);
        $field =  $this->parseField($options['field']);
        try{
            if(C('DB_SQL_LOG')) {
                $this->queryStr = $this->_dbName.'查询出错:'.$field;
            }
            // 记录开始执行时间
            G('queryStartTime');           
			if ($options["type"]==strtolower("list")){
				$pop = $this->_linkID->lPush($this->_keyname,$data);
			}
			else{
				throw_exception('NOT_SUPPORT_TYPE');
			}
            $this->debug();
            return $pop;
        } catch (Exception $e) {
            throw_exception($e->getMessage());
        }
	}
	//添加一个数据
	public function rpush($options=array(),$data){
        if(isset($options['table'])) {
            $this->switchKey($options['prefix'] . $options['table'],'',false);
        }
		$this->model = $options['model'];
        N('db_query',1);
        //$query  =  $this->parseWhere($options['where']);
        $field =  $this->parseField($options['field']);
        try{
            if(C('DB_SQL_LOG')) {
                $this->queryStr = $this->_dbName.'查询出错:'.$field;
            }
            // 记录开始执行时间
            G('queryStartTime');           
			if ($options["type"]==strtolower("list")){
				$pop = $this->_linkID->rPush($this->_keyname,$data);
			}
			else{
				throw_exception('NOT_SUPPORT_TYPE');
			}
            $this->debug();
            return $pop;
        } catch (Exception $e) {
            throw_exception($e->getMessage());
        }
	}
    /**
     * 添加数据
     * Enter description here ...
     * @param unknown_type $options
     * @param unknown_type $data
     */
    public function add($options=array(),$data){    	
		
        if(isset($options['table'])) {
            $this->switchKey($options['prefix'] . $options['table'],'',false);
        }
        $this->model  =   $options['model'];
        N('db_query',1);
        //$query  =  $this->parseWhere($options['where']);
        $field =  $this->parseField($options['field']);
        try{
            if(C('DB_SQL_LOG')) {
                $this->queryStr   =  $this->_dbName.'查询出错:'.$field;
            }
            // 记录开始执行时间
            G('queryStartTime');           
            if($options['type']) {
                if ($options["type"]==strtolower("list")){
					if(is_array($data))
						$data = json_encode($data);
					$add   = $this->_linkID->lPush($this->_keyname,$data);
					if($options['size']){
						$trimsize = $this->_linkID->lSize($this->_keyname) - $options['size'];
						for($i=0;$i<$trimsize;$i++)
							$this->_linkID->rPop($this->_keyname);
					}
                }
                elseif ($options["type"]==strtolower("queue")){
					if(is_array($data))
						$data = json_encode($data);
					$add   = $this->_linkID->lPush($this->_keyname,$data);
					if($options['size']){
						$trimsize = $this->_linkID->lSize($this->_keyname) - $options['size'];
						for($i=0;$i<$trimsize;$i++)
							$this->_linkID->rPop($this->_keyname);
					}
                }
                elseif ($options["type"]==strtolower("stack")){
					if(is_array($data))
						$data = json_encode($data);
					$add   = $this->_linkID->lPush($this->_keyname,$data);
					if($options['size']){
						$trimsize = $this->_linkID->lSize($this->_keyname) - $options['size'];
						for($i=0;$i<$trimsize;$i++)
							$this->_linkID->rPop($this->_keyname);
					}
                }
				elseif ($options["type"]==strtolower("set")){
                	//集合
					if(is_array($data)){
						$ret = $this->_linkID->multi();
						foreach($data as $val){
							$ret = $ret->sAdd($this->_keyname,$val);
						}
						$ret->exec();
						$add = 1;
					}
					else{
						$add  = $this->_linkID->sAdd($this->_keyname,$data);
					}
                }
				elseif ($options["type"]==strtolower("zset")){
					//有序集合
                	foreach ($data as $field=>$score) {
						$add = $this->_linkID->zAdd($this->_keyname,$score,$field);
                	}
                	
                }elseif ($options["type"]==strtolower("string")){
                	//字符串
                	$add   = $this->_linkID->mset($data);
                }elseif ($options["type"]==strtolower("hash")){
                	//HASH
                	$add   = $this->_linkID->hMset($this->_keyname,$data);
                }
            }else{
            	$add   = $this->_linkID->lPush($this->_keyname,$data);
            }
            $this->debug();
            return $add;
        } catch (Exception $e) {
            throw_exception($e->getMessage());
        }    
    }
	
    /**
     * 删除数据
     * Enter description here ...
     * @param unknown_type $options
     * @param unknown_type $data
     */
	public function delete($options=array(),$way=""){    	
        if(isset($options['table'])) {
            $this->switchKey($options['prefix'] . $options['table'],'',false);
        }
        $this->model  =   $options['model'];
        N('db_query',1);
        //$query  =  $this->parseWhere($options['where']);
        $field =  $this->parseField($options['field']);
        try{
            if(C('DB_SQL_LOG')) {
                $this->queryStr   =  $this->_dbName.'查询出错:'.$field;
            }
            // 记录开始执行时间
            G('queryStartTime');  
            if ($options["type"]==strtolower("list") || empty($options["type"])){
                	//列表
                	switch (strtolower($way)) {
                		case "lpop":
                			$delete=$this->_linkID->lPop($this->_keyname);
                			break;
                		case "ltrim":
                			$delete=$this->_linkID->lTrim('key', $options["where"][0], $options["where"][1]);
                			break;
                		default:
                			if ($this->_linkID->lSet($this->_keyname,intval($options["where"]),"_deleted_")){                				
                				$delete=$this->_linkID->lRem($this->_keyname,"_deleted_",0);	
                			}
							else
								throw_exception('list delete exception: not allowed to delete');
                			break;
                	}
            }elseif ($options["type"]==strtolower("set")){
                	//集合
                	$delete   = $this->_linkID->sRem($this->_keyname,$options["where"]['_string']);
            }elseif ($options["type"]==strtolower("zset")){
                	//有序集合
           			 switch (strtolower($way)) {
                		case strtolower("byscore"):
                			$delete   = $this->_linkID->zRemRangeByScore($this->_keyname,$options["where"][0],$options["where"][1]);
                		break;
                		case strtolower("byrank"):
                			$delete   = $this->_linkID->zRemRangeByRank($this->_keyname,$options["where"][0],$options["where"][1]);
                		break;                		
                		case strtolower("all"):
							$delete = $this->_linkID->del($this->_keyname);
                		break;                		
                		default:
							if( $options["where"]['_string'] != '')
								$delete   = $this->_linkID->zDelete($this->_keyname,$options["where"]['_string']);
							else
								throw_exception('zset delete exception: not allowed to delete');
                		break;
                	}
                	
            }elseif ($options["type"]==strtolower("string")){
                	//字符串
                	$delete   = $this->_linkID->delete($field);
            }elseif ($options["type"]==strtolower("hash")){
                	//HASH
                	$delete   = $this->_linkID->hDel($this->_keyname, $options["where"]["_string"]);
            }else{
				throw_exception('delete exception: not supported type');
			}
            $this->debug();
            return $delete;
        } catch (Exception $e) {
            throw_exception($e->getMessage());
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
        if(!empty($keyname) && $keyname != $this->_keyname) {
            $this->switchKey($keyname,'',false);
        }
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