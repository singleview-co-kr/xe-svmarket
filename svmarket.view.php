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
		echo __FILE__;
        $this->setTemplateFile('detail');
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
	 * @brief svmarket server active status 통지
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
        /*'<?xml version="1.0" encoding="UTF-8"?>
        <response>
        <error>0</error>
        <message>success</message>
        <updatedate><![CDATA[20210805151519]]></updatedate>
        </response>';*/
    }
    function _pushAppListXml()
    {
        $oRst = executeQuery('svmarket.getLatestPkg');
		$sXmlResp = svmarketXmlGenerater::generatePkgList($oRst->data);
		echo $sXmlResp;
/*
        echo '<?xml version="1.0" encoding="UTF-8"?>
<response>
	<error>0</error>
	<message>success</message>
	<packageList>
		<item>
			<category_srl>18322943</category_srl>
			<package_srl>22657234</package_srl>
			<path>
				<![CDATA[./addons/xdt_google_analytics]]>
			</path>
			<title>
				<![CDATA[xe111 design team Google analytics Addon]]>
			</title>
			<homepage>
				<![CDATA[http://www.xedesignteam.com/]]>
			</homepage>
			<package_description>
				<![CDATA[싱글뷰의 코드를 달 수 있는 애드온입니다. Google, Google Analytics는 Google inc.의 상표입니다.]]>
			</package_description>
			<package_voter>6</package_voter>
			<package_voted>60</package_voted>
			<package_downloaded>1039</package_downloaded>
			<package_regdate>
				<![CDATA[20140327011542]]>
			</package_regdate>
			<package_last_update>
				<![CDATA[20210805151519]]>
			</package_last_update>
			<nick_name>
				<![CDATA[도라미]]>
			</nick_name>
			<item_srl>22756278</item_srl>
			<item_screenshot_url>
				<![CDATA[https://download.xpressengine.com/xedownload/app/22657234/thumbnails/md.png]]>
			</item_screenshot_url>
			<item_version>
				<![CDATA[1.2]]>
			</item_version>
			<item_voter>0</item_voter>
			<item_voted>0</item_voted>
			<item_downloaded>147</item_downloaded>
			<item_regdate>
				<![CDATA[20210805151519]]>
			</item_regdate>
			<package_star>5</package_star>
		</item>
	</packageList>
	<page_navigation>
		<total_count>10</total_count>
		<total_page>1</total_page>
		<cur_page>1</cur_page>
		<page_count>10</page_count>
		<first_page>1</first_page>
		<last_page>135</last_page>
		<point>0</point>
	</page_navigation>
</response>';
*/
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
