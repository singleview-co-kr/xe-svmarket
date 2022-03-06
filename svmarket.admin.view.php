<?php
/* Copyright (C) singleview.co.kr <http://singleview.co.kr> */
/**
 * @class  svmarketAdminView
 * @author singleview.co.kr (root@singleview.co.kr)
 * @brief module admin view class
 */
class svmarketAdminView extends svmarket
{
	var $module_srl = 0;
	var $list_count = 20;
	var $page_count = 10;

	/**
	 * @brief Initialization
	 */
	function init()
	{
		// Pre-check if module_srl exists. Set module_info if exists
		$module_srl = Context::get('module_srl');
		// Create module model object
		$oModuleModel = getModel('module');
		// module_srl two come over to save the module, putting the information in advance
		if($module_srl)
		{
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
			if(!$module_info)
			{
				Context::set('module_srl','');
				$this->act = 'list';
			}
			else
			{
				ModuleModel::syncModuleToSite($module_info);
				$this->module_info = $module_info;
				Context::set('module_info',$module_info);
			}
		}
		// Get a list of module categories
		$module_category = $oModuleModel->getModuleCategories();
		Context::set('module_category', $module_category);
		//Security
		$security = new Security();
		$security->encodeHTML('module_category..title');

		// Get a template path (page in the administrative template tpl putting together)
		$this->setTemplatePath($this->module_path.'tpl');
	}
	/**
	 * @brief default admin view
	 */
	public function dispSvmarketAdminModList() 
	{
		$oModuleModel = &getModel('module');
		$oArgs = new stdClass();
		$oArgs->sort_index = "module_srl";
		$oArgs->page = Context::get('page');
		$oArgs->list_count = 20;
		$oArgs->page_count = 10;
		$oArgs->s_module_category_srl = Context::get('module_category_srl');
		$oRst = executeQueryArray('svmarket.getSvmarketList', $oArgs);
		$aList = $oModuleModel->addModuleExtraVars($oRst->data);
		Context::set('total_count', $oRst->total_count);
		Context::set('total_page', $oRst->total_page);
		Context::set('page', $oRst->page);
		Context::set('page_navigation', $oRst->page_navigation);
		Context::set('list', $aList);
		$oModuleModel = &getModel('module');
		$module_category = $oModuleModel->getModuleCategories();
		Context::set('module_category', $module_category);
		$this->setTemplateFile('modlist');
	}
	/**
	 * @brief 
	 */
	public function dispSvmarketAdminInsertMod() 
	{
		// 스킨 목록을 구해옴
		$oModuleModel = &getModel('module');
		$skin_list = $oModuleModel->getSkins($this->module_path);
		Context::set('skin_list',$skin_list);
		$mskin_list = $oModuleModel->getSkins($this->module_path, "m.skins");
		Context::set('mskin_list', $mskin_list);
		// 레이아웃 목록을 구해옴
		$oLayoutModel = &getModel('layout');
		$layout_list = $oLayoutModel->getLayoutList();
		Context::set('layout_list', $layout_list);
		$mobile_layout_list = $oLayoutModel->getLayoutList(0,"M");
		Context::set('mlayout_list', $mobile_layout_list);
		
		$module_category = $oModuleModel->getModuleCategories();
		Context::set('module_category', $module_category);

		// Set a template file
		$this->setTemplateFile('insertmod');
	}
    /**
     * @brief admin view for item list
     */
	public function dispSvmarketAdminPkgListByModule() 
	{
		$nModuleSrl = (int)Context::get('module_srl');
		if(!$nModuleSrl)
			return new BaseObject(-1, 'msg_invalid_request');

		$list_count = Context::get('disp_numb');
		$sort_index = Context::get('sort_index');
		$order_type = Context::get('order_type');
		if(!$list_count) 
			$list_count = 30;
		if(!$sort_index) 
			$sort_index = "list_order";
		if(!$order_type) 
			$order_type = 'asc';
		
		$sSearchItemName = Context::get('search_item_name');
        $oArgs = new stdClass();
		if(strlen($sSearchItemName))
			$oArgs->item_name = $sSearchItemName;
		$oArgs->module_srl = $nModuleSrl;
		$oArgs->page = Context::get('page');
		$oArgs->list_count = $list_count;
		$oArgs->sort_index = $sort_index;
		$oArgs->order_type = $order_type;
		
		$oSvitemAdminModel = getAdminModel('svmarket');
		$oRst = $oSvitemAdminModel->getSvmarketAdminPkgList($oArgs);
		if(!$oRst->toBool())
			return $oRst;
        unset($oArgs);
		Context::set('total_count', $oRst->total_count);
		Context::set('total_page', $oRst->total_page);
		Context::set('page', $oRst->page);
		Context::set('page_navigation', $oRst->page_navigation);
		Context::set('list', $oRst->data);
		// showwindow display
		$this->setTemplateFile('applist');
	}
	/**
     * @brief 
     */
	public function dispSvmarketAdminInsertPkg() 
	{
		$this->setTemplateFile('insert_pkg');
	}
    /**
     * @brief 
     */
	public function dispSvmarketAdminInsertApp() 
	{
		$this->setTemplateFile('insert_app');
	}
}
/* End of file svmarket.class.php */
/* Location: ./modules/svmarket/svmarket.class.php */
