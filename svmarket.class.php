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
	public static function generate($params)
	{
		$sXmlDoc = '<?xml version="1.0" encoding="utf-8" ?><response><error>0</error>';
		$sXmlDoc .= '<message>success</message>';
		if(!is_array($params))
		{
			echo __FILE__.':'.__LINE__.'<BR>';
			return NULL;
		}
		foreach($params as $key => $val)
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
	public static function getXmlDoc(&$params)
	{
		$body = XmlGenerater::generate($params);
		$buff = FileHandler::getRemoteResource(_XE_DOWNLOAD_SERVER_, $body, 3, "GET", "application/xml");
		var_Dump($buff);
		if(!$buff)
		{
			return;
		}

		$xml = new XeXmlParser();
		$xmlDoc = $xml->parse($buff);
		return $xmlDoc;
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
