<?php
/**
 * @class  svsvmarketPkgConsumer
 * @author singleview(root@singleview.co.kr)
 * @brief  svsvmarketPkgConsumer class
 */
class svmarketPkgConsumer extends svmarket
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
     * svmarket.pkg_admin.php::_setSkeletonHeader()과 통일성 유지
     **/
    private function _setSkeletonHeader()
    {
        $aBasicAttr = ['package_srl', 'module_srl', 'list_order', 'category_node_srl', 
                        'title', 'thumb_file_srl', 'description', 'homepage', 'tags', 'display', 
                        'updatetime', 'regdate'];
        $aInMemoryAttr = ['review_count', 'mid']; // 'extra_vars'는 unserialize된 구조체 적재
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
                $this->{$sHeaderType}->{$sAttrName} = svmarket::S_NULL_SYMBOL;
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
				unset($oTmpArgs);
				break;
		}
		$this->_matchOldPkgInfo($oTmpRst->data);
		//$this->_setReviewCnt(); // 후기수 설정
        $this->_nullifyHeader();

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
		return new BaseObject();
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
	private function _updateItem()
	{
		$this->_nullifyHeader();
		// 기본 정보 설정
		$oArgs->item_srl = $this->_g_oOldItemHeader->item_srl; // item_srl은 수정하면 안됨
		$oArgs->module_srl = $this->_g_oNewItemHeader->module_srl;

		if( $this->_g_oNewItemHeader->item_name )
			$oArgs->item_name = $this->_g_oNewItemHeader->item_name;
		if( $this->_g_oNewItemHeader->item_code )
			$oArgs->item_code = $this->_g_oNewItemHeader->item_code;
		if( $this->_g_oNewItemHeader->barcode )
			$oArgs->barcode = $this->_g_oNewItemHeader->barcode;
		if( $this->_g_oNewItemHeader->price )
			$oArgs->price = $this->_g_oNewItemHeader->price;
		if( $this->_g_oNewItemHeader->current_stock )
			$oArgs->current_stock = $this->_g_oNewItemHeader->current_stock;
		if( $this->_g_oNewItemHeader->safe_stock )
			$oArgs->safe_stock = $this->_g_oNewItemHeader->safe_stock;
		if( $this->_g_oNewItemHeader->taxfree )
			$oArgs->taxfree = $this->_g_oNewItemHeader->taxfree;
		if( $this->_g_oNewItemHeader->display )
			$oArgs->display = $this->_g_oNewItemHeader->display;
		if( $this->_g_oNewItemHeader->list_order )
			$oArgs->list_order = $this->_g_oNewItemHeader->list_order;
		if( $this->_g_oNewItemHeader->sv_tags )
			$oArgs->sv_tags = $this->_g_oNewItemHeader->sv_tags;

		$oEnhancedItemInfo = new stdClass();
		// begin GA EEC & EXTRA info processing
		$oEnhancedItemInfo->ga_brand_name = $this->_g_oNewItemHeader->ga_brand_name ? $this->_g_oNewItemHeader->ga_brand_name : $this->_g_oOldItemHeader->enhanced_item_info->ga_brand_name;
		$oEnhancedItemInfo->ga_category_name = $this->_g_oNewItemHeader->ga_category_name ? $this->_g_oNewItemHeader->ga_category_name : $this->_g_oOldItemHeader->enhanced_item_info->ga_category_name;
		$oEnhancedItemInfo->ga_variation_name = $this->_g_oNewItemHeader->ga_variation_name ? $this->_g_oNewItemHeader->ga_variation_name : $this->_g_oOldItemHeader->enhanced_item_info->ga_variation_name;
		
		if( $this->_g_oNewItemHeader->gallery_rep_thumbnail_idx != svmarket::S_NULL_SYMBOL )
		{
			$oFileModel = getModel('file');
			$nThumFileCnt = $oFileModel->getFilesCount($this->_g_oNewItemHeader->gallery_doc_srl);
			if( $this->_g_oNewItemHeader->gallery_rep_thumbnail_idx > $nThumFileCnt - 1 )
				return new BaseObject(-1, 'msg_invalid_gallery_rep_thumbnail_idx');
			$oEnhancedItemInfo->rep_gallery_thumb_idx = $this->_g_oNewItemHeader->gallery_rep_thumbnail_idx;
		}
		if( $this->_g_oNewItemHeader->item_brief )
			$oEnhancedItemInfo->item_brief = $this->_g_oNewItemHeader->item_brief;
		
		// OG description,  mob PC 상세페이지 내용 설정
		switch( $this->_g_oNewItemHeader->ua_type )
		{
			case 'og':
				$oDocArgs->document_srl = $this->_g_oNewItemHeader->document_srl;
				$oDocArgs->module_srl = $this->_g_oNewItemHeader->module_srl;
				$oDocArgs->content = $this->_g_oNewItemHeader->description;
				$oDocArgs->title = $this->_g_oNewItemHeader->item_name;
				$oDocArgs->list_order = $this->_g_oNewItemHeader->document_srl * -1;
				$oDocArgs->tags = Context::get('tag');
				$oDocArgs->allow_comment = 'Y';
				$oDocumentModel = &getModel('document');
				$oDocumentController = &getController('document');
				$oDocRst = $oDocumentController->updateDocument($oDocumentModel->getDocument($this->_g_oNewItemHeader->document_srl), $oDocArgs);
				if (!$oDocRst->toBool())
					return $oDocRst;

				unset($oDocRst);
				unset($oDocumentModel);
				unset($oDocumentController);
				unset($oDocArgs);
				break;
			case 'mob':
				if( $this->_g_oNewItemHeader->description )
					$oArgs->mob_description = $this->_g_oNewItemHeader->description;
				break;
			case 'pc':
				if( $this->_g_oNewItemHeader->description )
					$oArgs->pc_description = $this->_g_oNewItemHeader->description;
				break;
		}

		// naver EP info processing
		$oEnhancedItemInfo->naver_ep_item_name = $this->_g_oNewItemHeader->naver_ep_item_name ? $this->_g_oNewItemHeader->naver_ep_item_name : $this->_g_oOldItemHeader->enhanced_item_info->naver_ep_item_name;
		$oEnhancedItemInfo->naver_ep_maker = $this->_g_oNewItemHeader->naver_ep_maker ? $this->_g_oNewItemHeader->naver_ep_maker : $this->_g_oOldItemHeader->enhanced_item_info->naver_ep_maker;
		$oEnhancedItemInfo->naver_ep_origin = $this->_g_oNewItemHeader->naver_ep_origin ? $this->_g_oNewItemHeader->naver_ep_origin : $this->_g_oOldItemHeader->enhanced_item_info->naver_ep_origin;
		$oEnhancedItemInfo->naver_ep_search_tag = $this->_g_oNewItemHeader->naver_ep_search_tag ? $this->_g_oNewItemHeader->naver_ep_search_tag : $this->_g_oOldItemHeader->enhanced_item_info->naver_ep_search_tag;
		$oEnhancedItemInfo->naver_ep_barcode = $this->_g_oNewItemHeader->naver_ep_barcode ? $this->_g_oNewItemHeader->naver_ep_barcode : $this->_g_oOldItemHeader->enhanced_item_info->naver_ep_barcode;
		$oEnhancedItemInfo->naver_ep_naver_category = $this->_g_oNewItemHeader->naver_ep_naver_category ? $this->_g_oNewItemHeader->naver_ep_naver_category : $this->_g_oOldItemHeader->enhanced_item_info->naver_ep_naver_category;
		$oEnhancedItemInfo->naver_ep_event_words = $this->_g_oNewItemHeader->naver_ep_event_words ? $this->_g_oNewItemHeader->naver_ep_event_words : $this->_g_oOldItemHeader->enhanced_item_info->naver_ep_event_words;
		if( $this->_g_oNewItemHeader->naver_ep_sv_campaign2 )
		{
			if(!ctype_alnum( $this->_g_oNewItemHeader->naver_ep_sv_campaign2)) 
				return new BaseObject(-1, 'msg_invalid_naverep_sv_campaign_code');
			else
				$oEnhancedItemInfo->naver_ep_sv_campaign2 = $this->_g_oNewItemHeader->naver_ep_sv_campaign2 ? $this->_g_oNewItemHeader->naver_ep_sv_campaign2 : $this->_g_oOldItemHeader->enhanced_item_info->naver_ep_sv_campaign2;
		}
		if( $this->_g_oNewItemHeader->naver_ep_sv_campaign3 )
		{
			if(!ctype_alnum( $this->_g_oNewItemHeader->naver_ep_sv_campaign3)) 
				return new BaseObject(-1, 'msg_invalid_naverep_sv_campaign_code');
			else
				$oEnhancedItemInfo->naver_ep_sv_campaign3 = $this->_g_oNewItemHeader->naver_ep_sv_campaign3 ? $this->_g_oNewItemHeader->naver_ep_sv_campaign3 : $this->_g_oOldItemHeader->enhanced_item_info->naver_ep_sv_campaign3;
		}
		
		// daum EP info processing
		$oEnhancedItemInfo->daum_ep_item_name = $this->_g_oNewItemHeader->daum_ep_item_name ? $this->_g_oNewItemHeader->daum_ep_item_name : $this->_g_oOldItemHeader->enhanced_item_info->daum_ep_item_name;
		
		// badge info processing
		if( $this->_g_oNewItemHeader->badge_icon )
		{
			$aBadgeIconToSave = [];
			foreach( $this->_g_oNewItemHeader->badge_icon as $nIdx=>$sVal)
				$aBadgeIconToSave[$sVal] = 1;
			$oEnhancedItemInfo->default_badge_icon = $aBadgeIconToSave;
		}
		else
			$oEnhancedItemInfo->default_badge_icon = $this->_g_oOldItemHeader->enhanced_item_info->default_badge_icon;
		
		// 개발자용 PC / MOB 상세 페이지 등록
		$oEnhancedItemInfo->description_skin_pc = $this->_g_oNewItemHeader->description_skin_pc ? $this->_g_oNewItemHeader->description_skin_pc : $this->_g_oOldItemHeader->enhanced_item_info->description_skin_pc;
		if( $oEnhancedItemInfo->description_skin_pc == 'use_each_pc' )
			$oEnhancedItemInfo->description_skin_pc = null;
		$oEnhancedItemInfo->description_skin_mob = $this->_g_oNewItemHeader->description_skin_mob ? $this->_g_oNewItemHeader->description_skin_mob : $this->_g_oOldItemHeader->enhanced_item_info->description_skin_mob;
		if( $oEnhancedItemInfo->description_skin_mob == 'use_each_mob' )
			$oEnhancedItemInfo->description_skin_mob = null;
		// end GA EEC & EXTRA info processing

		$oArgs->enhanced_item_info = serialize($oEnhancedItemInfo);
		unset( $oEnhancedItemInfo );
		$oUpdateRst = executeQuery('svmarket.updateAdminItem', $oArgs);
		if(!$oUpdateRst->toBool())
			return $oUpdateRst;
		unset($oUpdateRst);

		// extra_vars update
		require_once(_XE_PATH_.'modules/svmarket/svmarket.extravar.controller.php');
		$oExtraVarsController = new svmarketExtraVarController();
		// begin - retrieve extended var info
		$oParam->nModuleSrl = $this->_g_oNewItemHeader->module_srl;
		$oParam->nItemSrl = $this->_g_oNewItemHeader->item_srl;
		$oExtendedVarRst = $oExtraVarsController->getExtendedVarsNameValueByItemSrl($oParam);
		foreach( $oExtendedVarRst->data as $nIdx => $oExtVar )
		{
			$sVarTitle = $oExtVar->column_name;
			if($this->_g_oNewItemHeader->{$sVarTitle})
				$oParam->oExtendedVar->{$sVarTitle} = $this->_g_oNewItemHeader->{$sVarTitle};
		}
		unset($oExtendedVarRst);
		// end - retrieve extended var info
		//$oParam->nModuleSrl = $this->_g_oNewItemHeader->module_srl;
		//$oParam->nItemSrl = $this->_g_oNewItemHeader->item_srl;
		$oUpdateRst = $oExtraVarsController->registerOnExtendedVar($oParam);
		if(!$oUpdateRst->toBool())
			return $oUpdateRst;
		unset($oParam);
		unset($oUpdateRst);
		unset($oExtraVarsController);
		
		// 첨부 이미지 파일 처리
		$oUpdateRst = $this->_procThumbnailImages();
		return $oUpdateRst;
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