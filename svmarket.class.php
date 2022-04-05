<?php
/* Copyright (C) singleview.co.kr <http://singleview.co.kr> */
/**
 * @class  svmarket
 * @author singleview.co.kr (root@singleview.co.kr)
 * @brief high class of the module svmarket
 */
class svmarket extends ModuleObject
{
	const S_NULL_SYMBOL = '|@|'; // ./svmarket.pkg_admin.php, svmarket.pkg_consumer.php에서 사용
	
	/**
	 * @brief Implement if additional tasks are necessary when installing
	 */
	function moduleInstall()
	{
	}
	/**
	 * @brief a method to check if successfully installed
	 */
	function checkUpdate()
	{
	}
	/**
	 * @brief Execute update
	 */
	function moduleUpdate()
	{
	}
	/**
	 * @brief Re-generate the cache file
	 */
	function recompileCache()
	{
	}
    /**
	 * @brief Re-generate the cache file
	 */
	public static function getFileExt($sFileName)
	{
        $aFileInfo = explode('.', $sFileName);
        return array_pop($aFileInfo);
	}
}
/* End of file svmarket.class.php */
/* Location: ./modules/svmarket/svmarket.class.php */