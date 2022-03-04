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
		$sReturnUrl = getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvmarketAdminInsertModInst','module_srl',$nModuleSrl);
		$this->setRedirectUrl($sReturnUrl);
	}
    /**
     * @brief 
     **/
	public function procSvmarketAdminInsertApp() 
	{
		$oArgs = Context::getRequestVars();
        var_dump($oArgs);

        // save representative thumbnail
		// if($this->_g_oNewItemHeader->thumbnail_image['tmp_name']) 
		// {
		// 	$oFileController = &getController('file');
		// 	if(is_uploaded_file($this->_g_oNewItemHeader->thumbnail_image['tmp_name'])) // single upload via web interface mode
		// 	{
		// 		$oFileRst = $oFileController->insertFile($this->_g_oNewItemHeader->thumbnail_image, $this->_g_oNewItemHeader->module_srl, $this->_g_oNewItemHeader->item_srl);
		// 		if(!$oFileRst || !$oFileRst->toBool())
		// 			return $oFileRst;
		// 		$oFileController->setFilesValid($this->_g_oNewItemHeader->item_srl);
		// 		$oArgs->thumb_file_srl = $oFileRst->get('file_srl');
		// 		unset($oFileRst);
		// 		unset($oFileController);
		// 	}
		// 	elseif( $this->_g_oNewItemHeader->thumbnail_image['size'] ) // excel bulk mode
		// 	{
		// 		echo 'yes img->'.$this->_g_oNewItemHeader->thumbnail_image['name'].'<BR>';
		// 		$oFileRst = $oFileController->insertFile($this->_g_oNewItemHeader->thumbnail_image, $this->_g_oNewItemHeader->module_srl, $this->_g_oNewItemHeader->item_srl, 0, true);
		// 		if(!$oFileRst || !$oFileRst->toBool())
		// 			return $oFileRst;
		// 		$oFileController->setFilesValid($this->_g_oNewItemHeader->item_srl);
		// 		$oArgs->thumb_file_srl = $oFileRst->get('file_srl');
		// 		unset($oFileRst);
		// 		unset($oFileController);
		// 	}
		// 	else
		// 	{
		// 		echo 'no img->'.$oArgs->thumbnail_image['name'].'<BR>';
		// 		$oArgs->thumb_file_srl = 0;
		// 	}
		// }
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
		$nAppSrl = $oDB->db_insert_id();
		unset($oArgs);
        unset($oInsertRst);
        unset($oInsertRst);
		$this->setMessage('success_registed');
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON')))
		{
			$sReturnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module',Context::get('module'),'act','dispSvmarketAdminInsertApp','module_srl',Context::get('module_srl'),'app_srl',$nAppSrl);
			$this->setRedirectUrl($sReturnUrl);
			return;
		}
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
