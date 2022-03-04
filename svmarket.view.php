<?php
/* Copyright (C) singleview.co.kr <http://singleview.co.kr> */
/**
 * @class  svmarketView
 * @author singleview.co.kr (root@singleview.co.kr)
 * @brief module view class
 */
class svmarketView extends svmarket
{
	var $module_srl = 0;
	var $list_count = 20;
	var $page_count = 10;
	var $cache_file;
	var $interval;
	var $path;

	/**
	 * @brief Initialization
	 */
	function init()
	{
		// Get a template path (page in the administrative template tpl putting together)
		// $this->setTemplatePath($this->module_path.'tpl');
	}

	/**
	 * @brief General request output
	 */
	function dispSvmarketIndex()
	{
		// Force the result output to be of XMLRPC
        Context::setResponseMethod("XMLRPC");
        $oArg = Context::getRequestVars();
        switch($oArg->mode)
        {
            case 'checkdate':
                $this->_checkUpdateDate();
                break;
            case 'applist':
                $this->_pushAppList();
                break;
        }
        exit;
    }

	/**
	 * @brief svmarket server active status 통지
	 */
    function _checkUpdateDate()
    {
        $aParams = [];
        $oRst = executeQuery('svmarket.getLatestUpdatedDate');
        if(!$oRst->toBool())
        {
            unset($oRst);
            $aParams["updatedate"] = "error";
        }
        if(count((array)$oRst->data) == 0)
        {
            unset($oRst);
            $aParams["updatedate"] = "error";
        }
        else
        	$aParams["updatedate"] = $oRst->data->updatetime;
		$sXmlResp = svmarketXmlGenerater::generate($aParams);
		echo $sXmlResp;
        /*'<?xml version="1.0" encoding="UTF-8"?>
        <response>
        <error>0</error>
        <message>success</message>
        <updatedate><![CDATA[20210805151519]]></updatedate>
        </response>';*/
    }
    
    function _pushAppList()
    {
        $oRst = executeQuery('svmarket.getLatestApps');
        $sXmlResp = svmarketXmlGenerater::generateAppList($oRst->data);
		echo $sXmlResp;
       
/*
        echo '<?xml version="1.0" encoding="UTF-8"?>
<response>
	<error>0</error>
	<message>success</message>
	<packageList>
		<item>
			<category_srl>18322943</category_srl>
			<package_srl>22657234</package_srl>
			<path>
				<![CDATA[./addons/xdt_google_analytics]]>
			</path>
			<title>
				<![CDATA[xe111 design team Google analytics Addon]]>
			</title>
			<homepage>
				<![CDATA[http://www.xedesignteam.com/]]>
			</homepage>
			<package_description>
				<![CDATA[싱글뷰의 코드를 달 수 있는 애드온입니다. Google, Google Analytics는 Google inc.의 상표입니다.]]>
			</package_description>
			<package_voter>6</package_voter>
			<package_voted>60</package_voted>
			<package_downloaded>1039</package_downloaded>
			<package_regdate>
				<![CDATA[20140327011542]]>
			</package_regdate>
			<package_last_update>
				<![CDATA[20210805151519]]>
			</package_last_update>
			<nick_name>
				<![CDATA[도라미]]>
			</nick_name>
			<item_srl>22756278</item_srl>
			<item_screenshot_url>
				<![CDATA[https://download.xpressengine.com/xedownload/app/22657234/thumbnails/md.png]]>
			</item_screenshot_url>
			<item_version>
				<![CDATA[1.2]]>
			</item_version>
			<item_voter>0</item_voter>
			<item_voted>0</item_voted>
			<item_downloaded>147</item_downloaded>
			<item_regdate>
				<![CDATA[20210805151519]]>
			</item_regdate>
			<package_star>5</package_star>
		</item>
	</packageList>
	<page_navigation>
		<total_count>10</total_count>
		<total_page>1</total_page>
		<cur_page>1</cur_page>
		<page_count>10</page_count>
		<first_page>1</first_page>
		<last_page>135</last_page>
		<point>0</point>
	</page_navigation>
</response>';
*/
        
        // exit;
		// // Variables used in the template Context:: set()
		// if($this->module_srl) Context::set('module_srl',$this->module_srl);

		// // $page_type_name = strtolower($this->module_info->page_type);
		// // $method = '_get' . ucfirst($page_type_name) . 'Content';
		// // if(method_exists($this, $method)) $page_content = $this->{$method}();
		// // else return new BaseObject(-1, sprintf('%s method is not exists', $method));

		// Context::set('module_info', $this->module_info);
		// Context::set('page_content', $page_content);

		// $this->setTemplateFile('content');
	}

	/**
	 * @brief Create a cache file in order to include if it is an internal file
	 */
	function executeFile($target_file, $caching_interval, $cache_file)
	{
		// Cancel if the file doesn't exist
		if(!file_exists(FileHandler::getRealPath($target_file))) return;

		// Get a path and filename
		$tmp_path = explode('/',$cache_file);
		$filename = $tmp_path[count($tmp_path)-1];
		$filepath = preg_replace('/'.$filename."$/i","",$cache_file);
		$cache_file = FileHandler::getRealPath($cache_file);

		$level = ob_get_level();
		// Verify cache
		if($caching_interval <1 || !file_exists($cache_file) || filemtime($cache_file) + $caching_interval*60 <= $_SERVER['REQUEST_TIME'] || filemtime($cache_file)<filemtime($target_file))
		{
			if(file_exists($cache_file)) FileHandler::removeFile($cache_file);

			// Read a target file and get content
			ob_start();
			include(FileHandler::getRealPath($target_file));
			$content = ob_get_clean();
			// Replace relative path to the absolute path 
			$this->path = str_replace('\\', '/', realpath(dirname($target_file))) . '/';
			$content = preg_replace_callback('/(target=|src=|href=|url\()("|\')?([^"\'\)]+)("|\'\))?/is',array($this,'_replacePath'),$content);
			$content = preg_replace_callback('/(<!--%import\()(\")([^"]+)(\")/is',array($this,'_replacePath'),$content);

			FileHandler::writeFile($cache_file, $content);
			// Include and then Return the result
			if(!file_exists($cache_file)) return;
			// Attempt to compile
			$oTemplate = &TemplateHandler::getInstance();
			$script = $oTemplate->compileDirect($filepath, $filename);

			FileHandler::writeFile($cache_file, $script);
		}

		$__Context = &$GLOBALS['__Context__'];
		$__Context->tpl_path = $filepath;

		ob_start();
		include($cache_file);

		$contents = '';
		while (ob_get_level() - $level > 0) {
			$contents .= ob_get_contents();
			ob_end_clean();
		}
		return $contents;
	}

	function _replacePath($matches)
	{
		$val = trim($matches[3]);
		// Pass if the path is external or starts with /, #, { characters
		// /=absolute path, #=hash in a page, {=Template syntax
		if(strpos($val, '.') === FALSE || preg_match('@^((?:http|https|ftp|telnet|mms)://|(?:mailto|javascript):|[/#{])@i',$val))
		{
				return $matches[0];
			// In case of  .. , get a path
		}
		else if(strncasecmp('..', $val, 2) === 0)
		{
			$p = Context::pathToUrl($this->path);
			return sprintf("%s%s%s%s",$matches[1],$matches[2],$p.$val,$matches[4]);
		}

		if(strncasecmp('..', $val, 2) === 0) $val = substr($val,2);
		$p = Context::pathToUrl($this->path);
		$path = sprintf("%s%s%s%s",$matches[1],$matches[2],$p.$val,$matches[4]);

		return $path;
	}
}
/* End of file svmarket.view.php */
/* Location: ./modules/svmarket/svmarket.view.php */
