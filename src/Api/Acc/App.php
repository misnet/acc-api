<?php
namespace Kuga\Api\Acc;
use Kuga\Core\Api\Exception as ApiException;
use Kuga\Core\Base\ModelException;
use Kuga\Module\Acc\Model\AppModel;
use Kuga\Module\Acc\Model\UserBindAppModel;
use Kuga\Module\Acc\Model\UserModel;
use Kuga\Module\Acc\Service\Acc;

class App extends BaseApi{


    /**
     * APP列表
     * @return array
     * @throws ApiException
     */
    public function appList(){
        //验证权限
        //当前用户的应用中，必须有ACC中心的授权
        $hasPrivilege = UserBindAppModel::count([
            'uid=:uid: and appId=:aid:',
            'bind'=>['uid'=>$this->_userMemberId,'aid'=>Acc::getAccAppKey()]
        ]);
        if(!$hasPrivilege){
            throw new ApiException(ApiException::$EXCODE_FORBIDDEN);
        }
        $acc     = $this->_di->getShared('aclService');
        $isAllow = $acc->isAllowed('RES_APP', 'OP_LIST');
        if ( ! $isAllow) {
            throw new ApiException($this->translator->_('对不起，您无权限进行此操作'),ApiException::$EXCODE_FORBIDDEN);
        }
        $data = $this->_toParamObject($this->getParams());
        $data['limit'] || $data['limit'] = 10;
        $data['page'] || $data['page'] = 1;
        $list  = AppModel::find(
            [
                'limit'   => $data['limit'],
                'offset' => ($data['page'] - 1) * $data['limit'],
                'order' => 'id desc']
        );
        $total = AppModel::count();
        return ['list' => $list->toArray(), 'total' => $total, 'page' => $data['page'], 'limit' => $data['limit']];
    }

    /**
     * 创建应用
     * @return bool
     * @throws ApiException
     */
    public function create(){

        $acc     = $this->_di->getShared('aclService');
        $isAllow = $acc->isAllowed('RES_APP', 'OP_ADD');
        if ( ! $isAllow) {
            throw new ApiException($this->translator->_('对不起，您无权限进行此操作'),ApiException::$EXCODE_FORBIDDEN);
        }
        $data                 = $this->_toParamObject($this->getParams());
        $model                = new AppModel();
        $model->name          = $data['name'];
        $model->shortDesc     = trim($data['shortDesc']);
        $model->secret        = $model->generateSecret();
        $model->disabled      = intval($data['disabled'])>0?1:0;
        $result               = $model->create();
        return $result;
    }
    /**
     * 更新应用
     * @return bool
     * @throws ApiException
     */
    public function update(){

        $acc     = $this->_di->getShared('aclService');
        $isAllow = $acc->isAllowed('RES_APP', 'OP_UPDATE');
        if ( ! $isAllow) {
            throw new ApiException($this->translator->_('对不起，您无权限进行此操作'),ApiException::$EXCODE_FORBIDDEN);
        }
        $data                 = $this->_toParamObject($this->getParams());
        $model                = AppModel::findFirstById($data['id']);
        if(!$model){
            throw new ApiException(ApiException::$EXCODE_NOTEXIST);
        }
        $model->name          = $data['name'];
        $model->shortDesc     = trim($data['shortDesc']);
        if($data['autoCreateSecret']){
            $model->secret    = $model->generateSecret();
        }
        $model->disabled      = intval($data['disabled'])>0?1:0;
        $result               = $model->update();
        return $result;
    }
}