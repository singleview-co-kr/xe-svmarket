<?php
/**
 * @class  svsvmarketAppAdmin
 * @author singleview(root@singleview.co.kr)
 * @brief  svsvmarketAppAdmin class
 */
class svmarketAppAdmin extends svmarket
{
	// private $_g_oSvmarketModuleConfig = NULL; // svmarket module config 적재
	private $_g_oLoggedInfo = NULL;
	private $_g_oOldAppHeader = NULL; // 정보 수정할 때 과거 상태에 관한 참조일 뿐
	private $_g_oNewAppHeader = NULL; // 항상 현재 쓰기의 기준
	const A_APP_HEADER_TYPE = ['_g_oNewAppHeader', '_g_oOldAppHeader'];
    const A_APP_TYPE = ['core'=>1, 'module'=>2, 'addon'=>3, 'widget'=>4, 'layout'=>5, 'm.layout'=>6, 'module/skin'=>7, 'module/m.skin'=>8];
/**
 * @brief 생성자
 * $oParams->oSvitemConfig
 **/
	public function __construct($oParams=null)
	{
		$this->_g_oLoggedInfo = Context::get('logged_info');
		$this->_setSkeletonHeader();
	}
/**
 * @brief 
 **/
	public function __get($sName) 
	{
		if($sName == 'nModuleSrl')
			return $this->_g_oOldAppHeader->module_srl; // [module_srl] attr은 Context 클래스를 통과하면서 전달되지 않는 것 같음
		if(isset($this->_g_oOldAppHeader->{$sName}))
		{
			if($this->_g_oOldAppHeader->{$sName} == svmarket::S_NULL_SYMBOL)
				return null;
			else
				return $this->_g_oOldAppHeader->{$sName};
		}
		else
		{
			debugPrint($sName);
			trigger_error('Undefined property or method: '.$sName);
            return null;
		}
	}
/**
 * @brief
 **/
 	public function __set($name, $value) 
	{
		if(property_exists($this, $name))
		{
			$this->{$name} = $value;
			return;
		}
		$method_name = "set_{$name}";
		if(method_exists($this, $method_name)) 
		{
			$this->{$method_name}($value);
			return;
		}
	    trigger_error("Undefined property $name or method $method_name");
	}
    /**
     * @brief set skeleton svmarket header
     * svmarket.pkg_consumer.php::_setSkeletonHeader()과 통일성 유지
     **/
    private function _setSkeletonHeader()
    {
        $aBasicAttr = ['app_srl', 'module_srl', 'package_srl', 'list_order',
                        'category_node_srl', 'type_srl',
                        'name', 'title', 'install_path', 'thumb_file_srl',
                        'og_description', 'description',
						'github_url', 'homepage', 'tags', 'display', 'updatetime', 'regdate'];
        $aInMemoryAttr = ['package_title', 'type_name', 'version_list'];
        $aTempAttr = ['thumbnail_image'];
        foreach(self::A_APP_HEADER_TYPE as $nTypeIdx => $sHeaderType)
        {
            $this->{$sHeaderType} = new stdClass();
            foreach($aBasicAttr as $nAttrIdx => $sAttrName)
                $this->{$sHeaderType}->{$sAttrName} = svmarket::S_NULL_SYMBOL;
            foreach($aInMemoryAttr as $nAttrIdx => $sAttrName)
                $this->{$sHeaderType}->{$sAttrName} = svmarket::S_NULL_SYMBOL;
            // temp item info for insertion
			foreach($aTempAttr as $nAttrIdx => $sAttrName)
                $this->{$sHeaderType}->{$sAttrName} = svitem::S_NULL_SYMBOL;
        }
        unset($aBasicAttr);
        unset($aInMemoryAttr);
    }
/**
 * @brief 신규 앱 생성
 **/
	public function create($oNewAppArgs)
	{
		$this->_initHeader();
		$this->_matchNewPkgInfo($oNewAppArgs);
		if($this->_g_oNewAppHeader->package_srl == svmarket::S_NULL_SYMBOL || 
            $this->_g_oNewAppHeader->module_srl == svmarket::S_NULL_SYMBOL || 
			$this->_g_oNewAppHeader->title == svmarket::S_NULL_SYMBOL)
			return new BaseObject(-1,'msg_invalid_request');
        // begin - check app duplication
        $oTmpArgs = new stdClass();
        $oTmpArgs->type_srl = $this->_g_oNewAppHeader->type_srl;
        $oTmpArgs->name = $this->_g_oNewAppHeader->name;
        $oTmpRst = executeQuery('svmarket.getAdminAppUniqueness', $oTmpArgs);
        unset($oTmpArgs);
        if(!$oTmpRst->toBool())
            return $oTmpRst;
        if(!is_object($oTmpRst->data) || $oTmpRst->data->count > 0) 
            return new BaseObject(-1,'msg_duplicate_app_type_name_request');
        unset($oTmpRst);
        // end - check app duplication
		return $this->_insertApp();
	}
    /**
 * @brief 기존 앱 정보 적재
 **/
	public function loadHeader($oParams)
	{
		$this->_initHeader();
		$oTmpArgs = new stdClass();
		$oTmpArgs->app_srl = $oParams->app_srl;
		$oTmpRst = executeQuery('svmarket.getAdminAppDetail', $oTmpArgs);
		unset($oTmpArgs);
		if(!$oTmpRst->toBool())
			return $oTmpRst;
		if(!is_object($oTmpRst->data))
			return new BaseObject(-1,'msg_invalid_app_request');
					
		$this->_matchOldAppInfo($oTmpRst->data);
		//$this->_setReviewCnt(); // 후기수 설정
		$oModuleModel = getModel('module');
		$oModuleInfo = $oModuleModel->getModuleInfoByModuleSrl($this->_g_oOldAppHeader->module_srl);
		$this->_g_oOldAppHeader->mid = $oModuleInfo->mid;
		unset($oModuleModel);
		unset($oModuleInfo);
		return $oTmpRst;
	}
	/**
	* @brief 기존 앱 설치 경로 생성
	**/
	public function _getAppTypeInfo()
	{
		$sAppType = 'invalid';
		if($this->_g_oOldAppHeader->type_srl)
			$sAppType = array_search($this->_g_oOldAppHeader->type_srl, self::A_APP_TYPE);
		$sInstallPath = 'unknown';
		switch($sAppType)
		{
			case 'core':
				$sInstallPath = '/';
				break;
			case 'module':
			case 'addon':
			case 'widget':
			case 'layout':
			case 'm.layout':
				$sInstallPath = '/'.$sAppType.'s/'.$this->_g_oOldAppHeader->name;
				break;
			case 'module/skin':
			case 'module/m.skin':
				$aAppName = explode('/',$this->_g_oOldAppHeader->name);
				$aAppType = explode('/',$sAppType);
				$sInstallPath = '/modules/'.$aAppName[0].'/'.$aAppType[1].'s/'.$aAppName[1];
				break;
			default:
				var_dump($sAppType);
				break;
		}
		return ['sAppType'=>$sAppType, 'sInstallPath'=>$sInstallPath];
	}
/**
* @brief 기존 앱 상세 정보 적재
**/
	public function loadDetail()
	{
		$this->_nullifyHeader();
        // begin - breadcrumb info
        $oTmpArgs = new stdClass();
		$oTmpArgs->package_srl = $this->_g_oOldAppHeader->package_srl;
		$oTmpRst = executeQuery('svmarket.getAdminPkgDetail', $oTmpArgs);
        unset($oTmpArgs);
		if(!$oTmpRst->toBool())
			return $oTmpRst;
		if(!is_object($oTmpRst->data))
			return new BaseObject(-1,'msg_invalid_pkg_request');
		$this->_g_oOldAppHeader->package_title = $oTmpRst->data->title;
        // end - breadcrumb info

        // begin - load category info
		// if($this->_g_oOldAppHeader->category_node_srl > 0)
		// {
		// 	$oSvmakretModel = &getModel('svmarket');
		// 	$nModuleSrl = $this->_g_oOldAppHeader->module_srl;
		// 	$this->_g_oOldAppHeader->oCatalog = $oSvitemModel->getCatalog($nModuleSrl, $this->_g_oOldAppHeader->category_node_srl);
		// 	if(strlen($this->_g_oOldAppHeader->enhanced_item_info->ga_category_name) == 0)
		// 		$this->_g_oOldAppHeader->enhanced_item_info->ga_category_name = $this->_g_oOldAppHeader->oCatalog->current_catalog_info->node_name;
		// 	unset($nModuleSrl);
		// }
        // end - load category info

		// begin - sns share url
		// $oDocumentModel = getModel('document');
		// $oDocument = $oDocumentModel->getDocument($this->_g_oOldAppHeader->package_srl);
		// // $this->_g_oOldAppHeader->enhanced_item_info->item_brief = $oDocument->getContent(false);
		// $oDbInfo = Context::getDBInfo();
		// $oSnsInfo = new stdClass();
		// $oSnsInfo->sPermanentUrl = $oDocument->getPermanentUrl().'?l='.$oDbInfo->lang_type;
		// $oSnsInfo->sEncodedDocTitle = urlencode($this->_g_oOldAppHeader->title);
		// $this->_g_oOldAppHeader->oSnsInfo = $oSnsInfo;
		// unset($oDbInfo);
		// unset($oDocument);
		// unset($oDocumentModel);
        // end - sns share url

		$this->_g_oOldAppHeader->desc_for_editor = htmlentities($this->_g_oOldAppHeader->description);
        if(!$this->_g_oOldAppHeader->og_description)
			$this->_g_oOldAppHeader->og_description = mb_substr(html_entity_decode(strip_tags($this->_g_oOldAppHeader->description)), 0, 40, 'utf-8');
		if($this->_g_oOldAppHeader->type_srl)
		{
			$aAppInfo = $this->_getAppTypeInfo();
			$this->_g_oOldAppHeader->type_name = $aAppInfo['sAppType'];
			$this->_g_oOldAppHeader->install_path = $aAppInfo['sInstallPath'];
		}

		// begin - load packaged version list
        $oArgs = new stdClass();
        $oArgs->app_srl = $this->_g_oOldAppHeader->app_srl;
        $oListRst = executeQueryArray('svmarket.getAdminVersionList', $oArgs);
        unset($oArgs);
		if(!$oListRst->toBool())
			return $oListRst;
        if(count($oListRst->data))
        {
            require_once(_XE_PATH_.'modules/svmarket/svmarket.version_admin.php');
            $oParams = new stdClass();
            $aPackedVersion = [];
            foreach($oListRst->data as $nIdx=>$oVal)
            {
                $oVersionAdmin = new svmarketVersionAdmin();
                $oParams->version_srl = $oVal->version_srl;
                $oTmpRst = $oVersionAdmin->loadHeader($oParams);
                if(!$oTmpRst->toBool())
                	return new BaseObject(-1,'msg_invalid_version_request');
                $oDetailRst = $oVersionAdmin->loadDetail();
                if(!$oDetailRst->toBool())
                	return $oDetailRst;
                unset($oDetailRst);
                $aPackedVersion[$nIdx] = $oVersionAdmin;
            }
            $this->_g_oOldAppHeader->version_list = $aPackedVersion;
        }
        else
            $this->_g_oOldAppHeader->version_list = [];
        // end - load packaged version list
		return new BaseObject();
	}
    /**
     * @brief 기존 앱 정보 변경
     **/
	public function update($oPkgArgs)
	{
		if(!$this->_g_oOldAppHeader)
			return new BaseObject(-1,'msg_required_to_load_old_information_first');
		$this->_matchNewPkgInfo($oPkgArgs);
		if($this->_g_oNewAppHeader->package_srl == -1)
			return new BaseObject(-1,'msg_invalid_request');
		
		// 고정값은 외부 쿼리로 변경하지 않음
		$this->_g_oNewAppHeader->package_srl = $this->_g_oOldAppHeader->package_srl;
		if($this->_g_oNewAppHeader->module_srl == svmarket::S_NULL_SYMBOL)
			$this->_g_oNewAppHeader->module_srl = $this->_g_oOldAppHeader->module_srl;
		return $this->_updateApp();
	}
    /**
     * @brief 기존 앱 갱신일 변경
     **/
	public function updateTimestamp()
	{
		if(!$this->_g_oOldAppHeader)
			return new BaseObject(-1,'msg_required_to_load_old_information_first');
        $oArgs = new stdClass();
        $oArgs->app_srl = $this->_g_oNewAppHeader->app_srl; 
        $oUpdateRst = executeQuery('svmarket.updateAdminAppTimestamp', $oArgs);
        unset($oArgs);
        return $oUpdateRst;
	}
    /**
     * @brief 앱 유형 출력
     **/
	public function getAppType()
	{
        return self::A_APP_TYPE;
	}
    /**
     * @brief 헤더 초기화
     **/
    private function _initHeader()
    {
        foreach($this->_g_oNewAppHeader as $sTitle => $sVal)
            $this->_g_oNewAppHeader->$sTitle = svmarket::S_NULL_SYMBOL;
        foreach($this->_g_oOldAppHeader as $sTitle => $sVal)
            $this->_g_oOldAppHeader->$sTitle = svmarket::S_NULL_SYMBOL;
    }
    /**
     * @brief 
     **/
    private function _insertApp()
    {
        // begin - retrieve package title // admin.controller로 뺴내야 함
        require_once(_XE_PATH_.'modules/svmarket/svmarket.pkg_admin.php');
        $oPkgAdmin = new svmarketPkgAdmin();
        $oParams = new stdClass();
        $oParams->package_srl = $this->_g_oNewAppHeader->package_srl;
        $oParams->mode = 'retrieve';
        $oTmpRst = $oPkgAdmin->loadHeader($oParams);
        if(!$oTmpRst->toBool())
            return new BaseObject(-1,'msg_invalid_pkg_request');
        unset($oTmpRst);
        $oDetailRst = $oPkgAdmin->loadDetail();
        if(!$oDetailRst->toBool())
            return $oDetailRst;
        unset($oDetailRst);
        $sPackageTitle = $oPkgAdmin->title;
        unset($oPkgAdmin);
        // end - retrieve package title 

        $this->_g_oNewAppHeader->app_srl = getNextSequence();
        $oDocArgs = new stdClass();
        $oDocArgs->document_srl = $this->_g_oNewAppHeader->app_srl;
        $oDocArgs->module_srl = $this->_g_oNewAppHeader->module_srl;
        $oDocArgs->content = $this->_g_oNewAppHeader->title.' 앱의 상세페이지 - '.$sPackageTitle.' 패키지';
        $oDocArgs->title = $this->_g_oNewAppHeader->title.' 앱의 상세페이지 - '.$sPackageTitle.' 패키지';
        $oDocArgs->list_order = $this->_g_oNewAppHeader->app_srl * -1;
        $oDocArgs->tags = Context::get('tag');
        $oDocArgs->allow_comment = 'Y';
        $oDocumentController = &getController('document');
        $oDocRst = $oDocumentController->insertDocument($oDocArgs);
        if(!$oDocRst->toBool())
            return $oDocRst;
        unset($oDocumentController);
        unset($oDocRst);
        unset($oDocArgs);
        
        $this->_nullifyHeader();
        // var_dump($this->_g_oNewAppHeader);
        // exit;
        $oParam = new stdClass();
        $oParam->app_srl = $this->_g_oNewAppHeader->app_srl;
        $oParam->package_srl = $this->_g_oNewAppHeader->package_srl;
        $oParam->module_srl = $this->_g_oNewAppHeader->module_srl;
        $oParam->type_srl = $this->_g_oNewAppHeader->type_srl;
        $oParam->title = $this->_g_oNewAppHeader->title;
        $oParam->name = $this->_g_oNewAppHeader->name;
        $oParam->og_description = $this->_g_oNewAppHeader->og_description;
		$oParam->description = $this->_g_oNewAppHeader->description;
		$oParam->github_url = $this->_g_oNewAppHeader->github_url;
        $oParam->homepage = $this->_g_oNewAppHeader->homepage;
        $oParam->display = 'N'; // 최초 등록 시에는 기본 최소 정보이므로 무조건 비공개
        $oParam->list_order = $this->_g_oNewAppHeader->package_srl * -1;
        
        // save representative thumbnail
        if($this->_g_oNewAppHeader->thumbnail_image['tmp_name']) 
        {
            if(is_uploaded_file($this->_g_oNewAppHeader->thumbnail_image['tmp_name'])) // single upload via web interface mode
            {
                $oFileController = getController('file');
                $oFileRst = $oFileController->insertFile($this->_g_oNewAppHeader->thumbnail_image, $this->_g_oNewAppHeader->module_srl, $this->_g_oNewAppHeader->app_srl);
                if(!$oFileRst || !$oFileRst->toBool())
                    return $oFileRst;
                $oFileController->setFilesValid($this->_g_oNewAppHeader->app_srl);
                $oParam->thumb_file_srl = $oFileRst->get('file_srl');
                unset($oFileRst);
                unset($oFileController);
            }
            else
            {
                echo 'no img->'.$oArgs->thumbnail_image['name'].'<BR>';
                $oParam->thumb_file_srl = 0;
            }
        }
        // var_dump($oParam);
        // exit;
        $oInsertRst = executeQuery('svmarket.insertAdminApp', $oParam);
        // var_dump($oInsertRst);
        // exit;
        if(!$oInsertRst->toBool())
        {
            unset($oParam);
            return $oInsertRst;
        }
        unset($oParam);
        $oInsertRst->add('nAppSrl', $this->_g_oNewAppHeader->app_srl);
        return $oInsertRst;
    }
	/**
	 * @brief 
	 **/
	private function _updateApp()
	{
		$this->_nullifyHeader();
		// begin - app info modification
		$oArgs = new stdClass();
		$oArgs->app_srl = $this->_g_oOldAppHeader->app_srl; // app_srl은 수정하면 안됨
		if($this->_g_oNewAppHeader->module_srl)
			$oArgs->module_srl = $this->_g_oNewAppHeader->module_srl;
		if($this->_g_oNewAppHeader->list_order)
			$oArgs->list_order = $this->_g_oNewAppHeader->list_order;
		if($this->_g_oNewAppHeader->category_node_srl)
			$oArgs->category_node_srl = $this->_g_oNewAppHeader->category_node_srl;
		if($this->_g_oNewAppHeader->type_srl)
			$oArgs->type_srl = $this->_g_oNewAppHeader->type_srl;
		if($this->_g_oNewAppHeader->name)
			$oArgs->name = $this->_g_oNewAppHeader->name;
		if($this->_g_oNewAppHeader->title)
			$oArgs->title = $this->_g_oNewAppHeader->title;
        if($this->_g_oNewAppHeader->thumb_file_srl)
			$oArgs->thumb_file_srl = $this->_g_oNewAppHeader->thumb_file_srl;
		if($this->_g_oNewAppHeader->og_description)
			$oArgs->og_description = $this->_g_oNewAppHeader->og_description;
		if($this->_g_oNewAppHeader->description)
			$oArgs->description = $this->_g_oNewAppHeader->description;
		if($this->_g_oNewAppHeader->homepage)
			$oArgs->homepage = $this->_g_oNewAppHeader->homepage;
		if($this->_g_oNewAppHeader->tags)
			$oArgs->tags = $this->_g_oNewAppHeader->tags;
		if($this->_g_oNewAppHeader->display)
			$oArgs->display = $this->_g_oNewAppHeader->display;
		// var_dump($oArgs);
		// exit;
		$oUpdateRst = executeQuery('svmarket.updateAdminApp', $oArgs);
		unset($oArgs);
		if(!$oUpdateRst->toBool())
			return $oUpdateRst;
		// 첨부 이미지 파일 처리
		//$oUpdateRst = $this->_procThumbnailImages();
		// end - app info modification
		// if($this->_g_oNewAppHeader->version && $this->_g_oNewAppHeader->version_zip_file)
		// {
		// 	$oVersionParam = new stdClass();
		// 	$oVersionParam->app_srl = $this->_g_oNewAppHeader->app_srl;
		// 	$oVersionParam->module_srl = $this->_g_oNewAppHeader->module_srl;
		// 	$oVersionParam->package_srl = $this->_g_oNewAppHeader->package_srl;
		// 	$oVersionParam->version = $this->_g_oNewAppHeader->version;
		// 	$oVersionParam->version_zip_file = $this->_g_oNewAppHeader->version_zip_file;

		// 	require_once(_XE_PATH_.'modules/svmarket/svmarket.version_admin.php');
		// 	$oVersionAdmin = new svmarketVersionAdmin();
		// 	$oInsertRst = $oVersionAdmin->create($oVersionParam);
		// 	if(!$oInsertRst->toBool())
		// 	{
		// 		unset($oVersionParam);
		// 		unset($oVersionAdmin);
		// 		return $oInsertRst;
		// 	}
		// 	unset($oVersionParam);
		// 	unset($oVersionAdmin);
		// }
		return $oUpdateRst;
	}
    /**
     * @brief 
     **/
	private function _matchNewPkgInfo($oNewAppArgs)
	{
		$aIgnoreVar = array('version_version', 'version_zip_file', 'error_return_url', 'ruleset', 'module', 'mid', 'act');
		$aCleanupVar = array('title');
		foreach($oNewAppArgs as $sTitle => $sVal)
		{
            if($sTitle!='app_srl')
                $sTitle = str_replace('app_', '', $sTitle);
            if(in_array($sTitle, $aIgnoreVar)) 
				continue;
            if(in_array($sTitle, $aCleanupVar))
				$sVal = trim(strip_tags($sVal));
            if($this->_g_oNewAppHeader->$sTitle == svmarket::S_NULL_SYMBOL)
				$this->_g_oNewAppHeader->$sTitle = $sVal;
			else
			{
//////////////// for debug only
				if(is_object($sVal))
				{
					var_dump('weird: '.$sTitle);
					echo '<BR>';
					var_dump($sVal);
					echo '<BR>';
				}
				else
				{
					var_dump('1weird: '.$sTitle.' => '. $sVal);
					echo '<BR>';
				}
//////////////// for debug only
			}
		}
	}
    /**
	 * @brief 
	 **/
	private function _matchOldAppInfo($oPkgmArgs)
	{
		$aIgnoreVar = array('module', 'mid', 'act');
		foreach($oPkgmArgs as $sTitle => $sVal)
		{
			if(in_array($sTitle, $aIgnoreVar)) 
				continue;
			if($this->_g_oOldAppHeader->{$sTitle} == svmarket::S_NULL_SYMBOL)
			{
				if($sVal)
					$this->_g_oOldAppHeader->{$sTitle} = $sVal;
			}
			else
			{
	//////////////// for debug only
				if(is_object($sVal))
				{
					var_dump('weird: '.$sTitle);
					echo '<BR>';
					var_dump($sVal);
					echo '<BR>';
				}
				else
				{
					var_dump('2weird: '.$sTitle.' => '. $sVal);
					echo '<BR>';
				}
	//////////////// for debug only
			}
		}
	}
    /**
     * @brief 저장 명령을 실행하기 위해 값 할당 후에도 svmarket::S_NULL_SYMBOL이면 null로 변경
     **/
    private function _nullifyHeader()
    {
        foreach($this->_g_oNewAppHeader as $sTitle => $sVal)
        {
            if($sVal == svmarket::S_NULL_SYMBOL)
                $this->_g_oNewAppHeader->$sTitle = null;
        }
		foreach($this->_g_oOldAppHeader as $sTitle => $sVal)
        {
            if($sVal == svmarket::S_NULL_SYMBOL)
                $this->_g_oOldAppHeader->$sTitle = null;
        }
    }
    /**
     * @brief 기존 앱 정보 비활성화
     * module_srl을 0으로 바꿔서 상품 관리자에서 검색되지 않게함
     **/
	public function deactivate()
	{
		if( !$this->_g_oOldItemHeader )
			return new BaseObject(-1,'msg_required_to_load_old_information_first');
		if( $this->_g_oOldItemHeader->item_srl == svmarket::S_NULL_SYMBOL )
			return new BaseObject(-1,'msg_invalid_request');
		
		$oArgs->item_srl = $this->_g_oOldItemHeader->item_srl;
		$oArgs->module_srl = 0; // 소속 모듈을 0으로 설정하여 검색되지 않게 함
		$oRst = executeQuery('svmarket.updateAdminItemDeactivated', $oArgs);
		if(!$oRst->toBool())
			return $oRst;
		unset($oRst);
		return new BaseObject();
	}
/**
 * @brief 앱 영구 삭제; 코드 블록만 유지하고 이 메소드의 접근을 차단함
 **/
	public function remove()
	{
		if( !$this->_g_oOldItemHeader )
			return new BaseObject(-1,'msg_required_to_load_old_information_first');
		if( $this->_g_oOldItemHeader->item_srl == svmarket::S_NULL_SYMBOL )
			return new BaseObject(-1,'msg_invalid_request');

		// delete related file
		$oFileController = &getController('file');
		$oFileController->deleteFile($this->_g_oOldItemHeader->thumb_file_srl);
		$oFileController->deleteFile($this->_g_oOldItemHeader->gallery_doc_srl);
		$oFileController->deleteFile($this->_g_oOldItemHeader->mob_doc_srl);
		$oFileController->deleteFile($this->_g_oOldItemHeader->pc_doc_srl);
		unset($oFileController);
		
		// delete document
		$oDocumentController = &getController('document');
		$oDocumentController->deleteDocument($item_info->document_srl);
		unset($oDocumentController);

		// delete db record
		$oArgs->item_srl = $item_srl;
		$oRst = executeQuery('svmarket.deleteItem', $oArgs);
		if(!$oRst->toBool())
			return $oRst;
		unset($oRst);
		$oRst = executeQuery('svmarket.deleteSvitemExtraVars', $oArgs);
		if(!$oRst->toBool())
			return $oRst;
		unset($oRst);
		unset($oArgs);
		return new BaseObject();
	}
/**
 * @brief svmarket 스킨에서 호출하는 메쏘드
 */	
	public function getThumbnailUrl( $nWidth = 80, $nHeight = 0, $sThumbnailType = 'crop' )
	{
		$sNoimgUrl = Context::getRequestUri().'/modules/svmarket/tpl/img/no_img_80x80.jpg';
		if($this->_g_oOldItemHeader->thumb_file_srl == svmarket::S_NULL_SYMBOL || is_null( $this->_g_oOldItemHeader->thumb_file_srl ) ) // 기본 이미지 반환
			return $sNoimgUrl;
		
		if(!$nHeight)
			$nHeight = $nWidth;
		
		// Define thumbnail information
		$sThumbnailPath = 'files/cache/thumbnails/'.getNumberingPath($this->_g_oOldItemHeader->thumb_file_srl, 3);
		$sThumbnailFile = $sThumbnailPath.$nWidth.'x'.$nHeight.'.'.$sThumbnailType.'.jpg';
		$sThumbnailUrl = Context::getRequestUri().$sThumbnailFile;
		// Return false if thumbnail file exists and its size is 0. Otherwise, return its path
		if(file_exists($sThumbnailFile) && filesize($sThumbnailFile) > 1 ) 
			return $sThumbnailUrl;

		// Target File
		$oFileModel = &getModel('file');
		$sSourceFile = NULL;
		$sFile = $oFileModel->getFile($this->_g_oOldItemHeader->thumb_file_srl);
		if($sFile) 
			$sSourceFile = $sFile->uploaded_filename;

		if($sSourceFile)
			$oOutput = FileHandler::createImageFile($sSourceFile, $sThumbnailFile, $nWidth, $nHeight, 'jpg', $sThumbnailType);

		// Return its path if a thumbnail is successfully genetated
		if($oOutput) 
			return $sThumbnailUrl;
		else
			FileHandler::writeFile($sThumbnailFile, '','w'); // Create an empty file not to re-generate the thumbnail
		return $sNoimgUrl;
	}
/**
 * @brief
 */
	private function _setReviewCnt()
	{
		$nReviewCnt = 0;
		if( $this->_g_oSvitemModuleConfig->connected_review_board_srl > 0 )
		{
			$oDocumentModel = getModel('document');
			$sCategoryContent = $oDocumentModel->getCategoryPhpFile($this->_g_oSvitemModuleConfig->connected_review_board_srl);
			unset($oDocumentModel);
			require($sCategoryContent);	
			foreach( $this->_g_oSvitemModuleConfig->review_for_item[$this->_g_oOldItemHeader->item_srl] as $key=>$val)
			{
				if( $val == 'match' )
					$nReviewCnt += (int)$menu->list[$key]['document_count'];
			}
		}
		$this->_g_oOldItemHeader->review_count = $nReviewCnt;
	}
/**
 * @brief 첨부 이미지 파일 처리
 **/
	// private function _procThumbnailImages()
	// {
	// 	$oFileController = getController('file');
	// 	// 카탈로그 썸네일 파일 변경
	// 	if(is_uploaded_file($this->_g_oNewItemHeader->thumbnail_image['tmp_name'])) 
	// 	{
	// 		// delete old catalog thumbnail
	// 		if($this->_g_oOldItemHeader->thumb_file_srl) 
	// 			$oFileController->deleteFile($this->_g_oOldItemHeader->thumb_file_srl);
	// 		// attach new catalog thumbnail
	// 		$oTmpRst = $oFileController->insertFile($this->_g_oNewItemHeader->thumbnail_image, $this->_g_oNewItemHeader->module_srl, $this->_g_oNewItemHeader->item_srl);
	// 		if(!$oTmpRst || !$oTmpRst->toBool())
	// 			return $oTmpRst;
	// 		$this->_g_oNewItemHeader->thumb_file_srl = $oTmpRst->get('file_srl');
	// 		unset($oTmpRst);
	// 		$oTmpArgs->item_srl = $this->_g_oNewItemHeader->item_srl;
	// 		$oTmpArgs->thumb_file_srl = $this->_g_oNewItemHeader->thumb_file_srl;
	// 		$oUpdateRst = executeQuery('svmarket.updateItemFile', $oTmpArgs);
	// 		if(!$oUpdateRst->toBool())
	// 			return $oUpdateRst;
	// 		unset($oUpdateRst);
	// 		unset($oTmpArgs);
	// 		$oFileController->setFilesValid($this->_g_oNewItemHeader->item_srl);
	// 	}
	// 	// 갤러리 썸네일 이미지를 첨부한 후 저장한 상황에 대응
	// 	$oFileController->setFilesValid($this->_g_oNewItemHeader->gallery_doc_srl);
	// 	unset($oFileController);
	// 	return new BaseObject();
	// }
}
/* End of file svmarket.pkg_admin.php */
/* Location: ./modules/svmarket/svmarket.pkg_admin.php */