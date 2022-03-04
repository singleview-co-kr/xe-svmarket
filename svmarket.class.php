<?php
/* Copyright (C) singleview.co.kr <http://singleview.co.kr> */
/**
 * @class  svmarket
 * @author singleview.co.kr (root@singleview.co.kr)
 * @brief high class of the module svmarket
 */
class svmarketXmlGenerater
{
	/**
	 * Generate XML using given data
	 *
	 * @param array $params The data
	 * @return string Returns xml string
	 */
	public static function generate($aParam)
	{
		$sXmlDoc = '<?xml version="1.0" encoding="utf-8" ?><response><error>0</error>';
		$sXmlDoc .= '<message>success</message>';
		if(!is_array($aParam))
		{
			echo __FILE__.':'.__LINE__.'<BR>';
			return NULL;
		}
		foreach($aParam as $key => $val)
		{
			$sXmlDoc .= sprintf("<%s><![CDATA[%s]]></%s>", $key, $val, $key);
		}
		$sXmlDoc .= "</response>";
		return $sXmlDoc;
	}

	/**
	 * Request data to server and returns result
	 *
	 * @param array $params Request data
	 * @return object
	 */
	public static function generateAppList($aAppInfo)
	{
        $sXmlDoc = '<?xml version="1.0" encoding="utf-8" ?><response><error>0</error>';
		$sXmlDoc .= '<message>success</message>';
        $aTmpInfo = [];
        if(is_object($aAppInfo))
        {
            $aTmpInfo[] = $aAppInfo;
            $aAppInfo = $aTmpInfo;
        }
		if(!is_array($aAppInfo))
		{
			echo __FILE__.':'.__LINE__.'<BR>';
			return NULL;
		}
        $sXmlDoc .= '<packageList>';
		foreach($aAppInfo as $nIdx => $oApp)
		{
            $sXmlDoc .= '<item>';
            foreach($oApp as $key => $val)
            {
                if(is_string($val))
                    $sXmlDoc .= sprintf("<%s><![CDATA[%s]]></%s>", $key, $val, $key);
                else
                    $sXmlDoc .= sprintf("<%s>%s</%s>", $key, $val, $key);
            }
            $sXmlDoc .= "<path>";
			$sXmlDoc .= "	<![CDATA[./addons/xdt_google_analytics]]>";
			$sXmlDoc .= "</path>";
            $sXmlDoc .= "<package_voter>6</package_voter>";
			$sXmlDoc .= "<package_voted>60</package_voted>";
			$sXmlDoc .= "<package_downloaded>1039</package_downloaded>";
            $sXmlDoc .= "<nick_name>";
			$sXmlDoc .= "	<![CDATA[도라미]]>";
			$sXmlDoc .= "</nick_name>";
			$sXmlDoc .= "<item_srl>22756278</item_srl>";
			$sXmlDoc .= "<item_screenshot_url>";
			$sXmlDoc .= "	<![CDATA[https://download.xpressengine.com/xedownload/app/22657234/thumbnails/md.png]]>";
			$sXmlDoc .= "</item_screenshot_url>";
			$sXmlDoc .= "<item_version>";
			$sXmlDoc .= "	<![CDATA[1.2]]>";
			$sXmlDoc .= "</item_version>";
			$sXmlDoc .= "<item_voter>0</item_voter>";
			$sXmlDoc .= "<item_voted>0</item_voted>";
			$sXmlDoc .= "<item_downloaded>147</item_downloaded>";
			$sXmlDoc .= "<item_regdate>";
			$sXmlDoc .= "	<![CDATA[20210805151519]]>";
			$sXmlDoc .= "</item_regdate>";
			$sXmlDoc .= "<package_star>5</package_star>";
            $sXmlDoc .= '</item>';	
		}
        $sXmlDoc .= '</packageList>';
        $sXmlDoc .= "<page_navigation>";
		$sXmlDoc .= "<total_count>10</total_count>";
		$sXmlDoc .= "<total_page>1</total_page>";
		$sXmlDoc .= "<cur_page>1</cur_page>";
		$sXmlDoc .= "<page_count>10</page_count>";
		$sXmlDoc .= "<first_page>1</first_page>";
		$sXmlDoc .= "<last_page>135</last_page>";
		$sXmlDoc .= "<point>0</point>";
	    $sXmlDoc .= "</page_navigation>";
        $sXmlDoc .= "</response>";
		return $sXmlDoc;
	}

}

class svmarket extends ModuleObject
{
	/**
	 * @brief Implement if additional tasks are necessary when installing
	 */
	function moduleInstall()
	{
		// page generated from the cache directory to use
		FileHandler::makeDir('./files/cache/page');

		return new BaseObject();
	}

	/**
	 * @brief a method to check if successfully installed
	 */
	function checkUpdate()
	{
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		$version_update_id = implode('.', array(__CLASS__, __XE_VERSION__, 'updated'));
		if($oModuleModel->needUpdate($version_update_id))
		{
			$output = executeQuery('page.pageTypeOpageCheck');
			if($output->toBool() && $output->data) return true;

			$output = executeQuery('page.pageTypeNullCheck');
			if($output->toBool() && $output->data) return true;

			$oModuleController->insertUpdatedLog($version_update_id);
		}

		return false;
	}

	/**
	 * @brief Execute update
	 */
	function moduleUpdate()
	{
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		$version_update_id = implode('.', array(__CLASS__, __XE_VERSION__, 'updated'));
		if($oModuleModel->needUpdate($version_update_id))
		{
			$args = new stdClass;
			// opage module instance update
			$output = executeQueryArray('page.pageTypeOpageCheck');
			if($output->toBool() && count($output->data) > 0)
			{
				foreach($output->data as $val)
				{
					$args->module_srl = $val->module_srl;
					$args->name = 'page_type';
					$args->value= 'OUTSIDE';
					$in_out = executeQuery('page.insertPageType', $args);
				}
				$output = executeQuery('page.updateAllOpage');
				if(!$output->toBool()) return $output;
			}

			// old page module instance update
			$output = executeQueryArray('page.pageTypeNullCheck');
			$skin_update_srls = array();
			if($output->toBool() && $output->data)
			{
				foreach($output->data as $val)
				{
					$args->module_srl = $val->module_srl;
					$args->name = 'page_type';
					$args->value= 'WIDGET';
					$in_out = executeQuery('page.insertPageType', $args);

					$skin_update_srls[] = $val->module_srl;
				}
			}

			if(count($skin_update_srls)>0)
			{
				$skin_args = new stdClass;
				$skin_args->module_srls = implode(',',$skin_update_srls);
				$skin_args->is_skin_fix = "Y";
				$ouput = executeQuery('page.updateSkinFix', $skin_args);
			}

			$oModuleController->insertUpdatedLog($version_update_id);
		}

		return new BaseObject(0,'success_updated');
	}

	/**
	 * @brief Re-generate the cache file
	 */
	function recompileCache()
	{
	}
}
/* End of file svmarket.class.php */
/* Location: ./modules/svmarket/svmarket.class.php */
