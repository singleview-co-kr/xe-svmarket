<?php
/**
 * @class  svmarketModel
 * @author singleview(root@singleview.co.kr)
 * @brief  svmarketModel
 */
class svmarketModel extends svmarket
{
/**
 * @brief
 * @return 
 **/
	public function init() 
	{
	}
/**
 * @brief classify request by document srl
 **/
	public function classifyReqByDocumentSrl($nModuleSrl, $nDocumentSrl)
	{
		if(!$nModuleSrl || !$nDocumentSrl)
			return new BaseObject(-1, 'msg_invalid_request');
        $oTmpArgs = new stdClass();
        // find package
        $oTmpArgs->package_srl = $nDocumentSrl;
		$oRst = executeQuery('svmarket.getPkgDetail', $oTmpArgs);
        if(!$oRst->toBool()) 
            return $oRst;
        if(is_object($oRst->data))
        {
            unset($oRst->data);
            $oRst->add('req_type', 'package');
            return $oRst;
        }
        unset($oTmpArgs->package_srl);
        // find app
        $oTmpArgs->app_srl = $nDocumentSrl;
		$oRst = executeQuery('svmarket.getAdminAppDetail', $oTmpArgs);
        if(!$oRst->toBool()) 
            return $oRst;
        if(is_object($oRst->data))
        {
            unset($oRst->data);
            $oRst->add('req_type', 'app');
            return $oRst;
        }
        unset($oTmpArgs->app_srl);
        // find version
        $oTmpArgs->version_srl = $nDocumentSrl;
		$oRst = executeQuery('svmarket.getAdminVersionDetail', $oTmpArgs);
        if(!$oRst->toBool()) 
            return $oRst;
        if(is_object($oRst->data))
        {
            unset($oRst->data);
            $oRst->add('req_type', 'version');
            return $oRst;
        }
	}
}
/* End of file svmarket.model.php */
/* Location: ./modules/svmarket/svmarket.model.php */