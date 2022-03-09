<?php
/**
 * @class  svsvmarketPkgAdmin
 * @author singleview(root@singleview.co.kr)
 * @brief  svsvmarketPkgAdmin class
 */
class svmarketPkgAdmin extends svmarket
{
	private $_g_oSvmarketModuleConfig = NULL; // svmarket module config 적재
	private $_g_oLoggedInfo = NULL;
	private $_g_oOldPkgHeader = NULL; // 정보 수정할 때 과거 상태에 관한 참조일 뿐
	private $_g_oNewPkgHeader = NULL; // 항상 현재 쓰기의 기준
	const A_PKG_HEADER_TYPE = ['_g_oNewPkgHeader', '_g_oOldPkgHeader'];
/**
 * @brief 생성자
 * $oParams->oSvitemConfig
 **/
	public function __construct($oParams=null)
	{
		$this->_g_oLoggedInfo = Context::get('logged_info');
		if($oParams->oSvmarketModuleConfig)
			$this->_g_oSvmarketModuleConfig = $oParams->oSvmarketModuleConfig;
		$this->_setSkeletonHeader();
	}
/**
 * @brief 
 **/
	public function __get($sName) 
	{
		if($sName == 'nModuleSrl')
			return $this->_g_oOldPkgHeader->module_srl; // [module_srl] attr은 Context 클래스를 통과하면서 전달되지 않는 것 같음
		if(isset($this->_g_oOldPkgHeader->{$sName}))
		{
			if($this->_g_oOldPkgHeader->{$sName} == svmarket::S_NULL_SYMBOL)
				return null;
			else
				return $this->_g_oOldPkgHeader->{$sName};
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
	 * @brief 신규 패키지 생성
	 **/
	public function create($oNewPkgArgs)
	{
		$this->_initHeader();
		$this->_matchNewPkgInfo($oNewPkgArgs);
		if($this->_g_oNewPkgHeader->module_srl == svmarket::S_NULL_SYMBOL || 
			$this->_g_oNewPkgHeader->title == svmarket::S_NULL_SYMBOL)
			return new BaseObject(-1,'msg_invalid_request');
		// if($sMode == 'bulk' ) // excel bulk upload mode
		// 	;
		return $this->_insertPkg();
	}
	/**
	 * @brief 기존 패키지 정보 적재
	 **/
	public function loadHeader($oParams)
	{
		$this->_initHeader();
		$oTmpArgs = new stdClass();
		$oTmpArgs->package_srl = $oParams->package_srl;
		$oTmpRst = executeQuery('svmarket.getPkgDetail', $oTmpArgs);
		if(!$oTmpRst->toBool())
			return $oTmpRst;
		if(!is_object($oTmpRst->data))
			return new BaseObject(-1,'msg_invalid_pkg_request');
		$this->_matchOldPkgInfo($oTmpRst->data);
		//$this->_setReviewCnt(); // 후기수 설정
		$oModuleModel = getModel('module');
		$oModuleInfo = $oModuleModel->getModuleInfoByModuleSrl($this->_g_oOldPkgHeader->module_srl);
		$this->_g_oOldPkgHeader->mid = $oModuleInfo->mid;
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
		if($this->_g_oOldPkgHeader->category_node_srl > 0)
		{
	//////////////////////// getCatalog() 가져오기
			$oSvmakretModel = &getModel('svmarket');
			$nModuleSrl = $this->_g_oOldPkgHeader->module_srl;
			$this->_g_oOldPkgHeader->oCatalog = $oSvitemModel->getCatalog($nModuleSrl, $this->_g_oOldPkgHeader->category_node_srl);
	////////////////////////
			if(strlen($this->_g_oOldPkgHeader->enhanced_item_info->ga_category_name) == 0)
				$this->_g_oOldPkgHeader->enhanced_item_info->ga_category_name = $this->_g_oOldPkgHeader->oCatalog->current_catalog_info->node_name;
			unset($nModuleSrl);
		}

		// for sns share
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($this->_g_oOldPkgHeader->package_srl);
		// $this->_g_oOldPkgHeader->enhanced_item_info->item_brief = $oDocument->getContent(false);
		$oDbInfo = Context::getDBInfo();
		$oSnsInfo = new stdClass();
		$oSnsInfo->sPermanentUrl = $oDocument->getPermanentUrl().'?l='.$oDbInfo->lang_type;
		$oSnsInfo->sEncodedDocTitle = urlencode($this->_g_oOldPkgHeader->title);
		$this->_g_oOldPkgHeader->oSnsInfo = $oSnsInfo;
		unset($oDbInfo);
		unset($oDocument);
		unset($oDocumentModel);

		$this->_g_oOldPkgHeader->desc_for_editor = htmlentities($this->_g_oOldPkgHeader->description);

		// begin - load packaged app list
		$oArgs = new stdClass();
		$oArgs->package_srl = $this->_g_oOldPkgHeader->package_srl;
		$oListRst = executeQueryArray('svmarket.getAdminAppList', $oArgs);
		// var_dump($oListRst);
		// exit;
		unset($oArgs);
		if(!$oListRst->toBool())
			return $oListRst;
		if(count($oListRst->data))
		{
			require_once(_XE_PATH_.'modules/svmarket/svmarket.app_admin.php');
			$oParams = new stdClass();
			$aPackedApp = [];
			foreach($oListRst->data as $nIdx=>$oVal)
			{
				$oAppAdmin = new svmarketAppAdmin();
				$oParams->app_srl = $oVal->app_srl;
				$oTmpRst = $oAppAdmin->loadHeader($oParams);
				if(!$oTmpRst->toBool())
					return new BaseObject(-1,'msg_invalid_app_request');
				$oDetailRst = $oAppAdmin->loadDetail();
				if(!$oDetailRst->toBool())
					return $oDetailRst;
				unset($oDetailRst);
				$aPackedApp[$nIdx] = $oAppAdmin;
			}
			unset($oParams);
			$this->_g_oOldPkgHeader->app_list = $aPackedApp;
		}
		else
			$this->_g_oOldPkgHeader->app_list = [];
		// end - load packaged app list
		return new BaseObject();
	}

	/**
	 * @brief 기존 패키지 정보 변경
	 **/
	public function update($oPkgArgs)
	{
		if(!$this->_g_oOldPkgHeader)
			return new BaseObject(-1,'msg_required_to_load_old_information_first');
		$this->_matchNewPkgInfo($oPkgArgs);
		if($this->_g_oNewPkgHeader->package_srl == -1)
			return new BaseObject(-1,'msg_invalid_request');
		
		// 고정값은 외부 쿼리로 변경하지 않음
		$this->_g_oNewPkgHeader->package_srl = $this->_g_oOldPkgHeader->package_srl;
		if($this->_g_oNewPkgHeader->module_srl == svmarket::S_NULL_SYMBOL)
			$this->_g_oNewPkgHeader->module_srl = $this->_g_oOldPkgHeader->module_srl;
		return $this->_updateItem();
	}
	/**
	 * @brief 
	 **/
	private function _updateItem()
	{
		$this->_nullifyHeader();
		// 기본 정보 설정
		$oArgs = new stdClass();
		$oArgs->package_srl = $this->_g_oOldPkgHeader->package_srl; // package_srl은 수정하면 안됨
		if($this->_g_oNewPkgHeader->module_srl)
			$oArgs->module_srl = $this->_g_oNewPkgHeader->module_srl;
		if($this->_g_oNewPkgHeader->list_order)
			$oArgs->list_order = $this->_g_oNewPkgHeader->list_order;
		if($this->_g_oNewPkgHeader->category_node_srl)
			$oArgs->category_node_srl = $this->_g_oNewPkgHeader->category_node_srl;
		if($this->_g_oNewPkgHeader->title)
			$oArgs->title = $this->_g_oNewPkgHeader->title;
		if($this->_g_oNewPkgHeader->thumb_file_srl)
			$oArgs->thumb_file_srl = $this->_g_oNewPkgHeader->thumb_file_srl;
		if($this->_g_oNewPkgHeader->og_description)
			$oArgs->og_description = $this->_g_oNewPkgHeader->og_description;
		if($this->_g_oNewPkgHeader->description)
			$oArgs->description = $this->_g_oNewPkgHeader->description;
		if($this->_g_oNewPkgHeader->homepage)
			$oArgs->homepage = $this->_g_oNewPkgHeader->homepage;
		if($this->_g_oNewPkgHeader->tags)
			$oArgs->tags = $this->_g_oNewPkgHeader->tags;
		if($this->_g_oNewPkgHeader->display)
			$oArgs->display = $this->_g_oNewPkgHeader->display;
		$oUpdateRst = executeQuery('svmarket.updateAdminPkg', $oArgs);
		unset($oArgs);
		if(!$oUpdateRst->toBool())
			return $oUpdateRst;
		// 첨부 이미지 파일 처리
		//$oUpdateRst = $this->_procThumbnailImages();
		return $oUpdateRst;
	}
    /**
     * @brief set skeleton svmarket header
     * svmarket.pkg_consumer.php::_setSkeletonHeader()과 통일성 유지
     **/
    private function _setSkeletonHeader()
    {
        $aBasicAttr = ['package_srl', 'module_srl', 'list_order', 'category_node_srl', 
                        'title', 'thumb_file_srl', 'og_description', 'description', 
						'homepage', 'tags', 'display', 'updatetime', 'regdate'];
        $aInMemoryAttr = ['app_list'];
        $aTempAttr = ['thumbnail_image'];
        foreach(self::A_PKG_HEADER_TYPE as $nTypeIdx => $sHeaderType)
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
 * @brief 헤더 초기화
 **/
    private function _initHeader()
    {
        foreach($this->_g_oNewPkgHeader as $sTitle => $sVal)
            $this->_g_oNewPkgHeader->$sTitle = svmarket::S_NULL_SYMBOL;
        foreach($this->_g_oOldPkgHeader as $sTitle => $sVal)
            $this->_g_oOldPkgHeader->$sTitle = svmarket::S_NULL_SYMBOL;
    }
/**
 * @brief 
 **/
	private function _matchNewPkgInfo($oNewPkgArgs)
	{
		$aIgnoreVar = array('error_return_url', 'ruleset', 'module', 'mid', 'act');
		$aCleanupVar = array('title');
		foreach($oNewPkgArgs as $sTitle => $sVal)
		{
            if($sTitle!='pkg_srl')
                $sTitle = str_replace('pkg_', '', $sTitle);
            if(in_array($sTitle, $aIgnoreVar)) 
				continue;
            if(in_array($sTitle, $aCleanupVar))
				$sVal = trim(strip_tags($sVal));
            if($this->_g_oNewPkgHeader->$sTitle == svmarket::S_NULL_SYMBOL)
				$this->_g_oNewPkgHeader->$sTitle = $sVal;
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
    private function _insertPkg()
    {
        // insert document for OG
        $this->_g_oNewPkgHeader->package_srl = getNextSequence();
        $oDocArgs = new stdClass();
        $oDocArgs->document_srl = $this->_g_oNewPkgHeader->package_srl;
        $oDocArgs->module_srl = $this->_g_oNewPkgHeader->module_srl;
        $oDocArgs->content = $this->_g_oNewPkgHeader->title.'패키지의 상세페이지';
        $oDocArgs->title = $this->_g_oNewPkgHeader->title;
        $oDocArgs->list_order = $this->_g_oNewPkgHeader->package_srl * -1;
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
        $oParam->package_srl = $this->_g_oNewPkgHeader->package_srl;
        $oParam->module_srl = $this->_g_oNewPkgHeader->module_srl;
        $oParam->title = $this->_g_oNewPkgHeader->title;
        $oParam->description = $this->_g_oNewPkgHeader->description;
        $oParam->homepage = $this->_g_oNewPkgHeader->homepage;
        $oParam->display = 'N'; // 최초 등록 시에는 기본 최소 정보이므로 무조건 비공개
        $oParam->list_order = $this->_g_oNewPkgHeader->package_srl * -1;
        
        // save representative thumbnail
        if($this->_g_oNewPkgHeader->thumbnail_image['tmp_name']) 
        {
            $oFileController = &getController('file');
            if(is_uploaded_file($this->_g_oNewPkgHeader->thumbnail_image['tmp_name'])) // single upload via web interface mode
            {
                $oFileRst = $oFileController->insertFile($this->_g_oNewPkgHeader->thumbnail_image, $this->_g_oNewPkgHeader->module_srl, $this->_g_oNewPkgHeader->package_srl);
                if(!$oFileRst || !$oFileRst->toBool())
                    return $oFileRst;
                $oFileController->setFilesValid($this->_g_oNewPkgHeader->package_srl);
                $oParam->thumb_file_srl = $oFileRst->get('file_srl');
                unset($oFileRst);
                unset($oFileController);
            }
            elseif($this->_g_oNewPkgHeader->thumbnail_image['size']) // excel bulk mode
            {
                echo 'yes img->'.$this->_g_oNewPkgHeader->thumbnail_image['name'].'<BR>';
                $oFileRst = $oFileController->insertFile($this->_g_oNewPkgHeader->thumbnail_image, $this->_g_oNewPkgHeader->module_srl, $this->_g_oNewPkgHeader->package_srl, 0, true);
                if(!$oFileRst || !$oFileRst->toBool())
                    return $oFileRst;
                $oFileController->setFilesValid($this->_g_oNewPkgHeader->package_srl);
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
        $oInsertRst = executeQuery('svmarket.insertAdminPkg', $oParam);
		if(!$oInsertRst->toBool())
        {
            unset($oParam);
			return $oInsertRst;
        }
        unset($oParam);
        $oInsertRst->add('nPkgSrl', $this->_g_oNewPkgHeader->package_srl);
        return $oInsertRst;
    }
/**
 * @brief 저장 명령을 실행하기 위해 값 할당 후에도 svmarket::S_NULL_SYMBOL이면 null로 변경
 **/
    private function _nullifyHeader()
    {
        foreach($this->_g_oNewPkgHeader as $sTitle => $sVal)
        {
            if($sVal == svmarket::S_NULL_SYMBOL)
                $this->_g_oNewPkgHeader->$sTitle = null;
        }
		foreach($this->_g_oOldPkgHeader as $sTitle => $sVal)
        {
            if($sVal == svmarket::S_NULL_SYMBOL)
                $this->_g_oOldPkgHeader->$sTitle = null;
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
	private function _matchOldPkgInfo($oPkgmArgs)
	{
		$aIgnoreVar = array('module', 'mid', 'act');
		foreach($oPkgmArgs as $sTitle => $sVal)
		{
			if(in_array($sTitle, $aIgnoreVar)) 
				continue;
			if($this->_g_oOldPkgHeader->{$sTitle} == svmarket::S_NULL_SYMBOL)
			{
				if($sVal)
					$this->_g_oOldPkgHeader->{$sTitle} = $sVal;
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
 * @brief 
 **/
	private function _matchOldItemInfo($oItemArgs)
	{
		$aIgnoreVar = array('module', 'mid', 'act');
		foreach($oItemArgs as $sTitle => $sVal)
		{
			if(in_array($sTitle, $aIgnoreVar)) 
				continue;

			if( $this->_g_oOldItemHeader->{$sTitle} == svmarket::S_NULL_SYMBOL )
			{
				if( $sVal )
					$this->_g_oOldItemHeader->{$sTitle} = $sVal;
			}
			else
			{
//////////////// for debug only
				if( is_object( $sVal ) )
				{
					var_dump( 'weird: '.$sTitle );
					echo '<BR>';
					var_dump( $sVal );
					echo '<BR>';
				}
				else
				{
					var_dump( '2weird: '.$sTitle.' => '. $sVal );
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