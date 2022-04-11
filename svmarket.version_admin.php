<?php
/**
 * @class  svsvmarketVersionAdmin
 * @author singleview(root@singleview.co.kr)
 * @brief  svsvmarketVersionAdmin class
 */
class svmarketVersionAdmin extends svmarket
{
	private $_g_oLoggedInfo = NULL;
	private $_g_oOldVersionHeader = NULL; // 정보 수정할 때 과거 상태에 관한 참조일 뿐
	private $_g_oNewVersionHeader = NULL; // 항상 현재 쓰기의 기준
	const A_VERSION_HEADER_TYPE = ['_g_oNewVersionHeader', '_g_oOldVersionHeader'];
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
			return $this->_g_oOldVersionHeader->module_srl; // [module_srl] attr은 Context 클래스를 통과하면서 전달되지 않는 것 같음
		if(isset($this->_g_oOldVersionHeader->{$sName}))
		{
			if($this->_g_oOldVersionHeader->{$sName} == svmarket::S_NULL_SYMBOL)
				return null;
			else
				return $this->_g_oOldVersionHeader->{$sName};
		}
		else
		{
			if($sName == 'thumb_file_srl' || $sName == 'readed_count')
                return 0;
            else
            {
                trigger_error('Undefined property or method: '.$sName);
			    return null;
            }
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
 * @brief 신규 패키지 생성
 **/
	public function create($oNewVersionArgs)
	{
		$this->_initHeader();
		$this->_matchNewPkgInfo($oNewVersionArgs);
		if($this->_g_oNewVersionHeader->package_srl == svmarket::S_NULL_SYMBOL || 
            $this->_g_oNewVersionHeader->module_srl == svmarket::S_NULL_SYMBOL || 
			$this->_g_oNewVersionHeader->title == svmarket::S_NULL_SYMBOL)
			return new BaseObject(-1,'msg_invalid_request');
		$this->_setAppendingFilesValid();
        return $this->_insertVersion();
	}
    /**
     * @brief 기존 패키지 정보 적재
     **/
	public function loadHeader($oParams)
	{
		$this->_initHeader();
		$oTmpArgs = new stdClass();
		$oTmpArgs->version_srl = $oParams->version_srl;
		$oTmpRst = executeQuery('svmarket.getAdminVersionDetail', $oTmpArgs);
		unset($oTmpArgs);
		if(!$oTmpRst->toBool())
			return $oTmpRst;
		if(!is_object($oTmpRst->data))
			return new BaseObject(-1,'msg_invalid_app_request');
					
		$this->_matchOldAppInfo($oTmpRst->data);
		//$this->_setReviewCnt(); // 후기수 설정
		$oModuleModel = getModel('module');
		$oModuleInfo = $oModuleModel->getModuleInfoByModuleSrl($this->_g_oOldVersionHeader->module_srl);
		$this->_g_oOldVersionHeader->mid = $oModuleInfo->mid;
		unset($oModuleModel);
		unset($oModuleInfo);
		return $oTmpRst;
	}
    /**
    * @brief 기존 버전 상세 정보 적재
    **/
	public function loadDetail()
	{
		$this->_nullifyHeader();
        // begin - breadcrumb info
        $oTmpArgs = new stdClass();
		$oTmpArgs->package_srl = $this->_g_oOldVersionHeader->package_srl;
		$oTmpRst = executeQuery('svmarket.getAdminPkgDetail', $oTmpArgs);
        unset($oTmpArgs);
		if(!$oTmpRst->toBool())
			return $oTmpRst;
		if(!is_object($oTmpRst->data))
			return new BaseObject(-1,'msg_invalid_pkg_request');
		$this->_g_oOldVersionHeader->package_title = $oTmpRst->data->title;
        unset($oTmpRst);

        // begin - load parent app info
        $oParams = new stdClass();
        $oParams->app_srl = $this->_g_oOldVersionHeader->app_srl;
        require_once(_XE_PATH_.'modules/svmarket/svmarket.app_admin.php');
        $oAppAdmin = new svmarketAppAdmin();
        $oAppDetailRst = $oAppAdmin->loadHeader($oParams);
        if(!$oAppDetailRst->toBool())
            return $oAppDetailRst;
        unset($oAppDetailRst);
        // $oAppAdmin->loadDetail();  // never call this infinite loop
		$this->_g_oOldVersionHeader->app_title = $oAppAdmin->title;
        $this->_g_oOldVersionHeader->app_type_name = $oAppAdmin->type_name;
        // $this->_g_oOldVersionHeader->app_thumb_file_srl = $oAppAdmin->thumb_file_srl;
        $this->_g_oOldVersionHeader->app_install_path = $oAppAdmin->install_path;
        $this->_g_oOldVersionHeader->app_github_url = $oAppAdmin->github_url;
        unset($oAppAdmin);
        unset($oParams);
        // end - load parent app info
		$this->_g_oOldVersionHeader->desc_for_editor = htmlentities($this->_g_oOldVersionHeader->description);
		if(!$this->_g_oOldVersionHeader->og_description)
			$this->_g_oOldVersionHeader->og_description = mb_substr(html_entity_decode(strip_tags($this->_g_oOldVersionHeader->description)), 0, 40, 'utf-8');

        if($this->_g_oOldVersionHeader->member_srl != svmarket::S_NULL_SYMBOL)
        {
            $oMemberModel = getModel('member');
            $oMemberInfo = $oMemberModel->getMemberInfoByMemberSrl($this->_g_oOldVersionHeader->member_srl);
            $this->_g_oOldVersionHeader->nick_name = $oMemberInfo->nick_name;
            unset($oMemberInfo);
            unset($oMemberModel);
        }
        else
            $this->_g_oOldPkgHeader->nick_name = 'anonymous';
        // begin - get appending zipfile download info
        // {$oVersionInfo->oVersionFile->download_url
        if(strpos($this->_g_oOldVersionHeader->app_github_url, 'https://github.com/') !== false)
        {
            $this->_g_oOldVersionHeader->oVersionFile = new stdClass();
            $this->_g_oOldVersionHeader->oVersionFile->download_url = $this->_g_oOldVersionHeader->github_download_url;
            $this->_g_oOldVersionHeader->oVersionFile->source_filename = 'download from github';
            $this->_g_oOldVersionHeader->sDetailLink = $this->_g_oOldVersionHeader->github_tag_url;
        }
        else
        {
            $oFileModel = getModel('file');
            $this->_g_oOldVersionHeader->oVersionFile = $oFileModel->getFile($this->_g_oOldVersionHeader->zip_file_srl);
            unset($oFileModel);
            $this->_g_oOldVersionHeader->sDetailLink = getUrl('document_srl',$this->_g_oOldVersionHeader->version_srl);
        }
        // end - get appending zipfile download info
        if(!$this->_g_oOldVersionHeader->description)
            $this->_g_oOldVersionHeader->description = $this->version.'을 다운로드하세요.';
		return new BaseObject();
	}
    /**
     * @brief 기존 패키지 정보 변경
     **/
	public function update($oPkgArgs)
	{
		if(!$this->_g_oOldVersionHeader)
			return new BaseObject(-1,'msg_required_to_load_old_information_first');
		$this->_matchNewPkgInfo($oPkgArgs);
		if($this->_g_oNewVersionHeader->package_srl == -1)
			return new BaseObject(-1,'msg_invalid_request');
		
		// 고정값은 외부 쿼리로 변경하지 않음
		$this->_g_oNewVersionHeader->version_srl = $this->_g_oOldVersionHeader->version_srl;
		if($this->_g_oNewVersionHeader->module_srl == svmarket::S_NULL_SYMBOL)
			$this->_g_oNewVersionHeader->module_srl = $this->_g_oOldVersionHeader->module_srl;
        $this->_setAppendingFilesValid();
        return $this->_updateVersion();
	}
    /**
	 * Update read counts of the package
	 * @return bool|void
	 */
	function updateReadedCount()
	{
		// Pass if Crawler access
		if(isCrawler()) return false;
		
		$nDocumentSrl = $this->_g_oOldVersionHeader->version_srl;
		$nMemberSrl = $this->_g_oOldVersionHeader->member_srl;
		// Call a trigger when the read count is updated (before)
		// Pass if read count is increaded on the session information
		if($_SESSION['readed_document'][$nDocumentSrl]) return false;

		// Pass if the author's IP address is as same as visitor's.
		if($this->_g_oOldVersionHeader->ipaddress == $_SERVER['REMOTE_ADDR'])
		{
			$_SESSION['readed_document'][$nDocumentSrl] = true;
			return false;
		}
		// Pass ater registering sesscion if the author is a member and has same information as the currently logged-in user.
		if($nMemberSrl && $this->_g_oLoggedInfo->member_srl == $nMemberSrl)
		{
			$_SESSION['readed_document'][$nDocumentSrl] = true;
			return false;
		}
		$oDB = DB::getInstance();
		$oDB->begin();
		// Update read counts
		$args = new stdClass;
		$args->version_srl = $nDocumentSrl;
		executeQuery('svmarket.updateVersionReadedCount', $args);
    	$oDB->commit();
        unset($args);
		// Register session
		if(!$_SESSION['banned_document'][$nDocumentSrl]) 
			$_SESSION['readed_document'][$nDocumentSrl] = true;
		return TRUE;
	}
    /**
     * @brief 헤더 초기화
     **/
    private function _initHeader()
    {
        foreach($this->_g_oNewVersionHeader as $sTitle => $sVal)
            $this->_g_oNewVersionHeader->$sTitle = svmarket::S_NULL_SYMBOL;
        foreach($this->_g_oOldVersionHeader as $sTitle => $sVal)
            $this->_g_oOldVersionHeader->$sTitle = svmarket::S_NULL_SYMBOL;
    }
    /**
     * @brief set skeleton svmarket header
     **/
    private function _setSkeletonHeader()
    {
        $aBasicAttr = ['version_srl', 'app_srl', 'module_srl', 'package_srl', 
                        'version', 'zip_file_srl', 'og_description', 'description', 
                        'member_srl', 'readed_count', 'ipaddress',
						'display', 'updatetime', 'regdate'];
        $aInMemoryAttr = ['package_title', 'app_title', 'nick_name', 'github_tag_url', 
                            'github_download_url', 'sDetailLink'];
        $aTempAttr = ['zip_file'];
        foreach(self::A_VERSION_HEADER_TYPE as $nTypeIdx => $sHeaderType)
        {
            $this->{$sHeaderType} = new stdClass();
            foreach($aBasicAttr as $nAttrIdx => $sAttrName)
                $this->{$sHeaderType}->{$sAttrName} = svmarket::S_NULL_SYMBOL;
            foreach($aInMemoryAttr as $nAttrIdx => $sAttrName)
                $this->{$sHeaderType}->{$sAttrName} = svmarket::S_NULL_SYMBOL;
            // temp item info for insertion
			foreach($aTempAttr as $nAttrIdx => $sAttrName)
                $this->{$sHeaderType}->{$sAttrName} = svmarket::S_NULL_SYMBOL;
        }
        unset($aBasicAttr);
        unset($aInMemoryAttr);
    } 
    /**
     * @brief
     **/
	private function _setAppendingFilesValid()
	{
        if($this->_g_oOldVersionHeader->version_srl)
            $nTargetSrl = $this->_g_oOldVersionHeader->version_srl;
        else
            $nTargetSrl = $this->_g_oNewVersionHeader->version_srl;
        $oFileController = getController('file');
        $oFileController->setFilesValid($nTargetSrl);
        unset($oFileController);
    }
    /**
     * @brief 
     **/
    private function _insertVersion()
    {
        $this->_nullifyHeader();
        $this->_g_oNewVersionHeader->version_srl = getNextSequence();
        $oParam = new stdClass();
        $oParam->version_srl = $this->_g_oNewVersionHeader->version_srl;
        $oParam->app_srl = $this->_g_oNewVersionHeader->app_srl;
        $oParam->package_srl = $this->_g_oNewVersionHeader->package_srl;
        $oParam->module_srl = $this->_g_oNewVersionHeader->module_srl;
        $oParam->version = $this->_g_oNewVersionHeader->version;
        $oParam->description = $this->_g_oNewVersionHeader->description;
        if($this->_g_oLoggedInfo)
			$oParam->member_srl = $this->_g_oLoggedInfo->member_srl;
        // save version zip file
        if($this->_g_oNewVersionHeader->zip_file['tmp_name']) 
        {
            if(is_uploaded_file($this->_g_oNewVersionHeader->zip_file['tmp_name'])) // single upload via web interface mode
            {
                $sFileExt = svmarket::getFileExt($this->_g_oNewVersionHeader->zip_file['name']);
                if($sFileExt == 'zip')
                {
                    $oFileController = getController('file');
                    $oFileRst = $oFileController->insertFile($this->_g_oNewVersionHeader->zip_file, $this->_g_oNewVersionHeader->module_srl, $this->_g_oNewVersionHeader->version_srl);
                    if(!$oFileRst || !$oFileRst->toBool())
                        return $oFileRst;
                    $oFileController->setFilesValid($this->_g_oNewVersionHeader->version_srl);
                    $oParam->zip_file_srl = $oFileRst->get('file_srl');
                    unset($oFileRst);
                    unset($oFileController);
                }
            }
        }
        $oInsertRst = executeQuery('svmarket.insertVersion', $oParam);
		if(!$oInsertRst->toBool())
        {
            unset($oParam);
			return $oInsertRst;
        }
        unset($oParam);
        return $oInsertRst;
    }
/**
 * @brief 저장 명령을 실행하기 위해 값 할당 후에도 svmarket::S_NULL_SYMBOL이면 null로 변경
 **/
    private function _nullifyHeader()
    {
        foreach($this->_g_oNewVersionHeader as $sTitle => $sVal)
        {
            if($sVal == svmarket::S_NULL_SYMBOL)
                $this->_g_oNewVersionHeader->$sTitle = null;
        }
		foreach($this->_g_oOldVersionHeader as $sTitle => $sVal)
        {
            if($sVal == svmarket::S_NULL_SYMBOL)
                $this->_g_oOldVersionHeader->$sTitle = null;
        }
    }
	/**
	 * @brief 
	 **/
	private function _updateVersion()
	{
		$this->_nullifyHeader();
        // var_dump($this->_g_oNewVersionHeader);
        // exit;
		// 기본 정보 설정
		$oArgs = new stdClass();
		$oArgs->version_srl = $this->_g_oOldVersionHeader->version_srl; // package_srl은 수정하면 안됨
		if($this->_g_oNewVersionHeader->version)
			$oArgs->version = $this->_g_oNewVersionHeader->version;
        if($this->_g_oNewVersionHeader->github_tag_url)
			$oArgs->github_tag_url = $this->_g_oNewVersionHeader->github_tag_url;
        if($this->_g_oNewVersionHeader->github_download_url)
			$oArgs->github_download_url = $this->_g_oNewVersionHeader->github_download_url;
        if($this->_g_oNewVersionHeader->og_description)
			$oArgs->og_description = $this->_g_oNewVersionHeader->og_description;
		if($this->_g_oNewVersionHeader->description)
			$oArgs->description = $this->_g_oNewVersionHeader->description;
        
        $oFileModel = getModel('file');
        $aFiles = $oFileModel->getFiles($this->_g_oNewVersionHeader->version_srl);
        foreach($aFiles as $nIdx=>$oFile)
        {
            $aFileInfo = explode('.',$oFile->source_filename);
            if($aFileInfo[1] == 'zip')
            {
                $this->_g_oNewVersionHeader->zip_file_srl = $oFile->file_srl;
                $oArgs->zip_file_srl = $this->_g_oNewVersionHeader->zip_file_srl;
                break;
            }
        }
        unset($aFileInfo);
        unset($aFiles);
        unset($oFileModel);
		$oUpdateRst = executeQuery('svmarket.updateAdminVersion', $oArgs);
		unset($oArgs);
		if(!$oUpdateRst->toBool())
			return $oUpdateRst;
		return $oUpdateRst;
	}
    /**
     * @brief 
     **/
	private function _matchNewPkgInfo($oNewVersionArgs)
	{
		$aIgnoreVar = array('error_return_url', 'ruleset', 'module', 'mid', 'act');
		$aCleanupVar = array('version');
		foreach($oNewVersionArgs as $sTitle => $sVal)
		{
            if($sTitle!='version_srl')
                $sTitle = str_replace('version_', '', $sTitle);
            if(in_array($sTitle, $aIgnoreVar)) 
				continue;
            if(in_array($sTitle, $aCleanupVar))
				$sVal = trim(strip_tags($sVal));
            if($this->_g_oNewVersionHeader->$sTitle == svmarket::S_NULL_SYMBOL)
				$this->_g_oNewVersionHeader->$sTitle = $sVal;
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
			if($this->_g_oOldVersionHeader->{$sTitle} == svmarket::S_NULL_SYMBOL)
			{
				if($sVal)
					$this->_g_oOldVersionHeader->{$sTitle} = $sVal;
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
     * @brief 기존 패키지 정보 비활성화
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
     * @brief 패키지 영구 삭제; 코드 블록만 유지하고 이 메소드의 접근을 차단함
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
}
/* End of file svmarket.pkg_admin.php */
/* Location: ./modules/svmarket/svmarket.pkg_admin.php */