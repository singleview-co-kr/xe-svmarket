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
			$sReturnUrl = Context::get('success_return_url') ? 
                Context::get('success_return_url') : 
                getNotEncodedUrl('', 'module',Context::get('module'),'act','dispSvmarketAdminUpdatePkg',
                'module_srl',Context::get('module_srl'),'package_srl',$nPkgSrl);
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
			$sReturnUrl = Context::get('success_return_url') ? 
                Context::get('success_return_url') : 
                getNotEncodedUrl('', 'module',Context::get('module'),'act','dispSvmarketAdminUpdateApp',
                'module_srl',Context::get('module_srl'),'package_srl',Context::get('package_srl'),'app_srl',$nAppSrl);
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
		if(!$oArgs->package_srl || !$oArgs->app_srl)
			return new BaseObject(-1,'msg_invalid_app_request');
        $oParams = new stdClass();
        $oParams->app_srl = $oArgs->app_srl;
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
        unset($oUpdateRst);
        $this->setMessage('success_registed');
		
        if($oArgs->version_version && $oArgs->version_zip_file)
		{
			$oVersionParam = new stdClass();
			$oVersionParam->module_srl = $oArgs->module_srl;
            $oVersionParam->package_srl = $oArgs->package_srl;
            $oVersionParam->app_srl = $oArgs->app_srl;
			$oVersionParam->version = $oArgs->version_version;
			$oVersionParam->zip_file = $oArgs->version_zip_file;
			require_once(_XE_PATH_.'modules/svmarket/svmarket.version_admin.php');
			$oVersionAdmin = new svmarketVersionAdmin();
			$oInsertRst = $oVersionAdmin->create($oVersionParam);
            if(!$oInsertRst->toBool())
			{
				unset($oVersionParam);
				unset($oVersionAdmin);
				return $oInsertRst;
			}
			unset($oVersionParam);
			unset($oVersionAdmin);
            // update app updateteim
            $oUpdateRst = $oAppAdmin->updateTimestamp();
            if(!$oUpdateRst->toBool())
				return $oUpdateRst;
            // update package updateteim
		}
        unset($oAppAdmin);
        unset($oArgs);
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON')))
		{
			$sReturnUrl = Context::get('success_return_url') ? 
                Context::get('success_return_url') : 
                getNotEncodedUrl('', 'module',Context::get('module'),'act','dispSvmarketAdminUpdateApp',
                'module_srl',Context::get('module_srl'),'package_srl',Context::get('package_srl'),'app_srl',Context::get('app_srl'));
			$this->setRedirectUrl($sReturnUrl);
			return;
		}
	}
    /**
     * @brief update version
     **/
	public function procSvmarketAdminUpdateVersion() 
	{
		$oArgs = Context::getRequestVars();
		$oParams = new stdClass();
		if($oArgs->package_srl)
			$nPkgSrl = $oArgs->package_srl;
		else
			return new BaseObject(-1,'msg_invalid_version_request');
		if($oArgs->app_srl)
			$nAppSrl = $oArgs->app_srl;
		else
			return new BaseObject(-1,'msg_invalid_version_request');
        if($oArgs->version_srl)
        {
            $nVersionSrl = $oArgs->version_srl;
            $oParams->version_srl = $nVersionSrl;
        }
        else
            return new BaseObject(-1,'msg_invalid_version_request');
		require_once(_XE_PATH_.'modules/svmarket/svmarket.version_admin.php');
		$oVersionAdmin = new svmarketVersionAdmin();
		$oTmpRst = $oVersionAdmin->loadHeader($oParams);
        unset($oParams);
		if(!$oTmpRst->toBool())
			return new BaseObject(-1,'msg_invalid_app_request');
		unset($oTmpRst);
		$oUpdateRst = $oVersionAdmin->update($oArgs);
        // var_dump($oUpdateRst);
        // exit;


		if(!$oUpdateRst->toBool())
		{
			unset($oArgs);
			unset($oVersionAdmin);
			return $oUpdateRst;
		}
        $this->setMessage('success_registed');
		unset($oArgs);
		unset($oVersionAdmin);
		unset($oUpdateRst);
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON')))
		{
			$sReturnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module',Context::get('module'),'act','dispSvmarketAdminUpdateVersion','module_srl',Context::get('module_srl'),'package_srl',$nPkgSrl,'app_srl',$nAppSrl,'version_srl',$nVersionSrl);
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
}
/* End of file svmarket.admin.controller.php */
/* Location: ./modules/svmarket/svmarket.admin.controller.php */
