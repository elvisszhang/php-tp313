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
class Smarty_Internal_Resource_Jiaruandev extends Smarty_Resource
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
    public function populate(Smarty_Template_Source $source, Smarty_Internal_Template $_template = null){
		$source->tpl_path = $_template->parent->source->filepath ? : $_template->parent->source->tpl_path;
		if(!$source->tpl_path){ 
			//echo json_encode($_template);
			throw_exception('error: empty tpl_path');
			exit;
		}

		$this->content = $this->loadTemplate($source);
		if(!$this->content){
			echo $source->compurl . ' not found!';
			exit;
		}
		
		$lang = defined('LANG_SET') ? LANG_SET : 'zh-cn';
        $source->uid = sha1($source->name . '_' . $source->tpl_path . '_' . $lang);
        $source->timestamp = time();  //设置为false，强制刷新缓存
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
		//从远程获取模板
		$block_data = $this->getBlockContent($source->tpl_path);
		$source->compurl = UICOMP_SERVER . '/?tpl=' . $source->name .'&l=' . $lang .'&eng=smarty3&version=2&debug=1';
		
		$http_data = $this->httpPost($source->compurl,$block_data);
		$data = json_decode($http_data,true);
		if($data){
			//检查模板成功标记
			if(!$data['success']){
				die('UICOMP SERVER IS BUSY, RETRY [99]!<br>'. $http_data);
				return null;
			}
			//保存到缓存
			$cache_dir = $source->smarty->getCacheDir();
			$cache_name = str_replace('/','_',$source->name) . "_" . md5($source->tpl_path) . "_" . $lang;
			$cache_file = $cache_dir . $cache_name . '.smarty3.tpl';
			if(!is_dir($cache_dir))
				mkdir($cache_dir,0777,true);
			file_put_contents($cache_file,$data['content']);
			//返回模板内容
			return $data['content'];
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
