<?php
/**
 * @class  svsvmarketPkgAdmin
 * @author singleview(root@singleview.co.kr)
 * @brief  svsvmarketPkgAdmin class
 */
class svmarketPkgAdmin extends svmarket
{
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
	public function create($oNewPkgArgs)
	{
		$this->_initHeader();
		$this->_matchNewPkgInfo($oNewPkgArgs);
		if($this->_g_oNewPkgHeader->module_srl == svmarket::S_NULL_SYMBOL || 
			$this->_g_oNewPkgHeader->title == svmarket::S_NULL_SYMBOL)
			return new BaseObject(-1,'msg_invalid_request');
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
		$oTmpRst = executeQuery('svmarket.getAdminPkgDetail', $oTmpArgs);
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
        if($this->_g_oOldPkgHeader->member_srl != svmarket::S_NULL_SYMBOL)
        {
            $oMemberModel = getModel('member');
            $oMemberInfo = $oMemberModel->getMemberInfoByMemberSrl($this->_g_oOldPkgHeader->member_srl);
            $this->_g_oOldPkgHeader->nick_name = $oMemberInfo->nick_name;
            unset($oMemberInfo);
		    unset($oMemberModel);
        }
        $oFileModel = getModel('file');
        $aFiles = $oFileModel->getFiles($this->_g_oOldPkgHeader->package_srl);
        $this->_g_oOldPkgHeader->thumb_file_srl = $aFiles[0]->file_srl;
        unset($aFiles);
        unset($oFileModel);
		return $oTmpRst;
	}
	/**
	* @brief 기존 패키지 상세 정보 적재
	**/
	public function loadDetail()
	{
		$this->_nullifyHeader();
		$this->_g_oOldPkgHeader->desc_for_editor = htmlentities($this->_g_oOldPkgHeader->description);
        $this->_g_oOldPkgHeader->list_order = 0;  // temporarily
        $this->_g_oOldPkgHeader->downloads = 0;  // temporarily
        $this->_g_oOldPkgHeader->reviews = 0;  // temporarily

		// begin - load packaged app list
		$oArgs = new stdClass();
		$oArgs->package_srl = $this->_g_oOldPkgHeader->package_srl;
		$oListRst = executeQueryArray('svmarket.getAdminAppList', $oArgs);
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
	 * Update read counts of the package
	 * @return bool|void
	 */
	function updateReadedCount()
	{
		// Pass if Crawler access
		if(isCrawler()) return false;
		
		$nDocumentSrl = $this->_g_oOldPkgHeader->package_srl;
		$nMemberSrl = $this->_g_oOldPkgHeader->member_srl;
		// Call a trigger when the read count is updated (before)
		// Pass if read count is increaded on the session information
		if($_SESSION['readed_document'][$nDocumentSrl]) return false;

		// Pass if the author's IP address is as same as visitor's.
		if($this->_g_oOldPkgHeader->ipaddress == $_SERVER['REMOTE_ADDR'])
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
		$args->package_srl = $nDocumentSrl;
		executeQuery('svmarket.updatePkgReadedCount', $args);
		$oDB->commit();
        unset($args);
		// Register session
		if(!$_SESSION['banned_document'][$nDocumentSrl]) 
			$_SESSION['readed_document'][$nDocumentSrl] = true;
		return TRUE;
	}
    /**
     * @brief 기존 패키지 갱신일 변경
     **/
	public function updateTimestamp()
	{
		if(!$this->_g_oOldPkgHeader)
			return new BaseObject(-1,'msg_required_to_load_old_information_first');
        $oArgs = new stdClass();
        $oArgs->package_srl = $this->_g_oOldPkgHeader->package_srl; 
        $oUpdateRst = executeQuery('svmarket.updateAdminPkgTimestamp', $oArgs);
        unset($oArgs);
        return $oUpdateRst;
	}
    /**
     * @brief 패키지 최초 갱신일을 XML 전송
     **/
	public static function checkUpdateDateXml()
	{
		$aParams = [];
        $oRst = executeQuery('svmarket.getLatestUpdatedDate');
        if(!$oRst->toBool())
            $aParams["updatedate"] = "error";
        if(count((array)$oRst->data) == 0)
            $aParams["updatedate"] = "error";
        else
        	$aParams["updatedate"] = $oRst->data->updatetime;
        unset($oRst);
        $sXmlDoc = '<?xml version="1.0" encoding="utf-8" ?><response><error>0</error>';
		$sXmlDoc .= '<message>success</message>';
		foreach($aParams as $key => $val)
			$sXmlDoc .= sprintf("<%s><![CDATA[%s]]></%s>", $key, $val, $key);
		$sXmlDoc .= "</response>";
		return $sXmlDoc;
	}
    /**
	 * @brief 기존 패키지 갱신일 순으로 XML 전송
	 */
    public static function pushPackageListXml()
    {
        $oRst = executeQuery('svmarket.getLatestPkg');
        foreach($oRst->data as $nIdx => $oPackage)
		{
			$oPackage->package_description = $oPackage->og_description;
		}
        $aAppInfo = $oRst->data;
        unset($oRst);
        $sXmlDoc = '<?xml version="1.0" encoding="utf-8" ?><response><error>0</error>';
		$sXmlDoc .= '<message>success</message>';
        $aTmpInfo = [];
        if(is_object($aAppInfo))
        {
            $aTmpInfo[] = $aAppInfo;
            $aAppInfo = $aTmpInfo;
        }
		if(!is_array($aAppInfo))
		{
			echo __FILE__.':'.__LINE__.'<BR>';
			return NULL;
		}
        $sXmlDoc .= '<packageList>';
		foreach($aAppInfo as $nIdx => $oApp)
		{
            $sXmlDoc .= '<item>';
            foreach($oApp as $key => $val)
            {
				if($key == 'item_screenshot_url')
					$val = svmarketView::dispThumbnailUrl($val,80);
                if(is_string($val))
                    $sXmlDoc .= sprintf("<%s><![CDATA[%s]]></%s>", $key, $val, $key);
                else
                    $sXmlDoc .= sprintf("<%s>%s</%s>", $key, $val, $key);
            }
            $sXmlDoc .= "<path>";
			$sXmlDoc .= "	<![CDATA[./addons/xdt_google_analytics]]>";
			$sXmlDoc .= "</path>";
            $sXmlDoc .= "<package_voter>6</package_voter>";
			$sXmlDoc .= "<package_voted>60</package_voted>";
			$sXmlDoc .= "<package_downloaded>1039</package_downloaded>";
            $sXmlDoc .= "<nick_name>";
			$sXmlDoc .= "	<![CDATA[singleview.co.kr]]>";
			$sXmlDoc .= "</nick_name>";
			$sXmlDoc .= "<item_srl>22756278</item_srl>";
			$sXmlDoc .= "<item_version>";
			$sXmlDoc .= "	<![CDATA[1.2]]>";
			$sXmlDoc .= "</item_version>";
			$sXmlDoc .= "<item_voter>0</item_voter>";
			$sXmlDoc .= "<item_voted>0</item_voted>";
			$sXmlDoc .= "<item_downloaded>147</item_downloaded>";
			$sXmlDoc .= "<item_regdate>";
			$sXmlDoc .= "	<![CDATA[20210805151519]]>";
			$sXmlDoc .= "</item_regdate>";
			$sXmlDoc .= "<package_star>5</package_star>";
            $sXmlDoc .= '</item>';	
		}
        $sXmlDoc .= '</packageList>';
        $sXmlDoc .= "<page_navigation>";
		$sXmlDoc .= "<total_count>10</total_count>";
		$sXmlDoc .= "<total_page>1</total_page>";
		$sXmlDoc .= "<cur_page>1</cur_page>";
		$sXmlDoc .= "<page_count>10</page_count>";
		$sXmlDoc .= "<first_page>1</first_page>";
		$sXmlDoc .= "<last_page>135</last_page>";
		$sXmlDoc .= "<point>0</point>";
	    $sXmlDoc .= "</page_navigation>";
        $sXmlDoc .= "</response>";
		return $sXmlDoc;
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
     * @brief set skeleton svmarket header
     * svmarket.pkg_consumer.php::_setSkeletonHeader()과 통일성 유지
     **/
    private function _setSkeletonHeader()
    {
        $aBasicAttr = ['package_srl', 'module_srl', 'list_order', 'category_node_srl', 
                        'title', 'thumb_file_srl', 'og_description', 'description', 
                        'member_srl', 'readed_count', 'ipaddress',
						'homepage', 'tags', 'display', 'updatetime', 'regdate'];
        $aInMemoryAttr = ['downloads', 'reviews','app_list', 'nick_name'];
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
     * @brief 
     **/
    private function _insertPkg()
    {
        $this->_nullifyHeader();
        if($this->_g_oNewPkgHeader->package_srl == 0)
            $this->_g_oNewPkgHeader->package_srl = getNextSequence();
        
        $oParam = new stdClass();
        $oParam->package_srl = $this->_g_oNewPkgHeader->package_srl;
        $oParam->module_srl = $this->_g_oNewPkgHeader->module_srl;
        $oParam->title = $this->_g_oNewPkgHeader->title;
        $oParam->og_description = $this->_g_oNewPkgHeader->og_description;
        $oParam->description = $this->_g_oNewPkgHeader->description;
        $oParam->homepage = $this->_g_oNewPkgHeader->homepage;
        $oParam->display = 'N'; // 최초 등록 시에는 기본 최소 정보이므로 무조건 비공개
        $oParam->list_order = $this->_g_oNewPkgHeader->package_srl * -1;
        if($this->_g_oLoggedInfo)
			$oParam->member_srl = $this->_g_oLoggedInfo->member_srl;
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
}
/* End of file svmarket.pkg_admin.php */
/* Location: ./modules/svmarket/svmarket.pkg_admin.php */