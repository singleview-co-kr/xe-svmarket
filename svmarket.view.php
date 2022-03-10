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
                // set browser title
                Context::setBrowserTitle(strip_tags($oAppAdmin->title).' - '.Context::getBrowserTitle());
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
                // set browser title
                Context::setBrowserTitle(strip_tags($oVersionAdmin->version).' - '.Context::getBrowserTitle());
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
		$sNoimgUrl = Context::getRequestUri().'/modules/svmarket/tpl/imgs/no_img_55x55.jpg';
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
		$oFileModel = &getModel('file');
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
		$oRst = executeQuery('svmarket.getLatestPkg');
		foreach($oRst->data as $nIdx => $oPackage)
		{
			$oPackage->item_screenshot_url = svmarketView::dispThumbnailUrl($oPackage->item_screenshot_url,80);
		}
		Context::set('aPackageList', $oRst->data);
        $this->setTemplateFile('index');
    }
	/**
	 * @brief svmarket server active status XML 통지
	 */
    function _checkUpdateDateXml()
    {
        $aParams = [];
        $oRst = executeQuery('svmarket.getLatestUpdatedDate');
        if(!$oRst->toBool())
        {
            unset($oRst);
            $aParams["updatedate"] = "error";
        }
        if(count((array)$oRst->data) == 0)
        {
            unset($oRst);
            $aParams["updatedate"] = "error";
        }
        else
        	$aParams["updatedate"] = $oRst->data->updatetime;
		$sXmlResp = svmarketXmlGenerater::generate($aParams);
		echo $sXmlResp;
    }
	/**
	 * @brief svmarket server active package list XML 통지
	 */
    function _pushPackageListXml()
    {
        $oRst = executeQuery('svmarket.getLatestPkg');
        foreach($oRst->data as $nIdx => $oPackage)
		{
			$oPackage->package_description = $oPackage->og_description;
		}
		$sXmlResp = svmarketXmlGenerater::generatePkgList($oRst->data);
		echo $sXmlResp;
	}
}
/* End of file svmarket.view.php */
/* Location: ./modules/svmarket/svmarket.view.php */
