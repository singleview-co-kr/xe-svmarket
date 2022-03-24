<?php
/* Copyright (C) singleview.co.kr <http://singleview.co.kr> */
/**
 * @class  svmarketView
 * @author singleview.co.kr (root@singleview.co.kr)
 * @brief module view class
 */
class svmarketView extends svmarket
{
	/**
	 * @brief Initialization
	 */
	function init()
	{
		$this->setTemplatePath($this->module_path.'skins/'.$this->module_info->skin);
	}

	/**
	 * @brief General request output
	 */
	function dispSvmarketIndex()
	{
        $oArg = Context::getRequestVars();
        switch($oArg->mode)
        {
            case 'checkdate':
				// Force the result output to be of XMLRPC
				Context::setResponseMethod("XMLRPC");
                $this->_checkUpdateDateXml();
                exit;
            case 'applist':
				// Force the result output to be of XMLRPC
				Context::setResponseMethod("XMLRPC");
                $this->_pushPackageListXml();
                exit;
            case 'checkcore':
                // Force the result output to be of XMLRPC
				Context::setResponseMethod("XMLRPC");
                $this->_pushCoreVersionListXml();
                exit;
        }
		if($oArg->document_srl)
			$this->_showDetail();
		else
			$this->_showPackageList();
    }
	/**
	 * @brief 
	 */
    function _showDetail()
    {
		$oArg = Context::getRequestVars();
		$oParams = new stdClass();
		if($oArg->document_srl)
			$oParams->package_srl = $oArg->document_srl;
		else
			return new BaseObject(-1,'msg_invalid_pkg_request');

        $oSvmarketModel = getModel('svmarket');
        $oRst = $oSvmarketModel->classifyReqByDocumentSrl($this->module_info->module_srl, $oArg->document_srl);
        if(!$oRst->toBool())
            return $oRst;
        $sReqType = $oRst->get('req_type');
        unset($oRst);
        switch($sReqType)
        {
            case 'package':
                require_once(_XE_PATH_.'modules/svmarket/svmarket.pkg_admin.php');
                $oPkgAdmin = new svmarketPkgAdmin();
                $oTmpRst = $oPkgAdmin->loadHeader($oParams);
                if(!$oTmpRst->toBool())  // display package
                    return $oTmpRst;
                unset($oTmpRst);
                $oPkgDetailRst = $oPkgAdmin->loadDetail();
                if(!$oPkgDetailRst->toBool())
                    return $oPkgDetailRst;
                unset($oPkgDetailRst);
                $oPkgAdmin->updateReadedCount();

                // begin - load SEO
                $oSeoController = getController('seo');
                $oParam = new stdClass();
                $oParam->bDocument = true;
                $oParam->sTitle = $oPkgAdmin->title;
                $oParam->sOgDesc = $oPkgAdmin->og_description;
                $oParam->sDisplay = $oPkgAdmin->display;
                $oParam->sAuthor = $oPkgAdmin->nick_name;
                $oParam->sTags = $oPkgAdmin->tags;
                $oSeoController->loadSeoInfoBeforeDisplay($oParam);
                unset($oParam);
                unset($oSeoController);
                // end - load SEO

                // set browser title
                Context::setBrowserTitle(strip_tags($oPkgAdmin->title).' - '.Context::getBrowserTitle());
                Context::set('oPkgInfo', $oPkgAdmin);
                $this->setTemplateFile('pkg_detail');
                break;
            case 'app':
                $oParams->app_srl = $oArg->document_srl;
                require_once(_XE_PATH_.'modules/svmarket/svmarket.app_admin.php');
                $oAppAdmin = new svmarketAppAdmin();
                $oAppDetailRst = $oAppAdmin->loadHeader($oParams);
                if(!$oAppDetailRst->toBool())
                    return $oAppDetailRst;
                unset($oAppDetailRst);
                $oAppDetailRst = $oAppAdmin->loadDetail();
                if(!$oAppDetailRst->toBool())
                    return $oAppDetailRst;
                unset($oAppDetailRst);
                $oAppAdmin->updateReadedCount();

                // begin - load SEO
                $oSeoController = getController('seo');
                $oParam = new stdClass();
                $oParam->bDocument = true;
                $oParam->sTitle = $oAppAdmin->title;
                $oParam->sOgDesc = $oAppAdmin->og_description;
                $oParam->sDisplay = $oAppAdmin->display;
                $oParam->sAuthor = $oAppAdmin->nick_name;
                $oParam->sTags = $oAppAdmin->tags;
                $oSeoController->loadSeoInfoBeforeDisplay($oParam);
                unset($oParam);
                unset($oSeoController);
                // end - load SEO
                // set browser title
                Context::setBrowserTitle(strip_tags($oAppAdmin->title).' - '.strip_tags($oAppAdmin->package_title).' - '.Context::getBrowserTitle());
                Context::set('oAppInfo', $oAppAdmin);
                $this->setTemplateFile('app_detail');
                break;
            case 'version':
                $oParams->version_srl = $oArg->document_srl;
                require_once(_XE_PATH_.'modules/svmarket/svmarket.version_admin.php');
                $oVersionAdmin = new svmarketVersionAdmin();
                $oVersionDetailRst = $oVersionAdmin->loadHeader($oParams);
                if(!$oVersionDetailRst->toBool())
                    return $oVersionDetailRst;
                unset($oVersionDetailRst);
                $oVersionDetailRst = $oVersionAdmin->loadDetail();
                if(!$oVersionDetailRst->toBool())
                    return $oVersionDetailRst;
                unset($oVersionDetailRst);
                $oVersionAdmin->updateReadedCount();
                // begin - load SEO
                $oSeoController = getController('seo');
                $oParam = new stdClass();
                $oParam->bDocument = true;
                $oParam->sTitle = $oVersionAdmin->version;
                $oParam->sOgDesc = $oVersionAdmin->og_description;
                $oParam->sDisplay = $oVersionAdmin->display;
                $oParam->sAuthor = $oVersionAdmin->nick_name;
                //$oParam->sTags = $oVersionAdmin->tags;
                $oSeoController->loadSeoInfoBeforeDisplay($oParam);
                unset($oParam);
                unset($oSeoController);
                // end - load SEO
                // set browser title
                Context::setBrowserTitle(strip_tags($oVersionAdmin->version).' - '.strip_tags($oVersionAdmin->app_title).' - '.Context::getBrowserTitle());
                Context::set('oVersionInfo', $oVersionAdmin);
                $this->setTemplateFile('version_detail');
                break;
            default:
                return new BaseObject(-1,'msg_invalid_pkg_request');
        }
    }
	/**
	 * @brief 스킨에서 호출하는 메쏘드
	 * will be deprecated
	 */	
	public static function dispThumbnailUrl($nThumbFileSrl, $nWidth = 80, $nHeight = 0, $sThumbnailType = 'crop')
	{
		$sNoimgUrl = Context::getRequestUri().'/modules/svmarket/tpl/imgs/no_img_80x80.jpg';
		if(!$nThumbFileSrl) // 기본 이미지 반환
			return $sNoimgUrl;
		if(!$nHeight)
			$nHeight = $nWidth;
		// Define thumbnail information
		$sThumbnailPath = 'files/cache/thumbnails/'.getNumberingPath($nThumbFileSrl, 3);
		$sThumbnailFile = $sThumbnailPath.$nWidth.'x'.$nHeight.'.'.$sThumbnailType.'.jpg';
		$sThumbnailUrl = Context::getRequestUri().$sThumbnailFile; //http://127.0.0.1/files/cache/thumbnails/840/80x80.crop.jpg"
		// Return false if thumbnail file exists and its size is 0. Otherwise, return its path
		if(file_exists($sThumbnailFile) && filesize($sThumbnailFile) > 1) 
			return $sThumbnailUrl;

		// Target File
		$oFileModel = getModel('file');
		$sSourceFile = NULL;
		$sFile = $oFileModel->getFile($nThumbFileSrl);
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
    function _showPackageList()
    {
        require_once(_XE_PATH_.'modules/svmarket/svmarket.pkg_admin.php');
		$oRst = executeQueryArray('svmarket.getLatestPkg');
        $oArgs = new stdClass();
        $aPackageList = [];
		foreach($oRst->data as $nIdx => $oPackage)
		{
            $oArgs->package_srl = $oPackage->package_srl;
            $oPkgAdmin = new svmarketPkgAdmin();
            $oTmpRst = $oPkgAdmin->loadHeader($oArgs);
            if(!$oTmpRst->toBool())
                return new BaseObject(-1,'msg_invalid_pkg_request');
            unset($oTmpRst);
            $oDetailRst = $oPkgAdmin->loadDetail();
            if(!$oDetailRst->toBool())
                return $oDetailRst;
            unset($oDetailRst);
            $aPackageList[] = $oPkgAdmin;
		}
        //$oPackage->item_screenshot_url = svmarketView::dispThumbnailUrl($oPackage->item_screenshot_url,80);
        unset($oArgs);
        unset($oRst);
		Context::set('aPackageList', $aPackageList);
        $this->setTemplateFile('index');
    }
	/**
	 * @brief svmarket server active status XML 통지
	 */
    function _checkUpdateDateXml()
    {
        require_once(_XE_PATH_.'modules/svmarket/svmarket.pkg_admin.php');
        $oPkgAdmin = new svmarketPkgAdmin();
        echo $oPkgAdmin->checkUpdateDateXml();
    }
	/**
	 * @brief svmarket server active package list XML 통지
	 */
    function _pushPackageListXml()
    {
        require_once(_XE_PATH_.'modules/svmarket/svmarket.pkg_admin.php');
        $oPkgAdmin = new svmarketPkgAdmin();
        echo $oPkgAdmin->pushPackageListXml();
	}
    /**
	 * @brief core version list XML 통지
	 */
    function _pushCoreVersionListXml()
    {
        //echo __FILE__.':'.__LINE__.'<BR>';
        require_once(_XE_PATH_.'modules/svmarket/svmarket.app_admin.php');
        $oAppAdmin = new svmarketAppAdmin();
        $oArgs = new stdClass();
        $oArgs->type_srl = $oAppAdmin::A_APP_TYPE['core'];
        $oTmpRst = executeQuery('svmarket.getAppByType', $oArgs);
        unset($oArgs);
        if(!$oTmpRst->toBool())
            return $oTmpRst;
        if(!is_object($oTmpRst->data)) 
            exit;
        unset($oAppAdmin);

        $oArgs = new stdClass();
        $oArgs->package_srl = $oTmpRst->data->package_srl;
        unset($oTmpRst);
        require_once(_XE_PATH_.'modules/svmarket/svmarket.pkg_admin.php');
		$oPkgAdmin = new svmarketPkgAdmin();
		$oTmpRst = $oPkgAdmin->loadHeader($oArgs);
        unset($oArgs);
		if(!$oTmpRst->toBool())
			return new BaseObject(-1,'msg_invalid_pkg_request');
		unset($oTmpRst);
		$oDetailRst = $oPkgAdmin->loadDetail();
		if(!$oDetailRst->toBool())
			return $oDetailRst;
		unset($oDetailRst);

        $sAppstoreHost = 'http://singleview.co.kr/'.$this->module_info->mid;
        $nAppSrl = $oPkgAdmin->app_list[0]->app_srl;
        // get latest version
        $sLatestVersion = trim($oPkgAdmin->app_list[0]->version_list[0]->version);
        $nPkgSrl = $oPkgAdmin->package_srl;
        $sOutput = '<?xml version="1.0"?>';
        $sOutput .= '<zbxe_news released_version="'.$sLatestVersion.'" download_link="'.$sAppstoreHost.'/'.$nPkgSrl.'">';
        foreach($oPkgAdmin->app_list[0]->version_list as $nIdx=>$oVersion)
            $sOutput .= '<item url="'.$sAppstoreHost.'/'.$nAppSrl.'" date="'.$oVersion->regdate.'">XE Core SV ver. '.$oVersion->version.'</item>';
        $sOutput .= '</zbxe_news>';
        echo $sOutput;

// <zbxe_news released_version="1.11.6" download_link="https://mp.singleview.co.kr/download&amp;package_id=22756225&amp;release_id=22756181">
// 	<item url="https://xe1.xpressengine.com/index.php?mid=download&amp;package_id=18325662&amp;release_id=22756225" date="20190402125404">XE Core ver. 1.11.6</item>
// 	<item url="https://xe1.xpressengine.com/index.php?mid=download&amp;package_id=18325662&amp;release_id=22756181" date="20190402125404">XE Core ver. 1.11.5</item>
// </zbxe_news>
        exit;
	}   
}
/* End of file svmarket.view.php */
/* Location: ./modules/svmarket/svmarket.view.php */
