<?php
/* Copyright (C) singleview.co.kr <http://singleview.co.kr> */
/**
 * @class  svmarketAdminModel
 * @author singleview.co.kr (root@singleview.co.kr)
 * @brief module admin model class
 */
class svmarketAdminModel extends svmarket
{
/**
 * @brief Contructor
 **/
	public function init() 
	{
		$oLoggedInfo = Context::get('logged_info');
		if($oLoggedInfo->is_admin!='Y')
			return new BaseObject(-1, 'msg_login_required');
	}
    /**
     * @brief
     **/
    public function getSvmarketAdminPkgList($oParam)
    {
        $oArg = new stdClass();
        if(!is_null($oParam->module_srl) && $oParam->module_srl != 0)
            $oArg->module_srl = $oParam->module_srl;
        if(!is_null($oParam->category_node_srl))
            $oArg->category_node_srl = $oParam->category_node_srl;
        if(!is_null($oParam->page) && $oParam->page != 0)
            $oArg->page = $oParam->page;
        if(!is_null($oParam->list_count) && $oParam->list_count != 0)
            $oArg->list_count = $oParam->list_count;
        if(!is_null($oParam->sort_index) && $oParam->sort_index != 0)
            $oArg->sort_index = $oParam->sort_index;
        if(!is_null($oParam->title) && strlen($oParam->title) > 0)
            $oArg->title = $oParam->title;
        $oRst = executeQueryArray('svmarket.getAdminPkgList', $oArg);
        unset($oArg);

        // $oFileModel = getModel('file');
        // foreach($oRst->data as $nIdx => $oApp)
        // {
        //     $oFile = $oFileModel->getFile($oApp->thumb_file_srl);
		// 	if($oFile)
		// 		$sThumbnailUrl = getFullUrl().$oFile->download_url;
		// 	unset($oFile);
        // }
		// unset($oFileModel);

        // $oSvmarketModel = getModel('svmarket');
        $oModuleModel = getModel('module');
        foreach($oRst->data as $key=>$val)
        {
            $oModuleInfo = $oModuleModel->getModuleInfoByModuleSrl($val->module_srl);
            $val->mid = $oModuleInfo->mid;
            // $val->review_count = $osSmarketModel->getReviewCnt($val->item_srl);
            unset($oModuleInfo);
        }
        // unset($oSvmarketModel);
        unset($oModuleModel);
        return $oRst;
    }
/**
 * @brief 
 **/
	public function getSvmarketAdminDeleteMod() 
	{
		$oModuleModel = getModel('module');
		$module_srl = Context::get('module_srl');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
		Context::set('module_info', $module_info);
		$oTemplate = &TemplateHandler::getInstance();
		$tpl = $oTemplate->compile($this->module_path.'tpl', 'form_delete_mod');
		$this->add('tpl', str_replace("\n"," ",$tpl));
	}
}
/* End of file svmarket.admin.model.php */
/* Location: ./modules/svmarket/svmarket.admin.model.php */