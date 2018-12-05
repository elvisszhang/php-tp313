<?php
class AopModel extends Model{
	private function init(){
		if(! $this->table ){
			echo 'table empty';
			exit;
		}
		if(! $this->advices ){
			echo 'advices empty';
			exit;
		}
		
		foreach($this->advices as $guid){
			require_once( C('CC_AOP_PATH') . 'model/' . $this->table . '/' . $guid . '.php');
		}
	}
	
	protected function _before_insert(&$data,$options) {
		$this->init();
		foreach($this->advices as $guid){
			$cls = aop_advice_instance($guid);
			if( $cls->_before_insert($data,$options) === false){
				return false;
			}
		}
		return true;
	}
	
	protected function _after_insert($data,$options) {
		$this->init();
		foreach($this->advices as $guid){
			$cls = aop_advice_instance($guid);
			$cls->_after_insert($data,$options);
		}
	}
	
	protected function _before_update(&$data,$options) {
		$this->init();
		foreach($this->advices as $guid){
			$cls = aop_advice_instance($guid);
			if( $cls->_before_update($data,$options) === false){
				return false;
			}
		}
		return true;
	}
	
	protected function _after_update($data,$options) {
		$this->init();
		foreach($this->advices as $guid){
			$cls = aop_advice_instance($guid);
			$cls->_after_update($data,$options);
		}
	}
}