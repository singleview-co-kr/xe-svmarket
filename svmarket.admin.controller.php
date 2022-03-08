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
	public function procSvmarketAdminInsertMod()
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
	 * @brief mid delete
	 **/
	public function procSvmarketAdminDeleteMod()
	{
		$nModuleSrl = Context::get('module_srl');
		$oModuleController = &getController('module');
		$oRst = $oModuleController->deleteModule($nModuleSrl);
		if(!$oRst->toBool())
			return $oRst;
		unset($oRst);
		unset($oModuleController);
		$this->setMessage('success_deleted');
		$sReturnUrl = getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvmarketAdminModList', 'page', Context::get('page'));
		$this->setRedirectUrl($sReturnUrl);
	}
	/**
     * @brief insert Package
     **/
	public function procSvmarketAdminInsertPkg() 
	{
		$oArgs = Context::getRequestVars();
		require_once(_XE_PATH_.'modules/svmarket/svmarket.pkg_admin.php');
		$oPkgAdmin = new svmarketPkgAdmin();
		$oInsertRst = $oPkgAdmin->create($oArgs);
		if(!$oInsertRst->toBool())
		{
			unset($oArgs);
			unset($oPkgAdmin);
			return $oInsertRst;
		}
		$nPkgSrl = $oInsertRst->get('nPkgSrl');
        $this->setMessage('success_registed');
		unset($oArgs);
		unset($oPkgAdmin);
		unset($oInsertRst);
		if(!in_array(Context::getRequestMethod(),['XMLRPC','JSON']))
		{
			$sReturnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module',Context::get('module'),'act','dispSvmarketAdminInsertPkg','module_srl',Context::get('module_srl'),'package_srl',$nPkgSrl);
			$this->setRedirectUrl($sReturnUrl);
			return;
		}
	}
	/**
     * @brief update Package
     **/
	public function procSvmarketAdminUpdatePkg() 
	{
		$oArgs = Context::getRequestVars();
		$oParams = new stdClass();
		if($oArgs->package_srl)
		{
			$nPkgSrl = $oArgs->package_srl;
			$oParams->package_srl = $oArgs->package_srl;
		}
		else
			return new BaseObject(-1,'msg_invalid_pkg_request');
		require_once(_XE_PATH_.'modules/svmarket/svmarket.pkg_admin.php');
		$oPkgAdmin = new svmarketPkgAdmin();
		$oParams->mode = 'retrieve';
		$oTmpRst = $oPkgAdmin->loadHeader($oParams);
		if(!$oTmpRst->toBool())
			return new BaseObject(-1,'msg_invalid_pkg_request');
		unset($oTmpRst);
		$oUpdateRst = $oPkgAdmin->update($oArgs);
		if(!$oUpdateRst->toBool())
		{
			unset($oArgs);
			unset($oPkgAdmin);
			return $oUpdateRst;
		}
        $this->setMessage('success_registed');
		unset($oArgs);
		unset($oPkgAdmin);
		unset($oUpdateRst);
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON')))
		{
			$sReturnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module',Context::get('module'),'act','dispSvmarketAdminUpdatePkg','module_srl',Context::get('module_srl'),'package_srl',$nPkgSrl);
			$this->setRedirectUrl($sReturnUrl);
			return;
		}
	}
    /**
     * @brief insert App
     **/
	public function procSvmarketAdminInsertApp() 
	{
		$oArgs = Context::getRequestVars();
		require_once(_XE_PATH_.'modules/svmarket/svmarket.app_admin.php');
		$oAppAdmin = new svmarketAppAdmin();
		$oInsertRst = $oAppAdmin->create($oArgs);
		if(!$oInsertRst->toBool())
		{
			unset($oArgs);
			unset($oAppAdmin);
			return $oInsertRst;
		}
		$nAppSrl = $oInsertRst->get('nAppSrl');
        $this->setMessage('success_registed');
		unset($oArgs);
		unset($oAppAdmin);
		unset($oInsertRst);
		if(!in_array(Context::getRequestMethod(),['XMLRPC','JSON']))
		{
			$sReturnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module',Context::get('module'),'act','dispSvmarketAdminInsertApp','module_srl',Context::get('module_srl'),'package_srl',Context::get('package_srl'),'app_srl',$nAppSrl);
			$this->setRedirectUrl($sReturnUrl);
			return;
		}
	}
	/**
     * @brief update App
     **/
	public function procSvmarketAdminUpdateApp() 
	{
		$oArgs = Context::getRequestVars();
		$oParams = new stdClass();
		if($oArgs->package_srl)
			$nPkgSrl = $oArgs->package_srl;
		else
			return new BaseObject(-1,'msg_invalid_app_request');
		if($oArgs->app_srl)
		{
			$nAppSrl = $oArgs->app_srl;
			$oParams->app_srl = $nAppSrl;
		}
		else
			return new BaseObject(-1,'msg_invalid_app_request');
		require_once(_XE_PATH_.'modules/svmarket/svmarket.app_admin.php');
		$oAppAdmin = new svmarketAppAdmin();
		$oTmpRst = $oAppAdmin->loadHeader($oParams);
		if(!$oTmpRst->toBool())
			return new BaseObject(-1,'msg_invalid_app_request');
		unset($oTmpRst);
		$oUpdateRst = $oAppAdmin->update($oArgs);
		if(!$oUpdateRst->toBool())
		{
			unset($oArgs);
			unset($oAppAdmin);
			return $oUpdateRst;
		}
        $this->setMessage('success_registed');
		unset($oArgs);
		unset($oAppAdmin);
		unset($oUpdateRst);
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON')))
		{
			$sReturnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module',Context::get('module'),'act','dispSvmarketAdminUpdateApp','module_srl',Context::get('module_srl'),'package_srl',$nPkgSrl,'app_srl',$nAppSrl);
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
