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
                $this->_pushAppListXml();
                exit;
        }
		if($oArg->document_srl)
			$this->_showAppDetail();
		else
			$this->_showAppList();
    }
	/**
	 * @brief svmarket server active status 통지
	 */
    function _showAppDetail()
    {
		$oArg = Context::getRequestVars();
		$oParams = new stdClass();
		if($oArg->document_srl) // svitem.view.php::dispSvitemItemDetail()에서 호출
			$oParams->nPkgSrl = $oArg->document_srl;
		else
			return new BaseObject(-1,'msg_invalid_item_request');
		unset($oArg);
		require_once(_XE_PATH_.'modules/svmarket/svmarket.pkg_consumer.php');
		$oPkgConsumer = new svmarketPkgConsumer();
		$oParams->mode = 'retrieve';
		$oTmpRst = $oPkgConsumer->loadHeader($oParams);
		if(!$oTmpRst->toBool())
			return new BaseObject(-1,'msg_invalid_item_request');
		unset($oTmpRst);
		$oDetailRst = $oPkgConsumer->loadDetail();
		if(!$oDetailRst->toBool())
			return $oDetailRst;
		unset($oDetailRst);

		// set browser title
		Context::setBrowserTitle(strip_tags($oPkgConsumer->title).' - '.Context::getBrowserTitle());

        $this->setTemplateFile('pkg_detail');
    }
	/**
	 * @brief svmarket server active status 통지
	 */
    function _showAppList()
    {
		$oRst = executeQuery('svmarket.getLatestPkg');
		foreach($oRst->data as $nIdx => $oApp)
		{
			$oApp->item_screenshot_url = svmarketView::dispThumbnailUrl($oApp->item_screenshot_url,80);
		}
		Context::set('aAppList', $oRst->data);
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
    function _pushAppListXml()
    {
        $oRst = executeQuery('svmarket.getLatestPkg');
		$sXmlResp = svmarketXmlGenerater::generatePkgList($oRst->data);
		echo $sXmlResp;
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
}
/* End of file svmarket.view.php */
/* Location: ./modules/svmarket/svmarket.view.php */
