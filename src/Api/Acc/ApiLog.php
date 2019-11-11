<?php
namespace Kuga\Api\Acc;
use Kuga\Core\Api\Exception as ApiException;
use Kuga\Core\GlobalVar;
use Kuga\Core\Model\ApiLogModel;
use Kuga\Core\Service\ApiAccessLogService;

class ApiLog extends BaseApi{
    /**
     * API列表
     */
    public function items(){
        $data = $this->_toParamObject($this->getParams());
        $data['page'] || $data['page'] = 1;
        $data['limit'] || $data['limit'] = GlobalVar::DATA_DEFAULT_LIMIT;
        $model = new ApiAccessLogService($this->_di);
        $startTime = $data['startTime'];
        $endTime   = $data['endTime'];
        $total = $model->count($startTime,$endTime);
        $list  = $model->getList($data['page'],$data['limit'],$startTime,$endTime);
        return [
            'list'=>$list,
            'page'=>$data['page'],
            'limit'=>$data['limit'],
            'total'=>$total
        ];
    }
}