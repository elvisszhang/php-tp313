<?php
class AopAdvisor{
	public function run(&$data = NULL, $options = array() ){
		if($this->scope == 'local'){ //加载微服务内部的aop切点
			foreach($this->advices as $guid){
				require_once( LIB_PATH . 'Aop/' . $this->name . '/' . $guid . '.php');
				$cls = aop_advice_instance($guid);
				if( $cls->run($data,$options) === false){
					return false;
				}
			}
		}
		else{ //加载项目全局的aop切点
			foreach($this->advices as $guid){
				require_once( C('CC_AOP_PATH') . 'advisor/' . $this->name . '/' . $guid . '.php');
				$cls = aop_advice_instance($guid);
				if( $cls->run($data,$options) === false){
					return false;
				}
			}
		}
		return true;
	}
	
	public function __call($method,$args) {
		
	}
}