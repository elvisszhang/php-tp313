<?php
/**
 * Smarty Internal Plugin Resource Registered
 *
 * @package    Smarty
 * @subpackage TemplateResources
 * @author     Uwe Tews
 * @author     Rodney Rehm
 */

/**
 * Smarty Internal Plugin Resource Registered
 * Implements the registered resource for Smarty template
 *
 * @package    Smarty
 * @subpackage TemplateResources
 * @deprecated
 */
class Smarty_Internal_Resource_Jiaruan extends Smarty_Resource
{
	private $content;
    /**
     * populate Source Object with meta data from Resource
     *
     * @param  Smarty_Template_Source   $source    source object
     * @param  Smarty_Internal_Template $_template template object
     *
     * @return void
     */
    public function populate(Smarty_Template_Source $source, Smarty_Internal_Template $_template = null)
    {
		$source->tpl_path = $_template->parent->source->filepath ? : $_template->parent->source->tpl_path;
		if(!$source->tpl_path){ 
			//echo json_encode($_template);
			throw_exception('error: empty tpl_path');
			exit;
		}
				
		$this->content = $this->loadTemplate($source);
		if(!$this->content){
			echo $source->filepath . 'not found!';
			exit;
		}
		
		$lang = defined('LANG_SET') ? LANG_SET : 'zh-cn';
        $source->uid = sha1($source->name . '_' . $source->tpl_path . '_' . $lang);  //之前用了 sha1($source->name) 作为 uid 算法，导致了不同页面用了同一个jiaruan模板时会发生缓存混乱;
		
		//$source->timestamp = time();  //强制刷新缓存
        //$source->exists = !!$this->content;

		//以下是使用模板缓存的算法（确保不同的页面 $source->uid 必须唯一才行，不然就会使用别的模板的缓存）
        $source->timestamp = !!$this->content;
        $source->exists = !!$this->content;
	}
	
	private function loadTemplate(Smarty_Template_Source $source){
		//获取语言
		$lang = defined('LANG_SET') ? LANG_SET : 'zh-cn';

		//检测是否可重定义的模板名
		$pos = strpos($source->name,'?');
		if( $pos > 0){
			$configName = substr($source->name,0,$pos);
			$defaultVal =  substr($source->name,$pos+1);
			$source->name = C($configName) ? : $defaultVal;
		}
		
		//检测是否是函数名称
		$pos = strpos($source->name,'()');
		if( $pos > 0){
			$funcName = substr($source->name,0,$pos);
			$source->name = $funcName();
		}
				
		//从本地缓存载入模板
		$cache_dir = $source->smarty->getCacheDir();
		$cache_name = str_replace('/','_',$source->name) . "_" . md5($source->tpl_path) . "_" . $lang;
		$cache_file = $cache_dir . $cache_name . '.smarty3.tpl';
		if(file_exists($cache_file)){
			$content = file_get_contents($cache_file);
			if($content){
				return $content;
			}
		}
		
		//从前端中心远程获取模板
		$block_data = $this->getBlockContent($source->tpl_path);
		$source->filepath = UICOMP_SERVER . '/?tpl=' . $source->name . '&l=' . $lang . '&eng=smarty3&version=2';
		$http_data = $this->httpPost($source->filepath,$block_data);
		$data = json_decode($http_data,true);
		if($data){
			//检查模板成功标记
			if(!$data['success']){
				die('UICOMP SERVER IS BUSY, RETRY [99]!<br>'. $source->filepath . '<br>' . htmlspecialchars($http_data) );
				return null;
			}
			//保存到本地缓存
			if(!is_dir($cache_dir))
				mkdir($cache_dir,0777,true);
			file_put_contents($cache_file,$data['content']);
			//返回模板内容
			return $data['content'];
		}
		else{
			die('UICOMP SERVER IS BUSY, RETRY[108]!<br>' . $source->filepath . '<br>'. htmlspecialchars($http_data));
		}
		return null;
	}
	

	
	//load content of <{block name=content}><{/block}>
	private function getBlockContent($tpl_path){
		if(!file_exists($tpl_path)){
			return '';
		}
		$tpl = file_get_contents($tpl_path);
		$pos1 = strpos($tpl,'<{block name=content}>',0);
		if($pos1 === false){
			return '';
		}
		$pos1 += strlen('<{block name=content}>');
		$pos2 = strpos($tpl,'<{/block}>',$pos1);
		if($pos2 === false){
			return '';
		}
		return substr($tpl,$pos1,$pos2-$pos1);
	}
	
    /**
     * populate Source Object with timestamp and exists from Resource
     *
     * @param  Smarty_Template_Source $source source object
     *
     * @return void
     */
    public function populateTimestamp(Smarty_Template_Source $source)
    {
        $source->timestamp = !!$this->content;
        $source->exists = !!$this->content;
    }

    /**
     * Load template's source by invoking the registered callback into current template object
     *
     * @param  Smarty_Template_Source $source source object
     *
     * @return string                 template source
     * @throws SmartyException        if source cannot be loaded
     */
    public function getContent(Smarty_Template_Source $source)
    {
        if ($source->exists) {
            return $this->content;
        }
        throw new SmartyException('Unable to read ' . ($source->isConfig ? 'config' : 'template') .
                                  " {$source->type} '{$source->name}'");
    }

    /**
     * Determine basename for compiled filename
     *
     * @param  Smarty_Template_Source $source source object
     *
     * @return string                 resource's basename
     */
    public function getBasename(Smarty_Template_Source $source)
    {
        return basename($source->name);
    }
}
