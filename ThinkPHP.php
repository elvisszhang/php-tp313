<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2012 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
//检查必须配置常量
//嘉软远程服务器配置

//不允许自己定义 APP_DEBUG 常量，防止进入git仓库
if( defined('APP_DEBUG') )
	die('APP_DEBUG should not defined. use .debug instead');

// ThinkPHP 入口文件
// 记录开始运行时间
$GLOBALS['_beginTime'] = microtime(TRUE);
// 记录内存初始使用
define('MEMORY_LIMIT_ON',function_exists('memory_get_usage'));
if(MEMORY_LIMIT_ON) $GLOBALS['_startUseMems'] = memory_get_usage();
// 自动解析命令行&WEB模式
if(PHP_SAPI == 'cli'){ //解析命令行参数
	$argv = $_SERVER['argv'];
	defined('SCRIPT_FILENAME') or die('must define SCRIPT_FILENAME' . PHP_EOL);
	if(count($argv) >= 2 && in_array($argv[1],array('start','stop','status')) ){
		cli_workerman($argv);
	} else{
		cli_normal($argv);
	}
}
else{
	define('APP_DOMAIN' , $_SERVER["SERVER_NAME"]);
	defined('SCRIPT_FILENAME') or define('SCRIPT_FILENAME' , $_SERVER["SCRIPT_FILENAME"]);
	// 自动判断切换为PHPRPC模式
	if( isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT'] == 'PHPRPC Client 3.0 for PHP'){
		define('MODE_NAME','phprpc');
	}
}

// 系统目录定义
defined('THINK_PATH') 	or define('THINK_PATH', dirname(__FILE__).'/');
if(defined('APP_FILENAME'))
	defined('APP_PATH') 	or define('APP_PATH', dirname(APP_FILENAME) . '/Home/');
else
	defined('APP_PATH') 	or define('APP_PATH', dirname(SCRIPT_FILENAME) . '/Home/');
defined('ENTRY_PATH') 	or define('ENTRY_PATH', dirname(SCRIPT_FILENAME) . '/');  //TP项目根目录

//TP解决方案(多个tp工程的组合)路径
defined('SOLUTION_PATH') or define('SOLUTION_PATH', APP_SINGLE_PLUGIN ? ENTRY_PATH : dirname(ENTRY_PATH) . '/');  
$solution_data_dir = ini_get('jrlic.solution_data_dir');
if($solution_data_dir){
	$solution_data_dir = str_replace('{dir1}',basename(SOLUTION_PATH),$solution_data_dir);
	$solution_data_dir = str_replace('{dir2}',basename(dirname(SOLUTION_PATH)),$solution_data_dir);
	if(substr($solution_data_dir,-1) != '/')
		$solution_data_dir .= '/';
	define('SOLUTION_DATA_PATH', $solution_data_dir); 
}
else{
	define('SOLUTION_DATA_PATH', SOLUTION_PATH . '.data/'); 
}
define('SOLUTION_RUNTIME_PATH', SOLUTION_DATA_PATH . 'runtime/'); 
define('SOLUTION_ENV_PATH', SOLUTION_DATA_PATH . 'env/'); 
define('SOLUTION_LOG_PATH', SOLUTION_DATA_PATH . 'log/'); 
define('SOLUTION_LANG_PATH', SOLUTION_DATA_PATH . 'lang/'); 

// 检查数据目录权限
if(!is_dir(SOLUTION_DATA_PATH)) {
	mkdir(SOLUTION_DATA_PATH,0777,true);
	chmod(SOLUTION_DATA_PATH,0777);
}
if(!is_writeable(SOLUTION_DATA_PATH)) {
	header('Content-Type:text/html; charset=utf-8');
	exit('目录 [ '.SOLUTION_DATA_PATH.' ] 不可写！');
}
// 检查env目录权限
if(!is_dir(SOLUTION_ENV_PATH)) {
	mkdir(SOLUTION_ENV_PATH,0777,true);
	chmod(SOLUTION_ENV_PATH,0777);
}
if(!is_writeable(SOLUTION_ENV_PATH)) {
	header('Content-Type:text/html; charset=utf-8');
	exit('目录 [ '.SOLUTION_ENV_PATH.' ] 不可写！');
}
// 检查log目录权限
if(!is_dir(SOLUTION_LOG_PATH)) {
	mkdir(SOLUTION_LOG_PATH,0777,true);
	chmod(SOLUTION_LOG_PATH,0777);
}
if(!is_writeable(SOLUTION_LOG_PATH)) {
	header('Content-Type:text/html; charset=utf-8');
	exit('目录 [ '.SOLUTION_LOG_PATH.' ] 不可写！');
}
// 检查runtime目录权限
if(!is_dir(SOLUTION_RUNTIME_PATH)) {
	mkdir(SOLUTION_RUNTIME_PATH,0777,true);
	chmod(SOLUTION_RUNTIME_PATH,0777);
}
if(!is_writeable(SOLUTION_RUNTIME_PATH)) {
	header('Content-Type:text/html; charset=utf-8');
	exit('目录 [ '.SOLUTION_RUNTIME_PATH.' ] 不可写！');
}

// 项目名称
defined('APP_NAME') or define('APP_NAME', basename(dirname(SCRIPT_FILENAME)));

//定义运行时文件存放路径
$rtpath = SOLUTION_RUNTIME_PATH . APP_NAME . '_' . (PHP_SAPI == 'cli' ? 'cli' : 'web') . '/';
defined('RUNTIME_PATH') or define('RUNTIME_PATH', $rtpath);

$runtime = defined('MODE_NAME')?'~'.strtolower(MODE_NAME).'_runtime.php':'~runtime.php';
defined('RUNTIME_FILE') or define('RUNTIME_FILE',RUNTIME_PATH.$runtime);


//检查是否调试模式
if(is_file(ENTRY_PATH . '.debug') || is_file(SOLUTION_PATH . '.debug')){
	define('APP_DEBUG',true );
}
else{
	define('APP_DEBUG',false );
}

//定义环境常量路径目录,如果目录底下存在 .env文件，直接栽入，如果不存在，那么从嘉软配置中心加载环境文件
if(is_file(SOLUTION_DATA_PATH . '.env')){
	define('SOLUTION_ENV_FILE', SOLUTION_DATA_PATH . '.env');
}

if(is_file(ENTRY_PATH . '.env')){
	define('ENV_FILE', ENTRY_PATH . '.env');
}
elseif(is_file(SOLUTION_ENV_PATH . APP_PREFIX .'.env')){
	define('ENV_FILE', SOLUTION_ENV_PATH . APP_PREFIX .'.env' );
}
else{
	define('ENV_FILE', '' );
}


//
if(defined('ENGINE_NAME')) {
    defined('ENGINE_PATH') or define('ENGINE_PATH',THINK_PATH.'Extend/Engine/');
	require ENGINE_PATH . strtolower(ENGINE_NAME).'.php';
}else{
	if(!APP_DEBUG && is_file(RUNTIME_FILE)) {
	    // 部署模式直接载入运行缓存
	    require RUNTIME_FILE;
	}else{
	    // 加载运行时文件
	    require THINK_PATH.'Common/runtime.php';
	}	
}
//命令行使用帮助
function cli_usage(){
	echo '--------normal---------' . PHP_EOL;
	echo 'php index.php' . PHP_EOL;
	echo 'php index.php <controller>/<action>' . PHP_EOL;
	echo 'php index.php <controller>/<action> <other params>' . PHP_EOL;
	echo '--------workerman start---------' . PHP_EOL;
	echo 'php index.php start' . PHP_EOL;
	echo 'php index.php start <controller>/<action>' . PHP_EOL;
	echo 'php index.php start -d' . PHP_EOL;
	echo 'php index.php start -d <controller>/<action>' . PHP_EOL;
	echo '--------workerman stop---------' . PHP_EOL;
	echo 'php index.php stop' . PHP_EOL;
	exit;	
}

//命令行的workerman模式
function cli_workerman_daemon($argv){
	if(count($argv) == 3){ //php index.php start -d
		$_GET['m'] = 'index';
		$_GET['a'] = 'index';
	}
	elseif(count($argv) == 4){ //php index.php start -d <controller>/<action>
		$param = explode('/',$argv[3]);
		if(count($param) != 2)
			cli_usage();
		$_GET['m'] = $param[0];
		$_GET['a'] = $param[1];	
	}
	else{
		cli_usage();
	}
}

function cli_workerman_debug($argv){
	if(count($argv) == 2){ //php index.php start 或者 php index.php status
		$_GET['m'] = 'index';
		$_GET['a'] = 'index';
	}
	elseif(count($argv) == 3){ //php index.php start <controller>/<action>
		$param = explode('/',$argv[2]);
		if(count($param) != 2)
			cli_usage();
		$_GET['m'] = $param[0];
		$_GET['a'] = $param[1];	
	}
	else{
		cli_usage();
	}
}

function cli_workerman($argv){
	if($argv[1] == 'start'){
		if(count($argv)>=3 && $argv[2] == '-d'){
			cli_workerman_daemon($argv);
		}
		else{
			cli_workerman_debug($argv);
		}
	}
	if($argv[1] == 'status'){
		cli_workerman_debug($argv);
	}
}
//普通命令行模式
function cli_normal($argv){
	//普通命令行模式
	if(count($argv) == 1){
		$_GET['m'] = 'index';
		$_GET['a'] = 'index';
	}
	elseif(count($argv) >= 2) {
		$param = explode('/',$argv[1]);
		if(count($param) != 2){
			cli_usage();
		}
		$_GET['m'] = $param[0];
		$_GET['a'] = $param[1];
	}
	else{ //>=3
		cli_usage();
	}	
}
