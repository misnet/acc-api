<?php
namespace Kuga\Api\Acc;

use Kuga\Api\Acc\Exception as XException;
use Kuga\Module\Acc\Model\ConfigurationModel;

class ConfigurationApi extends BaseApi{
    private function isAllow($resCode,$opCode){
        $acc     = $this->_di->getShared('aclService');
        return $acc->isAllowed($resCode,$opCode);
    }
    public function create($params=[]){//权限验证

        $isAllow = $this->isAllow('RES_CONFIGURATION','OP_ADD');
        if(!$isAllow){
            throw new XException(XException::$EXCODE_FORBIDDEN);
        }
        $model = new ConfigurationModel();
        $model->initData($params,['id']);
        $result = $model->create();
        if($result) {
            return true;
        }else{
            throw new \Exception($model->getMessages()[0]->getMessage());
        }
    }

    /**
     * 更新
     * @param $params
     * @return true
     * @throws \Exception
     */
    public function update($params=[]){
        $isAllow = $this->isAllow('RES_CONFIGURATION','OP_UPDATE');
        if(!$isAllow){
            throw new XException(XException::$EXCODE_FORBIDDEN);
        }
        $model = ConfigurationModel::findFirstById($params['id']);
        if(!$model){
            throw new \Exception('配置项不存在');
        }
        if($model->readonly==='y'){
            throw new \Exception('配置项只读，不能修改');
        }
        $model->initData($params,['id','configKey','appId']);
        $result = $model->update();
        if($result) {
            return true;
        }else{
            throw new \Exception($model->getMessages()[0]->getMessage());
        }
    }
    /**
     *
     * @return void
     */
    public function items($params=[]){
        $page = $params['current']?:1;
        $pageSize = $params['pageSize']?:10;
        $key = 'sys_configuration_'.md5(\json_encode($params,true));
        $where = '1=1';
        $bind = [];
        if(in_array($params['isEnabled'],['y','n'])){
            $where .= ' and isEnabled=:isEnabled:';
            $bind = ['isEnabled'=>$params['isEnabled']];
        }
        if(!$params['appId']){
            throw new \Exception('appId不能为空');
        }
        $where .= ' and appId=:appId:';
        $bind['appId'] = $params['appId'];
        $list = ConfigurationModel::find([
            'conditions'=>$where,
            'bind'=>$bind,
            'offset'=>($page-1)*$pageSize,
            'limit'=>$pageSize,
            'order'=>'id desc',
        ]);
        $total = ConfigurationModel::count(
            [
                'conditions'=>$where,
                'bind'=>$bind
            ]
        );
        return [
            'list'=>$list->toArray(),
            'total'=>$total,
            'current'=>$page,
            'pageSize'=>$pageSize
        ];
    }
    /**
     * 指定Key获取配置项
     * @param $params [keys]
     * @return void
     */
    public function getItemsByKeys($params=[]){
        $list = ConfigurationModel::find([
            'configKey in ({configureKeys:array}) and appId=:appId:',
            'columns'=>'configKey,configValue,configName',
            'bind'=>['configureKeys'=>$params['keys'],'appId'=>$params['appId']],
            'order'=>'id asc',
//            'cache'=>[
//                'service'=>'phalconCache',
//                'key'=>'xpod_config'.md5(json_encode($params['keys'],true)),
//                'lifetime'=>86400
//            ]
        ]);
        return $list->toArray();
    }

    /**
     * @param $params['moduleKey']
     * @return void
     */
    public function getItemsByModule($params){
        if(empty($params['moduleKey'])){
            return [];
        }
        if(!preg_match('/^[a-zA-Z0-9_.]+$/',$params['moduleKey'])){
            return [];
        }
        $where = 'module=:k: and appId=:aid:';
        $bind = ['k'=>$params['module'],'aid'=>$params['appId']];
        if(in_array($params['isEnabled'],['y','n'])){
            $where .= ' and isEnabled=:isEnabled:';
            $bind['isEnabled'] = $params['isEnabled'];
        }
        $list = ConfigurationModel::find([
            'conditions'=>$where,
            'columns'=>'configKey,configValue,configName',
            'bind'=>$bind,
            'order'=>'id asc'
        ]);
        return $list->toArray();
    }
}