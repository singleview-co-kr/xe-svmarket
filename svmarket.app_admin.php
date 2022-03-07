<?php
/**
 * @class  svsvmarketAppAdmin
 * @author singleview(root@singleview.co.kr)
 * @brief  svsvmarketAppAdmin class
 */
class svmarketAppAdmin extends svmarket
{
	private $_g_oSvmarketModuleConfig = NULL; // svmarket module config 적재
	private $_g_oLoggedInfo = NULL;
	private $_g_oOldAppHeader = NULL; // 정보 수정할 때 과거 상태에 관한 참조일 뿐
	private $_g_oNewAppHeader = NULL; // 항상 현재 쓰기의 기준
	const A_PKG_HEADER_TYPE = ['_g_oNewAppHeader', '_g_oOldAppHeader'];
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
        $aBasicAttr = ['app_srl', 'module_srl', 'package_srl', 'list_order', 'category_node_srl', 
                        'title', 'thumb_file_srl', 'description', 'github_url', 'homepage', 'tags', 
                        'display', 'updatetime', 'regdate'];
        //$aInMemoryAttr = ['review_count', 'mid', 'sDescription', 'extra_vars']; // 'extra_vars'는 unserialize된 구조체 적재
        $aTempAttr = ['thumbnail_image'];
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
	public function create($oNewAppArgs)
	{
		$this->_initHeader();
		$this->_matchNewPkgInfo($oNewAppArgs);
		if($this->_g_oNewAppHeader->package_srl == svmarket::S_NULL_SYMBOL || 
            $this->_g_oNewAppHeader->module_srl == svmarket::S_NULL_SYMBOL || 
			$this->_g_oNewAppHeader->title == svmarket::S_NULL_SYMBOL)
			return new BaseObject(-1,'msg_invalid_request');
		// if($sMode == 'bulk' ) // excel bulk upload mode
		// 	;
		return $this->_insertApp();
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
	private function _matchNewPkgInfo($oNewAppArgs)
	{
		$aIgnoreVar = array('error_return_url', 'ruleset', 'module', 'mid', 'act');
		$aCleanupVar = array('title');
		foreach($oNewAppArgs as $sTitle => $sVal)
		{
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
    private function _insertApp()
    {
        $this->_g_oNewAppHeader->app_srl = getNextSequence();
        $oDocArgs = new stdClass();
        $oDocArgs->document_srl = $this->_g_oNewAppHeader->app_srl;
        $oDocArgs->module_srl = $this->_g_oNewAppHeader->module_srl;
        $oDocArgs->content = $this->_g_oNewAppHeader->title.'앱의 상세페이지';
        $oDocArgs->title = $this->_g_oNewAppHeader->title;
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
        $oParam = new stdClass();
        $oParam->app_srl = $this->_g_oNewAppHeader->app_srl;
        $oParam->package_srl = $this->_g_oNewAppHeader->package_srl;
        $oParam->module_srl = $this->_g_oNewAppHeader->module_srl;
        $oParam->title = $this->_g_oNewAppHeader->title;
        $oParam->description = $this->_g_oNewAppHeader->description;
        $oParam->homepage = $this->_g_oNewAppHeader->homepage;
        $oParam->display = 'N'; // 최초 등록 시에는 기본 최소 정보이므로 무조건 비공개
        $oParam->list_order = $this->_g_oNewAppHeader->package_srl * -1;
        
        // save representative thumbnail
        if($this->_g_oNewAppHeader->thumbnail_image['tmp_name']) 
        {
            $oFileController = &getController('file');
            if(is_uploaded_file($this->_g_oNewAppHeader->thumbnail_image['tmp_name'])) // single upload via web interface mode
            {
                $oFileRst = $oFileController->insertFile($this->_g_oNewAppHeader->thumbnail_image, $this->_g_oNewAppHeader->module_srl, $this->_g_oNewAppHeader->package_srl);
                if(!$oFileRst || !$oFileRst->toBool())
                    return $oFileRst;
                $oFileController->setFilesValid($this->_g_oNewAppHeader->package_srl);
                $oParam->thumb_file_srl = $oFileRst->get('file_srl');
                unset($oFileRst);
                unset($oFileController);
            }
            elseif($this->_g_oNewAppHeader->thumbnail_image['size']) // excel bulk mode
            {
                echo 'yes img->'.$this->_g_oNewAppHeader->thumbnail_image['name'].'<BR>';
                $oFileRst = $oFileController->insertFile($this->_g_oNewAppHeader->thumbnail_image, $this->_g_oNewAppHeader->module_srl, $this->_g_oNewAppHeader->package_srl, 0, true);
                if(!$oFileRst || !$oFileRst->toBool())
                    return $oFileRst;
                $oFileController->setFilesValid($this->_g_oNewAppHeader->package_srl);
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
        $oInsertRst = executeQuery('svmarket.insertApp', $oParam);
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
 * @brief 기존 패키지 정보 적재
 **/
	public function loadHeader($oParams)
	{
		switch($oParams->mode)
		{
			case 'import':
				if(!$oParams->oRawData)
					return new BaseObject(-1,'msg_import_load_without_rawdata');
				$this->_initHeader();
				$oTmpRst = new BaseObject();
				$oTmpRst->data = $oParams->oRawData;
				break;
			default:
				$this->_initHeader();
				$oTmpArgs = new stdClass();
				$oTmpArgs->package_srl = $oParams->nPkgSrl;
				$oTmpRst = executeQuery('svmarket.getPkgDetail', $oTmpArgs);
				if(!$oTmpRst->toBool())
					return $oTmpRst;
				if(!is_object($oTmpRst->data))
					return new BaseObject(-1,'msg_invalid_pkg_request');
					
				break;
		}
		$this->_matchOldPkgInfo($oTmpRst->data);
		//$this->_setReviewCnt(); // 후기수 설정
		//

		$oModuleModel = getModel('module');
		$oModuleInfo = $oModuleModel->getModuleInfoByModuleSrl($this->_g_oOldAppHeader->module_srl);
		$this->_g_oOldAppHeader->mid = $oModuleInfo->mid;
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
		if($this->_g_oOldAppHeader->category_node_srl > 0)
		{
	//////////////////////// getCatalog() 가져오기
			$oSvmakretModel = &getModel('svmarket');
			$nModuleSrl = $this->_g_oOldAppHeader->module_srl;
			$this->_g_oOldAppHeader->oCatalog = $oSvitemModel->getCatalog($nModuleSrl, $this->_g_oOldAppHeader->category_node_srl);
	////////////////////////
			if(strlen($this->_g_oOldAppHeader->enhanced_item_info->ga_category_name) == 0)
				$this->_g_oOldAppHeader->enhanced_item_info->ga_category_name = $this->_g_oOldAppHeader->oCatalog->current_catalog_info->node_name;
			unset($nModuleSrl);
		}

		// for sns share
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($this->_g_oOldAppHeader->package_srl);
		// $this->_g_oOldAppHeader->enhanced_item_info->item_brief = $oDocument->getContent(false);

		$oDbInfo = Context::getDBInfo();
		$oSnsInfo = new stdClass();
		$oSnsInfo->sPermanentUrl = $oDocument->getPermanentUrl().'?l='.$oDbInfo->lang_type;
		$oSnsInfo->sEncodedDocTitle = urlencode($this->_g_oOldAppHeader->title);
		$this->_g_oOldAppHeader->oSnsInfo = $oSnsInfo;
		unset($oDbInfo);
		unset($oDocument);
		unset($oDocumentModel);
		return new BaseObject();
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
 * @brief 기존 패키지 정보 변경
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
		$oArgs->package_srl = $this->_g_oOldAppHeader->package_srl; // package_srl은 수정하면 안됨
		if($this->_g_oNewAppHeader->module_srl)
			$oArgs->module_srl = $this->_g_oNewAppHeader->module_srl;
		if($this->_g_oNewAppHeader->list_order)
			$oArgs->list_order = $this->_g_oNewAppHeader->list_order;
		if($this->_g_oNewAppHeader->category_node_srl)
			$oArgs->category_node_srl = $this->_g_oNewAppHeader->category_node_srl;
		if($this->_g_oNewAppHeader->title)
			$oArgs->title = $this->_g_oNewAppHeader->title;

		if($this->_g_oNewAppHeader->thumb_file_srl)
			$oArgs->thumb_file_srl = $this->_g_oNewAppHeader->thumb_file_srl;
		if($this->_g_oNewAppHeader->description)
			$oArgs->description = $this->_g_oNewAppHeader->description;
		if($this->_g_oNewAppHeader->homepage)
			$oArgs->homepage = $this->_g_oNewAppHeader->homepage;
		if($this->_g_oNewAppHeader->tags)
			$oArgs->tags = $this->_g_oNewAppHeader->tags;
		if($this->_g_oNewAppHeader->display)
			$oArgs->display = $this->_g_oNewAppHeader->display;
		
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
 * @brief 헤더에 extra_vars 설정
 * svmarket.item_consumer.php::_consturctExtraVars()와 통일성 유지
 **/
	private function _consturctExtraVars($oParams = null)
	{
		require_once(_XE_PATH_.'modules/svmarket/svmarket.extravar.controller.php');
		$oExtraVarsController = new svmarketExtraVarController();
		
		$oArg->nItemSrl = $this->_g_oOldItemHeader->item_srl;
		$oArg->nModuleSrl = $this->_g_oOldItemHeader->module_srl;
		$this->_g_oOldItemHeader->extra_vars = $oExtraVarsController->getExtendedVarsNameValueByItemSrl($oArg);
		foreach( self::A_ITEM_HEADER_TYPE as $nTypeIdx => $sHeaderType )
		{
			foreach( $aExtraVar as $nIdx => $oExtraVar )
			{
				$sAttrName = $oExtraVar->name;
				$this->{$sHeaderType}->{$sAttrName} = svmarket::S_NULL_SYMBOL;
			}
		}
		unset($oArg);
		unset($oExtraVarsController);
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
	private function _matchOldItemInfo($oItemArgs)
	{
		$aIgnoreVar = array('module', 'mid', 'act', '__related_items', '__extra_vars' );
		foreach( $oItemArgs as $sTitle => $sVal)
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