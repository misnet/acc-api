<?php

namespace Kuga\Module\Acc\Model;

use Kuga\Core\Base\AbstractModel;
use Kuga\Core\Base\ModelException;
use Kuga\Module\Acc\Service\Acl as AclService;

/**
 * 分配资源给角色
 *
 * @author dony
 *
 */
class RoleResModel extends AbstractModel
{

    /**
     *
     * @var integer
     */
    public $id;

    /**
     *
     * @var integer
     */
    public $rid;

    /**
     *
     * @var string
     */
    public $rescode;

    /**
     *
     * @var string
     */
    public $opcode;

    /**
     * 是否允许
     * @var integer
     */
    public $isAllow;

    private $accXmlFile;

    public function getSource()
    {
        return 't_role_res';
    }

    public function initialize()
    {
        parent::initialize();
        $this->belongsTo("rid", "RoleModel", "id", ['namespace' => 'Kuga\\Core\\Acc\\Model']);
    }

    /**
     * Independent Column Mapping.
     */
    public function columnMap()
    {
        return ['id' => 'id', 'rid' => 'rid', 'rescode' => 'rescode', 'opcode' => 'opcode', 'is_allow' => 'isAllow'];
    }

    public function beforeSave()
    {
        $acc     = new AclService();
        $isAllow = $acc->isAllowed('RES_ACC', 'OP_ASSIGN');
        if ( ! $isAllow) {
            throw new ModelException($this->_('对不起，您无权限进行此操作'));
        }

        return true;
    }

    /**
     * 根据角色ID与资源代码取得分配的对应的权限操作列表（分允许与禁止）
     * Enter description here ...
     * @param integer $roleId 角色ID
     * @param string $resourceCode 资源代码
     * @return array 例：array('allow'=>array('','OP_ADD','OP_REMOVE'),'deny'=>array('OP_CHECK'));
     */
    public function getAssignedOperators($roleId,$resourceCode){
        $roleId = intval($roleId);
        if(!$roleId){
            throw new ModelException($this->translator->_('没有指定角色，无法分配权限'));
        }
        if(!$resourceCode){
            throw new ModelException($this->translator->_('没有指定权限资源，无法分配权限'));
        }
        $rows = self::find(array(
            'conditions'=>'rid=:rid: and (rescode=:rescode:)',
            'bind'=>array('rid'=>$roleId,'rescode'=>$resourceCode),
            'order'=>'id desc'
        ));
        $allowOperators = array();
        $denyOperators  = array();
        if($rows){
            foreach($rows as $row){
                if($row->isAllow){
                    $allowOperators[] = $row->opcode;
                }else{
                    $denyOperators[]  = $row->opcode;
                }
            }
        }
        return array('allow'=>$allowOperators,'deny'=>$denyOperators);
    }

    /**
     * 注入权限配置xml文件
     * @param $f
     */
    public function setResourceConfigFile($f){
        $this->accXmlFile = $f;
    }
    /**
     * 取得权限资源组列表
     * 需先调用setResourceConfigFile
     */
    public function getResourceGroup()
    {
        $cache             = $this->getDI()->get('cache');
        $cacheKey          = 'acc_setting';
        $callback['func']  = [$this, 'parsePrivilegeSetting'];
        $callback['param'] = [];
        $resourceList      = $cache->get($cacheKey, $callback);

        return $resourceList;
    }

    /**
     * 分析acc.xml文件，读取权限资源操作配置
     *
     * @return array
     */
    public function parsePrivilegeSetting()
    {
        //$file = QING_ROOT_PATH.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'acc.xml';
        $file = $this->accXmlFile;

        $resourceList = [];
        if ($file && is_readable($file)) {
            //simplexml_load_file失效了，不明
            $dom = simplexml_load_string(file_get_contents($file));
            if ($dom instanceof \SimpleXMLElement) {
                if (sizeof($dom->children()) > 0) {
                    $i = 0;
                    foreach ($dom->children() as $key => $node) {
                        $nodeName = (string)$key;
                        $nodeName = strtolower($nodeName);
                        if ($nodeName == 'resource') {
                            $code = (string)$node['code'];
                            $code = trim($code);
                            if ($code != '' && ! array_key_exists($code, $resourceList)) {
                                $resourceList[$code] = ['code'      => $code, 'text' => (string)$node['title'], 'op' => $this->_parseOpNode($node),
                                                        'model'     => strval($node['model']), 'idField' => strval($node['idField']),
                                                        'nameField' => strval($node['nameField'])];
                            }
                        }
                    }
                }
            }
        }

        return $resourceList;
    }

    /**
     * 解析acc.xml的op项
     * @param SimpleXMLElement $dom
     * @return array
     */
    private function _parseOpNode($dom)
    {
        $op = [];
        if ($dom instanceof \SimpleXMLElement) {
            if (sizeof($dom->children()) > 0) {
                $i = 0;
                foreach ($dom->children() as $key => $node) {
                    $nodeName = (string)$key;
                    $nodeName = strtolower($nodeName);
                    if ($nodeName == 'op') {
                        $code = (string)$node['code'];
                        if ($code != '') {
                            $op[$i]['code'] = $code;
                            $op[$i]['text'] = (string)$node['title'];
                            $i++;
                        }
                    }
                }
            }
        }

        return $op;
    }

    /**
     * 根据资源代码取得资源数组信息
     *
     * @param string $code
     *
     * @return array
     */
    public function getResource($code)
    {
        $resourceList = $this->getResourceGroup();
        if (is_array($resourceList) && sizeof($resourceList) > 0) {
            if (array_key_exists($code, $resourceList)) {
                return $resourceList[$code];
            } else {
                return [];
            }
        } else {
            return [];
        }
    }
}
