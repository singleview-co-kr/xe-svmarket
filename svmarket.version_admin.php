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
	const A_PKG_HEADER_TYPE = ['_g_oNewVersionHeader', '_g_oOldVersionHeader'];
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
			debugPrint($sName);
			trigger_error('Undefined property or method: '.$sName);
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
        $aBasicAttr = ['version_srl', 'app_srl', 'module_srl', 'package_srl', 
                        'version', 'zip_file_srl', 'description', 'updatetime', 'regdate'];
        //$aInMemoryAttr = ['review_count', 'mid', 'sDescription', 'extra_vars']; // 'extra_vars'는 unserialize된 구조체 적재
        $aTempAttr = ['version_zip_file'];
        foreach(self::A_PKG_HEADER_TYPE as $nTypeIdx => $sHeaderType)
        {
            $this->{$sHeaderType} = new stdClass();
            foreach($aBasicAttr as $nAttrIdx => $sAttrName)
                $this->{$sHeaderType}->{$sAttrName} = svmarket::S_NULL_SYMBOL;
            // foreach($aInMemoryAttr as $nAttrIdx => $sAttrName)
            //     $this->{$sHeaderType}->{$sAttrName} = svmarket::S_NULL_SYMBOL;
            // temp item info for insertion
			foreach($aTempAttr as $nAttrIdx => $sAttrName)
                $this->{$sHeaderType}->{$sAttrName} = svitem::S_NULL_SYMBOL;
        }
        unset($aBasicAttr);
        unset($aInMemoryAttr);
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
		// if($sMode == 'bulk' ) // excel bulk upload mode
		// 	;
		return $this->_insertVersion();
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
 * @brief 
 **/
	private function _matchNewPkgInfo($oNewVersionArgs)
	{
        // var_dump($oNewVersionArgs);
        // exit;
		$aIgnoreVar = array('error_return_url', 'ruleset', 'module', 'mid', 'act');
		$aCleanupVar = array('version');
		foreach($oNewVersionArgs as $sTitle => $sVal)
		{
            if($sTitle!='version_srl' && $sTitle!='version_zip_file')
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
    private function _insertVersion()
    {
        $this->_g_oNewVersionHeader->version_srl = getNextSequence();
        $oDocArgs = new stdClass();
        $oDocArgs->document_srl = $this->_g_oNewVersionHeader->version_srl;
        $oDocArgs->module_srl = $this->_g_oNewVersionHeader->module_srl;
        $oDocArgs->content = $this->_g_oNewVersionHeader->title.'버전의 상세페이지';
        $oDocArgs->title = $this->_g_oNewVersionHeader->title;
        $oDocArgs->list_order = $this->_g_oNewVersionHeader->app_srl * -1;
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
        $oParam = new stdClass();
        $oParam->version_srl = $this->_g_oNewVersionHeader->version_srl;
        $oParam->app_srl = $this->_g_oNewVersionHeader->app_srl;
        $oParam->package_srl = $this->_g_oNewVersionHeader->package_srl;
        $oParam->module_srl = $this->_g_oNewVersionHeader->module_srl;
        $oParam->version = $this->_g_oNewVersionHeader->version;
        $oParam->description = $this->_g_oNewVersionHeader->description;

        // save version zip file
        if($this->_g_oNewVersionHeader->version_zip_file['tmp_name']) 
        {
            if(is_uploaded_file($this->_g_oNewVersionHeader->version_zip_file['tmp_name'])) // single upload via web interface mode
            {
                $sFileExt = svmarket::getFileExt($this->_g_oNewVersionHeader->version_zip_file['name']);
                if($sFileExt == 'zip')
                {
                    $oFileController = getController('file');
                    $oFileRst = $oFileController->insertFile($this->_g_oNewVersionHeader->version_zip_file, $this->_g_oNewVersionHeader->module_srl, $this->_g_oNewVersionHeader->version_srl);
                    if(!$oFileRst || !$oFileRst->toBool())
                        return $oFileRst;
                    $oFileController->setFilesValid($this->_g_oNewVersionHeader->version_srl);
                    $oParam->zip_file_srl = $oFileRst->get('file_srl');
                    unset($oFileRst);
                    unset($oFileController);
                }
            }
        }
        // var_dump($oParam);
		// exit;
        $oInsertRst = executeQuery('svmarket.insertVersion', $oParam);
		if(!$oInsertRst->toBool())
        {
            unset($oParam);
			return $oInsertRst;
        }
        unset($oParam);
        //$oInsertRst->add('nVersionSrl', $this->_g_oNewVersionHeader->version_srl);
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
* @brief 기존 패키지 상세 정보 적재
**/
	public function loadDetail()
	{
		$this->_nullifyHeader();

		// for sns share
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($this->_g_oOldVersionHeader->package_srl);
		// $this->_g_oOldVersionHeader->enhanced_item_info->item_brief = $oDocument->getContent(false);

		$oDbInfo = Context::getDBInfo();
		$oSnsInfo = new stdClass();
		$oSnsInfo->sPermanentUrl = $oDocument->getPermanentUrl().'?l='.$oDbInfo->lang_type;
		$oSnsInfo->sEncodedDocTitle = urlencode($this->_g_oOldVersionHeader->title);
		$this->_g_oOldVersionHeader->oSnsInfo = $oSnsInfo;
		unset($oDbInfo);
		unset($oDocument);
		unset($oDocumentModel);

        // begin - get appending file info
        $oFileModel = getModel('file');
        $this->_g_oOldVersionHeader->oVersionFile = $oFileModel->getFile($this->_g_oOldVersionHeader->zip_file_srl);
        if(!$this->_g_oOldVersionHeader->description)
            $this->_g_oOldVersionHeader->description = $this->version.'을 다운로드하세요.';
        unset($oFileModel);
        // end - get appending file info
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
		$this->_g_oNewVersionHeader->package_srl = $this->_g_oOldVersionHeader->package_srl;
		if($this->_g_oNewVersionHeader->module_srl == svmarket::S_NULL_SYMBOL)
			$this->_g_oNewVersionHeader->module_srl = $this->_g_oOldVersionHeader->module_srl;
		return $this->_updateVersion();
	}
	/**
	 * @brief 
	 **/
	private function _updateVersion()
	{
		$this->_nullifyHeader();
		// 기본 정보 설정
		$oArgs = new stdClass();
		$oArgs->package_srl = $this->_g_oOldVersionHeader->package_srl; // package_srl은 수정하면 안됨
		if($this->_g_oNewVersionHeader->module_srl)
			$oArgs->module_srl = $this->_g_oNewVersionHeader->module_srl;
		if($this->_g_oNewVersionHeader->list_order)
			$oArgs->list_order = $this->_g_oNewVersionHeader->list_order;
		if($this->_g_oNewVersionHeader->category_node_srl)
			$oArgs->category_node_srl = $this->_g_oNewVersionHeader->category_node_srl;
		if($this->_g_oNewVersionHeader->title)
			$oArgs->title = $this->_g_oNewVersionHeader->title;
		if($this->_g_oNewVersionHeader->thumb_file_srl)
			$oArgs->thumb_file_srl = $this->_g_oNewVersionHeader->thumb_file_srl;
		if($this->_g_oNewVersionHeader->description)
			$oArgs->description = $this->_g_oNewVersionHeader->description;
		if($this->_g_oNewVersionHeader->homepage)
			$oArgs->homepage = $this->_g_oNewVersionHeader->homepage;
		if($this->_g_oNewVersionHeader->tags)
			$oArgs->tags = $this->_g_oNewVersionHeader->tags;
		if($this->_g_oNewVersionHeader->display)
			$oArgs->display = $this->_g_oNewVersionHeader->display;
		
		$oUpdateRst = executeQuery('svmarket.updateAdminPkg', $oArgs);
		unset($oArgs);
		if(!$oUpdateRst->toBool())
			return $oUpdateRst;
		//unset($oUpdateRst);
		// 첨부 이미지 파일 처리
		//$oUpdateRst = $this->_procThumbnailImages();
		return $oUpdateRst;
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
* @brief for debug only
*/
	public function dumpInfo()
	{
		foreach( $this->_g_oNewItemHeader as $sTitle=>$sVal)
		{
			if(is_object($sVal))
			{
				echo $sTitle.'=><BR>';
				var_dump($sVal);
				echo '<BR>';
			}
			else
				echo $sTitle.'=>'.$sVal.'<BR>';
		}
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
 * @brief 첨부 이미지 파일 처리
 **/
	private function _procThumbnailImages()
	{
		$oFileController = getController('file');
		// 카탈로그 썸네일 파일 변경
		if(is_uploaded_file($this->_g_oNewItemHeader->thumbnail_image['tmp_name'])) 
		{
			// delete old catalog thumbnail
			if($this->_g_oOldItemHeader->thumb_file_srl) 
				$oFileController->deleteFile($this->_g_oOldItemHeader->thumb_file_srl);
			// attach new catalog thumbnail
			$oTmpRst = $oFileController->insertFile($this->_g_oNewItemHeader->thumbnail_image, $this->_g_oNewItemHeader->module_srl, $this->_g_oNewItemHeader->item_srl);
			if(!$oTmpRst || !$oTmpRst->toBool())
				return $oTmpRst;
			$this->_g_oNewItemHeader->thumb_file_srl = $oTmpRst->get('file_srl');
			unset($oTmpRst);
			$oTmpArgs->item_srl = $this->_g_oNewItemHeader->item_srl;
			$oTmpArgs->thumb_file_srl = $this->_g_oNewItemHeader->thumb_file_srl;
			$oUpdateRst = executeQuery('svmarket.updateItemFile', $oTmpArgs);
			if(!$oUpdateRst->toBool())
				return $oUpdateRst;
			unset($oUpdateRst);
			unset($oTmpArgs);
			$oFileController->setFilesValid($this->_g_oNewItemHeader->item_srl);
		}
		// 갤러리 썸네일 이미지를 첨부한 후 저장한 상황에 대응
		$oFileController->setFilesValid($this->_g_oNewItemHeader->gallery_doc_srl);
		unset($oFileController);
		return new BaseObject();
	}
}
/* End of file svmarket.pkg_admin.php */
/* Location: ./modules/svmarket/svmarket.pkg_admin.php */