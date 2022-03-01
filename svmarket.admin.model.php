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
    public function getSvmarketAdminAppList($oParam)
    {
        $oRst = $this->_getAppList($oParam);
        return $oRst;
    }
    /**
    * @brief
    **/
    private function _getAppList($oParam)
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
        if(!is_null($oParam->order_type) && $oParam->order_type != 0)
            $oArg->order_type = $oParam->order_type;
        if(!is_null($oParam->item_name) && strlen($oParam->item_name) > 0)
            $oArg->item_name = $oParam->item_name;

    //dispSvpromotionAdminItemDiscountList 위해서 임시 유지 시작
        if(is_null($oArg->module_srl) || $oArg->module_srl == 0)
            $oArg->module_srl = Context::get('module_srl');
        if(!is_null($oParam->page) && $oParam->page != 0)
            $oArg->page = Context::get('page');
    //dispSvpromotionAdminItemDiscountList 위해서 임시 유지 끝
        
        $oRst = executeQueryArray('svmarket.getAdminAppList', $oArg);
        unset($oArg);
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
}
/* End of file svmarket.admin.model.php */
/* Location: ./modules/svmarket/svmarket.admin.model.php */