<?php
/* Copyright (C) singleview.co.kr <http://singleview.co.kr> */
/**
 * @class  svmarketAdminController
 * @author singleview.co.kr (root@singleview.co.kr)
 * @brief module admin controller class
 */
class svmarketAdminController extends svmarket
{
	/**
	 * @brief Initialization
	 */
	function init()
	{
	}
	/**
	 * @brief mid 생성하거나 변경
	 **/
	public function procSvmarketAdminInsertModInst()
	{
		$oArgs = Context::getRequestVars();
		$oArgs->module = 'svmarket';
		// module_srl의 값에 따라 insert
		if(!$oArgs->module_srl) 
		{
			$oModuleController = &getController('module');
			$oRst = $oModuleController->insertModule($oArgs);
			$nModuleSrl = $oRst->get('module_srl');
			$sMsgCode = 'success_registed';
		}
		else //update
		{
			$oRst = $this->_updateMidLevelConfig($oArgs);
			$nModuleSrl = $oArgs->module_srl;
			$sMsgCode = 'success_updated';
		}
		if(!$oRst->toBool())
			return $oRst;
		unset($oRst);
		unset($oArgs);
		$this->add('module_srl', $nModuleSrl);
		$this->setMessage($sMsgCode);
		$sReturnUrl = getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvmarketAdminInsertMod','module_srl',$nModuleSrl);
		$this->setRedirectUrl($sReturnUrl);
	}
    /**
     * @brief 
     **/
	public function procSvmarketAdminInsertApp() 
	{
		$oArgs = Context::getRequestVars();

        $oParam = new stdClass();
        $oParam->module_srl = $oArgs->module_srl;
        $oParam->title = $oArgs->app_title;
        $oParam->thumb_file_srl = $oArgs->thumb_file_srl;
        $oParam->description = $oArgs->app_desc;
        $oParam->github_url = $oArgs->app_github_url;
        $oParam->homepage = $oArgs->app_homepage;
		$oInsertRst = executeQuery('svmarket.insertApp', $oParam);
		if(!$oInsertRst->toBool())
        {
            unset($oInsertRst);
			return $oInsertRst;
        }
        $oDB = DB::getInstance();
		$nPkgSrl = $oDB->db_insert_id();
        unset($oInsertRst);
        unset($oParam);
        // save representative thumbnail
        if($oArgs->thumbnail_image['tmp_name'])
        {
			$oFileController = &getController('file');
			if(is_uploaded_file($oArgs->thumbnail_image['tmp_name'])) // single upload via web interface mode
			{
				$oFileRst = $oFileController->insertFile($oArgs->thumbnail_image, $oArgs->module_srl, $nPkgSrl);
				if(!$oFileRst || !$oFileRst->toBool())
					return $oFileRst;
				$oFileController->setFilesValid($this->_g_oNewItemHeader->item_srl);
				$nThumbFileSrl = $oFileRst->get('file_srl');
				unset($oFileRst);
			}
            else
			{
				echo 'no img->'.$oArgs->thumbnail_image['name'].'<BR>';
				$nThumbFileSrl = 0;
			}
            unset($oFileController);
		}
		unset($oArgs);

        var_dump($nThumbFileSrl);
        $oParam = new stdClass();
        $oParam->pkg_srl = $nPkgSrl;
        $oParam->thumb_file_srl = $nThumbFileSrl;
		$oUpdateRst = executeQuery('svmarket.updateApp', $oParam);
        if(!$oUpdateRst->toBool())
        {
            unset($oUpdateRst);
			return $oUpdateRst;
        }
        unset($oUpdateRst);
        unset($oParam);
        $this->setMessage('success_registed');
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON')))
		{
			$sReturnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module',Context::get('module'),'act','dispSvmarketAdminInsertApp','module_srl',Context::get('module_srl'),'pkg_srl',$nPkgSrl);
			$this->setRedirectUrl($sReturnUrl);
			return;
		}
	}
	/**
	* @brief update mid level config
	* procSvitemAdminInsertModInst 와 병합해야 함
	**/
	private function _updateMidLevelConfig($oArgs)
	{
		if(!$oArgs->module_srl)
			return new BaseObject(-1, 'msg_invalid_module_srl');

		unset($oArgs->module);
		unset($oArgs->error_return_url);
		unset($oArgs->success_return_url);
		unset($oArgs->act);
		unset($oArgs->ext_script);
		unset($oArgs->list);

		$oModuleModel = &getModel('module');
		$oConfig = $oModuleModel->getModuleInfoByModuleSrl($oArgs->module_srl);
		foreach($oArgs as $key=>$val)
			$oConfig->{$key} = $val;
		$oModuleController = &getController('module');
		$oRst = $oModuleController->updateModule($oConfig);
		return $oRst;
	}
    

	/**
	 * @brief Upload attachments
	 */
	function procUploadFile()
	{
		// Basic variables setting
		$upload_target_srl = Context::get('upload_target_srl');
		$module_srl = Context::get('module_srl');
		// Create the controller object file class
		$oFileController = getController('file');
		$output = $oFileController->insertFile($module_srl, $upload_target_srl);
		// Attachment to the output of the list, java script
		$oFileController->printUploadedFileList($upload_target_srl);
	}

	/**
	 * @brief Delete the attachment
	 * Delete individual files in the editor using
	 */
	function procDeleteFile()
	{
		// Basic variable setting(upload_target_srl and module_srl set)
		$upload_target_srl = Context::get('upload_target_srl');
		$module_srl = Context::get('module_srl');
		$file_srl = Context::get('file_srl');
		// Create the controller object file class
		$oFileController = getController('file');
		if($file_srl) $output = $oFileController->deleteFile($file_srl, $this->grant->manager);
		// Attachment to the output of the list, java script
		$oFileController->printUploadedFileList($upload_target_srl);
	}
}
/* End of file svmarket.admin.controller.php */
/* Location: ./modules/svmarket/svmarket.admin.controller.php */
