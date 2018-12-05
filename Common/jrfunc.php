<?php

//删除文件
function delDir($dirName) {
	 $dh = opendir($dirName);
	 //循环读取文件
	 while ($file = readdir($dh)) {
		 if($file != '.' && $file != '..') {
			 $fullpath = $dirName . '/' . $file;
			 //判断是否为目录
			 if(!is_dir($fullpath)) {
				 //如果不是,删除该文件
				 if(!unlink($fullpath)) {
					 //echo $fullpath . ' not deletable <br>';
				 }
			 } else {
				 //如果是目录,递归本身删除下级目录
				 delDir($fullpath);
			 }
		 }
	 }
	 //关闭目录
	 closedir($dh);
	 //删除目录
	 if(!rmdir($dirName)) {
		return false;
	 }
	 return true;
}

//驼峰命名法
function camelCase($name){
	return ucfirst(parse_name($name,1));
}
//驼峰转下划线
function underscore($str){
    $str = preg_replace_callback('/([A-Z]{1})/',function($matches){
        return '_'.strtolower($matches[0]);
    },$str);
    return $str;
}
//初始化aop的拦截点实例
function aop_advice_instance($guid){
	static $instances = array();
	if( !isset($instances[$guid])){
		$instances[$guid] = new $guid;
	}
	return $instances[$guid];
}
//生成数据库表的AOP操作类(只支持当前微服务下的数据库表)
function create_dbaop_class($name,$connection){
	if(! C('CC_AOP_PATH') )
		return false;
	$layer = C('DEFAULT_M_LAYER');
	$modelFile = C('CC_AOP_PATH') . 'model/' . camelCase($name) . $layer .'.class.php';
	if(is_file($modelFile)){
		require_once($modelFile);
		$class = camelCase($name) . $layer;
		$instance = new $class($name,'',$connection);
		return $instance;
	}
	return false;
}

//生成局部（微服务内部）AOP Advisor类
function create_local_aop_advisor_class($name){
	$class = camelCase($name) . 'Advisor';
	$filepath = LIB_PATH . 'Aop/' . $class . '.class.php';
	if(is_file($filepath)){
		require_once($filepath);
		return new $class();
	}
	else{
		return false;
	}
}

//生成全局AOP Advisor类
function create_global_aop_advisor_class($name){
	if(! C('CC_AOP_PATH') )
		return false;
	$class = camelCase($name) . 'Advisor';
	$filepath = C('CC_AOP_PATH') . 'advisor/' . $class . '.class.php';
	if(is_file($filepath)){
		require_once($filepath);
		return new $class();
	}
	else{
		return false;
	}
}

//生成插件框架下的AOP Advisor类
function create_plugin_aop_advisor_class($name){
	if(! C('SOLUTION_AOP_PATH') )
		return false;
	$class = camelCase($name) . 'Advisor';
	$filepath = C('SOLUTION_AOP_PATH') . $name . '.php';
	if(is_file($filepath)){
		require_once($filepath);
		return new $class();
	}
	else{
		return false;
	}
}
//加载其他微服务的数据库
function load_mservice_model($name,$prefix=''){
	//获取微服务前缀
	if(!$prefix){
		list($prefix) = explode('_',$name);
	}
	
	//对应的env
	$envfile = SOLUTION_ENV_PATH . $prefix . '.env';
	if(!file_exists($envfile) && $prefix == 'jms'){
		$envfile = SOLUTION_ENV_PATH . 'fdv2.env';
	}
	if(!file_exists($envfile)){
		echo $envfile . " doesn't exist";
		exit;
	}
	$result = require($envfile);
	if(!$result['DB_DSN']){
		$global_envfile = SOLUTION_DATA_PATH . '.env';
		if(!file_exists($global_envfile)){
			echo $global_envfile . " doesn't exist";
		exit;
		}
		$global_env = require($global_envfile);
		$result['DB_DSN'] = $global_env['DB_DSN'];
	}
	if(!$result['DB_DSN']){
		echo 'invalid envfile ' . $envfile;
		exit;
	}
	//跨数据库加载模型
	return M($name,null,$result['DB_DSN']);
}


//远程嘉软组件库的PHP类
function load_jiaruan_class($name){
	//从Lib/Class目录载入,php文件直接放到目录下
	$phpcls_dir = LIB_PATH . '/Class/';
	$localname = str_replace('Jiaruan\\','',$name);
	$phpcls_file = $phpcls_dir . $localname . '.php';
	if(file_exists($phpcls_file)){
		require_once($phpcls_file);
		return true;
	}
	
	//从缓存载入代码
	$filename = str_replace('\\','/',$name);
	$cache_file = RUNTIME_PATH . 'Class/' . $filename . '.php';
	
	if(file_exists($cache_file)){
		require_once($cache_file);
		return true;
	}
	
	//从PHP类库载入代码
	if( C('PHPCLS_SERVER') ){
		$phpcls_server = C('PHPCLS_SERVER');
		if(substr($phpcls_server,0,7) == "http://")
			$url = $phpcls_server . '/index.php?s=api/fetch_class&classname='.$name;
		else
			$url = $phpcls_server;
	}
	else{
		$url = JIARUAN_COMP_SERVER . '/phpcls/index.php?cls='.$name;
	}
	if(substr($url,0,7) == "http://"){
		$content = file_get_contents($url);
	}
	else if( substr($url,0,1) == "/" ){
		$classname = str_replace('\\','/',$name);
		$filepath = $url . 'Home/Lib/ShareClass/'. $classname . '.php';
		$content = file_get_contents($filepath);
	}
	else if( substr($url,1,1) == ":" ){
		$classname = str_replace('\\','/',$name);
		$filepath = $url . 'Home/Lib/ShareClass/'. $classname . '.php';
		$content = file_get_contents($filepath);
	}
	else if( substr($url,0,7) == "phar://" ){
		$classname = str_replace('\\','/',$name);
		$filepath = $url . 'Home/Lib/ShareClass/'. $classname . '.php';
		$content = file_get_contents($filepath);
	}
	else{
		die("invalid phpcls url " . $url);
	}
	
	if($content){
		$cache_dir = dirname($cache_file);
		if(!is_dir($cache_dir))
			mkdir($cache_dir,0755,true);
		file_put_contents($cache_file,$content);
		require_once($cache_file);
		return true;
	}
	
	//
	throw_exception('invaid phpcls '. $name . ' ' .$url);
	exit;
}


/*
函数说明:
	返回JSON成功信息

参数说明:
	message： 消息文本
	data： 额外数据

*/
function json_success($message, $data = ''){
	echo json_encode(array('success' => true, 'message' => $message, 'data' => $data),JSON_UNESCAPED_UNICODE);
	exit;
}
/*
函数说明:
	返回JSON失败信息

参数说明:
	message： 消息文本
	data： 额外数据

*/
function json_fail($message, $data = ''){
	echo json_encode(array('success' => false, 'message' => $message, 'data' => $data),JSON_UNESCAPED_UNICODE);
	exit;
}

/*
函数说明:
	返回JSON信息

参数说明:
	code：错误码
	message： 消息文本
	data： 额外数据

*/
function json_return($code,$message='', $data = ''){
	echo json_encode(array('code' => $code, 'message' => $message, 'data' => $data),JSON_UNESCAPED_UNICODE);
	exit;
}


/*
函数说明:
	生成32位自增GUID 年月日 + 微秒 + 随机数 + 访问IP + 域名 + 脚本路径
*/
function create_guid(){
	$guid = "";
	$guid .= dechex( substr(date('Y'),0,2) + 0xa0) ;
	$guid .= dechex( substr(date('Y'),2,2) + 0xa0) ;
	$guid .= dechex(date('m') + 0xa0) ;
	$guid .= dechex(date('d') + 0xa0) ;
	$guid .= dechex(date('H') + 0xa0) ;
	$guid .= dechex(date('i') + 0xa0) ;
	$guid .= dechex(date('s') + 0xa0) ;
	$guid .= substr( dechex(microtime(true)*10000000) ,-6);
	$guid .= substr( md5($_SERVER['HTTP_HOST'] . SCRIPT_FILENAME . mt_rand() ) , -6);
	$guid .= substr( md5($_SERVER['REMOTE_ADDR'] . mt_rand() ) , -6);
	return $guid;
}

/*
函数说明：
	PHPRPC远程调用
*/

function phprpc($url){
	vendor('phpRPC/phprpc_client');
	return new phprpc_client($url);
}

/*
函数说明：
	构造tp3的php命令行
*/
function php_cmd($action){
	$php = defined('PHP_CMD') ? PHP_CMD : 'php';
	return $php . ' index.php '. $action;
}
/**
 * Redis函数用于实例化一个没有模型文件的RedisModel
 * @param string $name Model名称 支持指定基础模型 例如 MongoModel:User
 * @param mixed $dsn 数据库连接信息
 * @return RedisModel
 */
function Redis($name='', $type='list', $dsn='REDIS_DSN') {
    if(!in_array($type,array('list','set','zset','hash','string','queue','stack'))){
		throw_exception('_INVALID_REDIS_TYPE' . ':' . $type);
	}
	static $_redis_model  = array();
    if(strpos($name,':')) {
        list($class,$name)    =  explode(':',$name);
    }else{
        $class = 'RedisModel';
    }
    $guid = $name . '_'. $type . '_' . $class . $dsn;
    if (!isset($_redis_model[$guid])){
        $_redis_model[$guid] = new $class($name,'',$dsn);
		$_redis_model[$guid]->type($type);
	}
    return $_redis_model[$guid];
}

/*
   获取数据库在redis中的缓存数据
   $key : redis hash表的键值
   $field : 表字段名
*/
function Drc($table,$key,$field = ''){
	if(!C('DRC_REDIS_DSN')){
		echo 'set constant DRC_REDIS_DSN first';
		exit;
	}
	$redis = Redis('drc_{table}','hash',C('DRC_REDIS_DSN'));
	$json = $redis->prefix('drc_')->table($table)->get($key);
	$data = json_decode($json,true);
	if($field)
		return $data[$field];
	else
		return $data;
}

/**/
function curl_json_post($url,$data){
	$ch = curl_init ();
	curl_setopt( $ch, CURLOPT_URL, $url ); 
	curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt( $ch, CURLOPT_POST, 1 );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($data));
	curl_setopt( $ch, CURLOPT_TIMEOUT,10); 
	curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0);
	$ret = curl_exec ( $ch );
	if ($ret === false ) {
		$result = array(
			'success'=>false,
			'message'=>curl_error($ch) 
		);
	}else {
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE);
		if ($http_code != 200) {
			$result = array(
				'success'=>false,
				'message'=> $http_code . " " . curl_error($ch) 
			);
		} else {
			$result = json_decode($ret,true);
			if(!$result){
				$result = array(
					'success'=>false,
					'message'=> 'return: ' .$ret 
				);
			}				
		}
	}
	curl_close ( $ch );
	return $result;
}
/*获取json格式*/
function curl_json_get($url){
	$ch = curl_init ();
	curl_setopt( $ch, CURLOPT_URL, $url ); 
	curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt( $ch, CURLOPT_TIMEOUT,5); 
	curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0);
	$ret = curl_exec ( $ch );
	if ($ret === false ) {
		$result = array(
			'success'=>false,
			'message'=>curl_error($ch) 
		);
	}else {
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE);
		if ($http_code != 200) {
			$result = array(
				'success'=>false,
				'message'=> $http_code . " " . curl_error($ch) 
			);
		} else {
			$result = json_decode($ret,true);
			if(!$result){
				$result = array(
					'success'=>false,
					'message'=> 'return: ' .$ret 
				);
			}				
		}
	}
	curl_close ( $ch );
	return $result;
}
//解码json格式的post数据
function phpinput_json(){
	return json_decode( file_get_contents("php://input"),true) ;
}

//获取uri所在目录
function query_uri_folder(){
	/*
	$uri = $_SERVER['REQUEST_URI'];
	$pos = strpos($uri, "/index.php");
	if($pos!==false){
		$uri_folder = substr($uri,0,$pos + 1);
		return $uri_folder;
	}
	return '/';
	*/
	$dir = dirname($_SERVER['PHP_SELF']);
	if($dir == '/')
		return $dir;
	else
		return $dir . '/';
}

//模板函数,生成网址
function tpl_create_url($url,$ctrl=''){
	//如果是绝对网址
	if(strpos($url,'http://') === 0 || strpos($url,'https://') === 0)
		return $url;
	//如果是 / 开头
	if(strpos($url,'/') === 0)
		return $url;
	//如果为空
	if($url == ''){
		return $_SERVER['REQUEST_URI'];
	}
	//如果有 .php 
	if(strpos($url,'.php') > 0 || strpos($url,'.html') > 0 )
		return query_uri_folder() . $url;
	//获取文件名
	$filename = basename($_SERVER['PHP_SELF']);
	//生成tp标准网址
	if(strpos($url,'/') === false){
		//如果中间没有/号，说明只有操作，没有控制器名
		return query_uri_folder() . $filename . '?s=' . $ctrl . '/' . $url;
	}
	else{
		//其他，一般是 tp的 控制器名/操作 形式
		return query_uri_folder() . $filename . '?s=' . $url;
	}
}

//模板函数，工具栏按钮过滤器
function tpl_list_toolbar_filter($list,$field_json){
	$field = json_decode($field_json,true);
	$field['url'] = tpl_create_url($field['view_textfield'],$list->getModelName());
	$field['param'] = json_decode($field['param'],true);
	if($list->toolbar_filter){
		$func = $list->toolbar_filter;
		$func($field);
	}
	return $field;
}
//模板函数，操作栏按钮过滤器
function tpl_list_actionbar_filter($list,$field_json){
	$field = json_decode($field_json,true);
	$field['url'] = tpl_create_url($field['view_textfield'],$list->getModelName());
	$field['param'] = json_decode($field['param'],true);
	if($list->actionbar_filter){
		$func = $list->actionbar_filter;
		$func($field);
	}
	return $field;
}
//模板函数，搜索栏过滤器
function tpl_list_searchbar_filter($list,$field_json){
	$field = json_decode($field_json,true);
	$field['editor']['url'] = tpl_create_url($field['editor']['url'],$list->getModelName());
	if($list->searchbar_filter){
		$func = $list->searchbar_filter;
		$func($field);
	}
	return $field;
}
//模板函数，列表选项卡过滤器
function tpl_list_tabbar_filter($list,$field_json){
	$field = json_decode($field_json,true);
	if($list->tabbar_filter){
		$func = $list->tabbar_filter;
		$func($field);
		$field['url'] = tpl_create_url($field['url'],$list->getModelName());
	}
	return $field;
}
//模板函数，表单选项卡过滤器
function tpl_form_tabbar_filter($form,$field_json){
	$field = json_decode($field_json,true);
	if($form->tabbar_filter){
		$func = $form->tabbar_filter;
		$func($field);
		$field['url'] = tpl_create_url($field['url'],$form->getModelName());
	}
	return $field;
}
//编码转换
function utf8_gbk($str){
	return mb_convert_encoding($str,"GBK","UTF-8");
}

//三元运算
function ternary($a,$x,$y){
	if($x)
		return $a ? $x : $y;
	else
		return $a ? : $y;
}
//获取引用网址的get参数
function referal_get($name){
	$referal_url = $_SERVER['HTTP_REFERER'];
	$query_str = parse_url($referal_url, PHP_URL_QUERY);
	parse_str($query_str, $param);
	return $param[$name];
}

//des加密
function des_encrypt($str,$key){
	import('ORG.Crypt.Des');
	$des = new Des();
	return base64_encode($des->encrypt($str,$key));
}

//des解密
function des_decrypt($base64_str,$key){
	$str = base64_decode($base64_str);
	if(!$str)
		return false;
	import('ORG.Crypt.Des');
	$des = new Des();
	return trim($des->decrypt($str,$key,true));
}
//加密s参数的key
function crypt_s_key(){
	$key  = '778cca74654c5b29';
	$key .= $_COOKIE["PHPSESSID"] ? : "";
	$key .= $_COOKIE[ 'uc_'. md5(APP_DOMAIN) ] ? : "";
	return md5($key);
}

//加密s参数
function encrypt_s($s){
	$prefix = substr($s,0,strpos($s ,'_'));
	return $prefix . '_es' .  urlencode(des_encrypt($s,crypt_s_key()));
}

//解密s参数
function decrypt_s($es){
	if(!$es)
		return false;
	$cs = substr($es,strpos($es ,'_'));
	if(substr($cs,0,3) != '_es'){
		return $es;
	}
	$result = des_decrypt(substr($cs,3),crypt_s_key());
	if(! preg_match("/^[A-Za-z0-9\/\_]+$/",$result) ){
		header('Location: /');
		exit;
	}
	return $result;
}

//执行类的静态函数
function invoke_static_method($class,$method,$args){
	try{
		$ref_method = new ReflectionMethod($class,$method);
		if(!$ref_method->isPublic())
			$ref_method->setAccessible(true);
		if(!$ref_method->isStatic())
			throw new ReflectionException($class . '::' . $method . 'not static method!');
		return $ref_method->invokeArgs(null, $args );
	}catch(ReflectionException $e){
		die('[error] invoke_static_method exception' . $e->getMessage() );
	}
}
