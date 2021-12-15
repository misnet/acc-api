<?php
/**
 * 通用类目API
 */
namespace Kuga\Api\Acc;
use AlibabaCloud\Client\AlibabaCloud;
use Kuga\Core\Api\AbstractApi;
use Kuga\Core\Api\ApiService;
use Kuga\Core\Api\Exception as ApiException;
use Kuga\Core\Api\Request\BaseRequest as Request;


use Kuga\Core\GlobalVar;
use Kuga\Core\Model\RegionModel;
class Common extends  AbstractApi {
    private $smsPrefix = 'sms';
    /**
     * 发送验证码
     */
    public function sendVerifyCode(){
        $data = $this->_toParamObject($this->_params);
        $pattern = "/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/";

        $seed = date('YmdHi');


        if(preg_match($pattern, $data['receive'])){
            //是Email
            $key = $this->smsPrefix . $data['countryCode']. $data['receive'] . '_' . $seed;
            $simpleStorage = $this->_di->get('simpleStorage');
            $r = $simpleStorage->get($key);
            if (! $r) {
                $r = $this->_testModel ? GlobalVar::VERIFY_CODE_FORTEST : rand(1000, 9999);
            }

            $result = $this->_testModel ? true : $this->_di->getShared('emailer')->verifyCode($data['receive'], $r);
        }elseif (preg_match('/^(13|14|15|17|18|19)[\d+]{9}$/', $data['receive'])) {
            //是手机
            $key = $this->smsPrefix . $data['countryCode']. $data['receive'] . '_' . $seed;

            $simpleStorage = $this->_di->get('simpleStorage');
            $r = $simpleStorage->get($key);
            if (! $r) {
                $r = $this->_testModel ? GlobalVar::VERIFY_CODE_FORTEST : rand(1000, 9999);
            }

            if (! isset($data['countryCode'])) {
                $data['countryCode'] = GlobalVar::COUNTRY_CODE_CHINA;
            }
            $result = $this->_testModel ? true : $this->_di->getShared('sms')->verifyCode($data['receive'], $r);
        }else{
            throw new ApiException($this->translator->_('请入邮箱或手机号'));
        }




        if ($result) {
            // 写入数据库
            $s = $simpleStorage->set($key, $r);
            // 2分钟有效
            $simpleStorage->expired($key, 300);
            return $seed;
        } else {
            throw new ApiException($this->translator->_('验证码发送失败'));
        }
    }

    /**
     * 批量请求API
     * 格式：method1: API方法名
     *      param1:  json格式字串
     *      method2: ...
     *      param2:  ...
     *
     * @return array
     */
    public function batchRequest(){
        $responseData = [];
        $pairMethod=[];
        $pairParams=[];
        foreach($this->getParams() as $key=>$value){
            preg_match('/^(method|param)([0-9]{1,})$/i',$key,$matches);

            if(!empty($matches)){
                if($matches[1]=='method'){
                    $pairMethod['r'.$matches[2]] = $value;
                }else{
                    $pairParams['r'.$matches[2]] = (is_array($value)||is_object($value))?$value:json_decode($value,true);
                }
            }
        }


        if(!empty($pairMethod)){
            ApiService::setDi($this->_di);
            foreach($pairMethod as $k=>$method){
                if(isset($pairParams[$k]) && $pairParams[$k]){
                    $params = $pairParams[$k];
                }else{
                    $params = [];
                }
                $params['method'] = $method;
                $req = $this->_createRequestObject($params);
                $responseData[$k] = ApiService::invoke($req);
            }
        }
        return $responseData;

    }

    /**
     * 取得OSS配置信息
     * 为不在程序中写死，APP需要读取本信息
     */
    public function ossSetting()
    {
        //官方说用杭州的，可以授权所有的
        $fileStorage   = $this->_di->getShared('fileStorage');
        $configSetting = $fileStorage->getOption();
        $stsRegion = $configSetting['bucket']['region'];
        AlibabaCloud::accessKeyClient($configSetting['accessKeyId'], $configSetting['accessKeySecret'])->regionId($stsRegion)->asDefaultClient();
        $result = AlibabaCloud::rpc()
            ->product('Sts')
            ->scheme('https')
            ->version('2015-04-01')
            ->action('AssumeRole')
            ->method('POST')
            ->options([
                'query'=>[
                    'RoleSessionName'=>$configSetting['roleSessionName'],
                    'RoleArn'=>$configSetting['roleArn'],
                    'Policy'=>$configSetting['policy'],
                    'DurationSeconds'=>$configSetting['tokenExpireTime']
                ]
            ])
            ->request();
        $returnData = $result->toArray();
        $returnData['Bucket'] = $configSetting['bucket'];
        return $returnData;
    }
    /**
     * 取得地区列表
     * @return array
     * @throws ApiException
     */
    public function getRegionList(){
        $data = $this->_toParamObject($this->getParams());
        $pid  = intval($data['parentId']);
        $list = RegionModel::find([
            'parentId=:pid:',
            'bind'=>['pid'=>$pid],
            'orderBy'=>'sortIndex',

            'columns'=>['id','name','parentId','(select count(0) from '.RegionModel::class.' child where child.parentId='.RegionModel::class.'.id) as childNum']
        ]);
        return $list?$list->toArray():[];
    }
    /**
     * 创建请求对象
     * @param $params
     * @return Request
     */
    private function _createRequestObject($params){
        $apiKeyFile = $this->_di->get('config')->apiKeys;
        $apiKeys = [];
        if(file_exists($apiKeyFile)){
            $apiKeys = json_decode(file_get_contents($apiKeyFile),true);
        }
        $data['appkey'] = $this->_appKey;

        foreach($params as $k=>$v){
            $data[$k]   = $v;
        }
        if(isset($this->_accessToken)){
            $data['access_token'] = $this->_accessToken;
        }
        if(isset($this->_params['appid'])){
            $data['appid'] = $this->_params['appid'];
        }
        $data['sign']   = Request::createSign($apiKeys[$this->_appKey]['secret'], $data);
        return new Request($data);
    }

    public function ip(){
        $data = $this->_toParamObject($this->getParams());
        $query= 'http://ip-api.com/json/'.$data['ip'].'?lang=zh-CN';
        $body = file_get_contents($query);
        $resultData = json_decode($body,true);
        var_dump($data);
        if($resultData){
            return [
                'country'=>$resultData['country'],
                'prov'=>$resultData['regionName'],
                'city'=>$resultData['city'],
                'area'=>''
            ];
        }
//        $host = "https://ipquery.market.alicloudapi.com";
//        $path = "/query";
//        $method = "GET";
//        $appcode = "3c1cebe87c2a46a28ce105e89727dce1";
//        $headers = array();
//        array_push($headers, "Authorization:APPCODE " . $appcode);
//        $querys = "ip=".$data['ip'];
//        $bodys = "";
//        $url = $host . $path . "?" . $querys;
//
//        $curl = curl_init();
//        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
//        curl_setopt($curl, CURLOPT_URL, $url);
//        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
//        curl_setopt($curl, CURLOPT_FAILONERROR, false);
//        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($curl, CURLOPT_HEADER, true);
//        if (1 == strpos("$" . $host, "https://")) {
//            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
//            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
//        }
//        $result= curl_exec($curl);
//        $body = '';
//        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) == '200') {
//            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
//            $header = substr($result, 0, $headerSize);
//            $body = substr($result, $headerSize);
//        }
//        $resultData = json_decode($body,true);
        return $resultData['data']??[];
    }

}
