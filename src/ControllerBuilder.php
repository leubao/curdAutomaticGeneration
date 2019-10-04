<?php
/**
 * Created by PhpStorm.
 * User: tioncico
 * Date: 19-5-2
 * Time: 上午10:38
 */

namespace AutomaticGeneration;

use AutomaticGeneration\Config\ControllerConfig;
use EasySwoole\Http\Message\Status;
use App\Utils\Code;
use EasySwoole\MysqliPool\Mysql;
use EasySwoole\Utility\File;
use EasySwoole\Utility\Str;
use EasySwoole\Validate\Validate;
use http\Message\Body;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

/**
 * easyswoole 控制器快速构建器
 * Class ControllerBuilder
 * @package AutomaticGeneration
 */
class ControllerBuilder
{
    /**
     * @var $config BeanConfig;
     */
    protected $config;
    protected $validateList = [];

    /**
     * BeanBuilder constructor.
     * @param        $config
     * @throws \Exception
     */
    public function __construct(ControllerConfig $config)
    {
        $this->config = $config;
        $this->createBaseDirectory($config->getBaseDirectory());
    }

    /**
     * createBaseDirectory
     * @param $baseDirectory
     * @throws \Exception
     * @author Tioncico
     * Time: 19:49
     */
    protected function createBaseDirectory($baseDirectory)
    {
        File::createDirectory($baseDirectory);
    }

    /**
     * generateBean
     * @return bool|int
     * @author Tioncico
     * Time: 19:49
     */
    public function generateController()
    {
        $realTableName = $this->setRealTableName();
        $phpNamespace = new PhpNamespace($this->config->getBaseNamespace());
        //$phpNamespace->addUse($this->config->getMysqlPoolClass());
        //zj 模型和bean 在控制创建中非必须
        if($this->config->getModelClass()){
            $phpNamespace->addUse($this->config->getModelClass());
        }
        if($this->config->getBeanClass()){
            $phpNamespace->addUse($this->config->getBeanClass());
        }
        $phpNamespace->addUse(Code::class);
        //$phpNamespace->addUse(Validate::class);
        $phpNamespace->addUse(Mysql::class);
        $phpNamespace->addUse($this->config->getExtendClass());
        $phpClass = $phpNamespace->addClass($realTableName);
        //增加所属模块继承的改变
        
        $phpClass->addExtend($this->config->getExtendClass());
        $phpClass->addComment("{$this->config->getTableComment()}");
        $phpClass->addComment("Class {$realTableName}");
        $phpClass->addComment('Create With Automatic Generator');
        $this->addAddDataMethod($phpClass);
        $this->addUpdateDataMethod($phpClass);
        $this->addGetOneDataMethod($phpClass);
        $this->addGetAllDataMethod($phpClass);
        $this->addDeleteDataMethod($phpClass);

        //zj 验证器独立不在controller展示
        //$this->addValidateMethod($phpClass);

        return $this->createPHPDocument($this->config->getBaseDirectory() . '/' . $realTableName, $phpNamespace, $this->config->getTableColumns());
    }

    function addValidateMethod(ClassType $phpClass)
    {
        $method = $phpClass->addMethod('getValidateRule');
        $method->addParameter("action")->setTypeHint('string')->setNullable();
        $method->setReturnType(Validate::class)->setReturnNullable();
        $methodBody = <<<Body
\$validate = null;
switch (\$action) {        
{$this->validateGenerationStr()}
}
return \$validate;
Body;
        $method->setBody($methodBody);
        $method->addComment("@author: AutomaticGeneration < 1067197739@qq.com >");
    }

    function validateGenerationStr()
    {
        $addColumnStr = '';
        $updateColumnStr = '';
        $getOneColumnStr = '';
        $getAllColumnStr = '';
        $deleteColumnStr = '';
        $updateColumnStr .= "        \$validate->addColumn('{$this->config->getPrimaryKey()}', 'id')->required();\n";
        $deleteColumnStr .= "        \$validate->addColumn('{$this->config->getPrimaryKey()}', 'id')->required();\n";
        $getOneColumnStr .= "        \$validate->addColumn('{$this->config->getPrimaryKey()}', 'id')->required();\n";
        $getAllColumnStr .= "        \$validate->addColumn('page', '页数')->optional();
        \$validate->addColumn('limit', 'limit')->optional();
        \$validate->addColumn('keyword', '关键词')->optional();";
        foreach ($this->config->getTableColumns() as $column) {
            if ($column['Key'] != 'PRI') {
                $addColumnStr .= "        \$validate->addColumn('{$column['Field']}', '{$column['Comment']}')";
                $updateColumnStr .= "        \$validate->addColumn('{$column['Field']}', '{$column['Comment']}')->optional();\n";
                if ($column['Null'] == 'NO') {
                    $addColumnStr .= "->required()";
                } else {
                    $addColumnStr .= "->optional()";
                }
                $addColumnStr .= ";\n";
            }
        }
        $body = '';
        $body .= <<<BODY
    case 'add':
        \$validate = new Validate();       
$addColumnStr
        break;
    case 'update':
        \$validate = new Validate();       
$updateColumnStr
        break;
    case 'getAll':
        \$validate = new Validate();       
$getAllColumnStr
        break;
    case 'getOne':
        \$validate = new Validate();       
$getOneColumnStr
        break;
    case 'delete':
        \$validate = new Validate();       
$deleteColumnStr
        break;
BODY;
       return $body;
    }


    function addAddDataMethod(ClassType $phpClass)
    {
        $addData = [];
        $method = $phpClass->addMethod('add');
        $apiUrl = str_replace(['App\\HttpController', '\\'], ['', '/'], $this->config->getBaseNamespace());
//        var_dump($this->config->getBaseNamespace(),$apiUrl);die;
        //配置基础注释
        $method->addComment("@api {get|post} {$apiUrl}/{$this->setRealTableName()}/add");
        $method->addComment("@apiName add");
        $method->addComment("@apiGroup {$apiUrl}/{$this->setRealTableName()}");
        $method->addComment("@apiPermission {$this->config->getAuthName()}");
        $method->addComment("@apiDescription add新增数据");
        $this->config->getAuthSessionName() && ($method->addComment("@apiParam {String}  {$this->config->getAuthSessionName()} 权限验证token"));
        $mysqlPoolNameArr = (explode('\\', $this->config->getMysqlPoolClass()));
        $mysqlPoolName = end($mysqlPoolNameArr);
        $modelNameArr = (explode('\\', $this->config->getModelClass()));
        $modelName = end($modelNameArr);
        $beanNameArr = (explode('\\', $this->config->getBeanClass()));
        $beanName = end($beanNameArr);
        if (empty($this->config->getMysqlPoolName())) {
            $methodBody = "\$db = {$mysqlPoolName}::defer();\n";
        } else {
            $methodBody = "\$db = {$mysqlPoolName}::defer('{$this->config->getMysqlPoolName()}');\n";
        }
        $methodBody .= <<<Body
\$param = \$this->request()->getRequestParam();

Body;
        if($this->config->isValidator()){
            $methodBody .= <<<Body
if (\$this->validator( \$param, '{$this->config->getValidate()}.add' ) !== true ) {
    \$this->writeJson(Code::error, [], \$this->getValidator()->getError() );
}

Body;
        }
        $methodBody .= <<<Body
\$model = new {$modelName}(\$db);
\$bean = new {$beanName}();

Body;
        foreach ($this->config->getTableColumns() as $column) {
            if ($column['Key'] != 'PRI') {
                $addData[] = $column['Field'];
                $columnType = $this->convertDbTypeToDocType($column['Type']);
                $setMethodName = "set" . Str::studly($column['Field']);
                if ($column['Null'] == 'NO') {
                    $method->addComment("@apiParam {{$columnType}} {$column['Field']} {$column['Comment']}");
                    $methodBody .= "\$bean->$setMethodName(\$param['{$column['Field']}']);\n";
                } else {
                    $method->addComment("@apiParam {{$columnType}} [{$column['Field']}] {$column['Comment']}");
                    $methodBody .= "\$bean->$setMethodName(\$param['{$column['Field']}']??'');\n";
                }
            } else {
                $this->config->setPrimaryKey($column['Field']);
            }
        }
        $setPrimaryKeyMethodName = "set" . Str::studly($this->config->getPrimaryKey());
        $methodBody .= <<<Body
\$rs = \$model->add(\$bean);
if (\$rs) {
    \$bean->$setPrimaryKeyMethodName(\$db->getInsertId());
    \$this->writeJson(Code::CODE_OK, \$bean->toArray(), "success");
} else {
    \$this->writeJson(Code::CODE_BAD_REQUEST, [], \$db->getLastError());
}
Body;
        $method->setBody($methodBody);
        $method->addComment("@apiSuccess {Number} code");
        $method->addComment("@apiSuccess {Object[]} data");
        $method->addComment("@apiSuccess {String} msg");
        $method->addComment("@apiSuccessExample {json} Success-Response:");
        $method->addComment("HTTP/1.1 200 OK");
        $method->addComment("{\"code\":200,\"data\":{},\"msg\":\"success\"}");
        $method->addComment("@author: AutomaticGeneration < 1067197739@qq.com >");
    }

    function addUpdateDataMethod(ClassType $phpClass)
    {
        $addData = [];
        $method = $phpClass->addMethod('update');
        $apiUrl = str_replace(['App\\HttpController', '\\'], ['', '/'], $this->config->getBaseNamespace());
        //配置基础注释
        $method->addComment("@api {get|post} {$apiUrl}/{$this->setRealTableName()}/update");
        $method->addComment("@apiName update");
        $method->addComment("@apiGroup {$apiUrl}/{$this->setRealTableName()}");
        $method->addComment("@apiPermission {$this->config->getAuthName()}");
        $method->addComment("@apiDescription update修改数据");
        $this->config->getAuthSessionName() && ($method->addComment("@apiParam {String}  {$this->config->getAuthSessionName()} 权限验证token"));
        $method->addComment("@apiParam {int} {$this->config->getPrimaryKey()} 主键id");
        $mysqlPoolNameArr = (explode('\\', $this->config->getMysqlPoolClass()));
        $mysqlPoolName = end($mysqlPoolNameArr);
        $modelNameArr = (explode('\\', $this->config->getModelClass()));
        $modelName = end($modelNameArr);
        $beanNameArr = (explode('\\', $this->config->getBeanClass()));
        $beanName = end($beanNameArr);
        if (empty($this->config->getMysqlPoolName())) {
            $methodBody = "\$db = {$mysqlPoolName}::defer();\n";
        } else {
            $methodBody = "\$db = {$mysqlPoolName}::defer('{$this->config->getMysqlPoolName()}');\n";
        }
        $methodBody .= <<<Body
\$param = \$this->request()->getRequestParam();

Body;
        if($this->config->isValidator()){
            $methodBody .= <<<Body
if (\$this->validator( \$param, '{$this->config->getValidate()}.update' ) !== true ) {
    \$this->writeJson(Code::error, [], \$this->getValidator()->getError() );
}

Body;
        }

        $methodBody .= <<<Body
\$model = new {$modelName}(\$db);
\$bean = \$model->getOne(new {$beanName}(['{$this->config->getPrimaryKey()}' => \$param['{$this->config->getPrimaryKey()}']]));
if (empty(\$bean)) {
    \$this->writeJson(Code::CODE_BAD_REQUEST, [], '该数据不存在');
    return false;
}
\$updateBean = new {$beanName}();
\n
Body;
        foreach ($this->config->getTableColumns() as $column) {
            if ($column['Key'] != 'PRI') {
                $addData[] = $column['Field'];
                $columnType = $this->convertDbTypeToDocType($column['Type']);
                $setMethodName = "set" . Str::studly($column['Field']);
                $getMethodName = "get" . Str::studly($column['Field']);
                $method->addComment("@apiParam {{$columnType}} [{$column['Field']}] {$column['Comment']}");
                $methodBody .= "\$updateBean->$setMethodName(\$param['{$column['Field']}']??\$bean->$getMethodName());\n";
            } else {
                $this->config->setPrimaryKey($column['Field']);
            }
        }
        $setPrimaryKeyMethodName = "set" . Str::studly($this->config->getPrimaryKey());
        $methodBody .= <<<Body
\$rs = \$model->update(\$bean, \$updateBean->toArray([], \$updateBean::FILTER_NOT_NULL));
if (\$rs) {
    \$this->writeJson(Code::CODE_OK, \$rs, "success");
} else {
    \$this->writeJson(Code::CODE_BAD_REQUEST, [], \$db->getLastError());
}
Body;
        $method->setBody($methodBody);
        $method->addComment("@apiSuccess {Number} code");
        $method->addComment("@apiSuccess {Object[]} data");
        $method->addComment("@apiSuccess {String} msg");
        $method->addComment("@apiSuccessExample {json} Success-Response:");
        $method->addComment("HTTP/1.1 200 OK");
        $method->addComment("{\"code\":200,\"data\":{},\"msg\":\"success\"}");
        $method->addComment("@author: AutomaticGeneration < 1067197739@qq.com >");
    }

    function addGetOneDataMethod(ClassType $phpClass)
    {
        $method = $phpClass->addMethod('getOne');
        $apiUrl = str_replace(['App\\HttpController', '\\'], ['', '/'], $this->config->getBaseNamespace());
        //配置基础注释
        $method->addComment("@api {get|post} {$apiUrl}/{$this->setRealTableName()}/getOne");
        $method->addComment("@apiName getOne");
        $method->addComment("@apiGroup {$apiUrl}/{$this->setRealTableName()}");
        $method->addComment("@apiPermission {$this->config->getAuthName()}");
        $method->addComment("@apiDescription 根据主键获取一条信息");
        $this->config->getAuthSessionName() && ($method->addComment("@apiParam {String}  {$this->config->getAuthSessionName()} 权限验证token"));
        $method->addComment("@apiParam {int} {$this->config->getPrimaryKey()} 主键id");
        $mysqlPoolNameArr = (explode('\\', $this->config->getMysqlPoolClass()));
        $mysqlPoolName = end($mysqlPoolNameArr);
        $modelNameArr = (explode('\\', $this->config->getModelClass()));
        $modelName = end($modelNameArr);
        $beanNameArr = (explode('\\', $this->config->getBeanClass()));
        $beanName = end($beanNameArr);
        if (empty($this->config->getMysqlPoolName())) {
            $methodBody = "\$db = {$mysqlPoolName}::defer();\n";
        } else {
            $methodBody = "\$db = {$mysqlPoolName}::defer('{$this->config->getMysqlPoolName()}');\n";
        }
        $methodBody .= <<<Body
\$param = \$this->request()->getRequestParam();

Body;
var_dump($this->config->getValidate());
        if($this->config->isValidator()){
            $methodBody .= <<<Body
if (\$this->validator( \$param, '{$this->config->getValidate()}.add' ) !== true ) {
    \$this->writeJson(Code::error, [], \$this->getValidator()->getError() );
}

Body;
        }
        $methodBody .= <<<Body
\$model = new {$modelName}(\$db);
\$bean = \$model->getOne(new {$beanName}(['{$this->config->getPrimaryKey()}' => \$param['{$this->config->getPrimaryKey()}']]));
if (\$bean) {
    \$this->writeJson(Code::CODE_OK, \$bean, "success");
} else {
    \$this->writeJson(Code::CODE_BAD_REQUEST, [], 'fail');
}
Body;
        $method->setBody($methodBody);
        $method->addComment("@apiSuccess {Number} code");
        $method->addComment("@apiSuccess {Object[]} data");
        $method->addComment("@apiSuccess {String} msg");
        $method->addComment("@apiSuccessExample {json} Success-Response:");
        $method->addComment("HTTP/1.1 200 OK");
        $method->addComment("{\"code\":200,\"data\":{},\"msg\":\"success\"}");
        $method->addComment("@author: AutomaticGeneration < 1067197739@qq.com >");
    }

    function addDeleteDataMethod(ClassType $phpClass)
    {
        $method = $phpClass->addMethod('delete');
        $apiUrl = str_replace(['App\\HttpController', '\\'], ['', '/'], $this->config->getBaseNamespace());
        //配置基础注释
        $method->addComment("@api {get|post} {$apiUrl}/{$this->setRealTableName()}/delete");
        $method->addComment("@apiName delete");
        $method->addComment("@apiGroup {$apiUrl}/{$this->setRealTableName()}");
        $method->addComment("@apiPermission {$this->config->getAuthName()}");
        $method->addComment("@apiDescription 根据主键删除一条信息");
        $this->config->getAuthSessionName() && ($method->addComment("@apiParam {String}  {$this->config->getAuthSessionName()} 权限验证token"));
        $method->addComment("@apiParam {int} {$this->config->getPrimaryKey()} 主键id");
        $mysqlPoolNameArr = (explode('\\', $this->config->getMysqlPoolClass()));
        $mysqlPoolName = end($mysqlPoolNameArr);
        $modelNameArr = (explode('\\', $this->config->getModelClass()));
        $modelName = end($modelNameArr);
        $beanNameArr = (explode('\\', $this->config->getBeanClass()));
        $beanName = end($beanNameArr);
        if (empty($this->config->getMysqlPoolName())) {
            $methodBody = "\$db = {$mysqlPoolName}::defer();\n";
        } else {
            $methodBody = "\$db = {$mysqlPoolName}::defer('{$this->config->getMysqlPoolName()}');\n";
        }
        $methodBody .= <<<Body
\$param = \$this->request()->getRequestParam();

Body;
        if($this->config->isValidator()){
            $methodBody .= <<<Body
if (\$this->validator( \$param, '{$this->config->getValidate()}.add' ) !== true ) {
    \$this->writeJson(Code::error, [], \$this->getValidator()->getError() );
}

Body;
        }
        $methodBody .= <<<Body
\$model = new {$modelName}(\$db);

\$rs = \$model->delete(new $beanName(['{$this->config->getPrimaryKey()}' => \$param['{$this->config->getPrimaryKey()}']]));
if (\$rs) {
    \$this->writeJson(Code::CODE_OK, [], "success");
} else {
    \$this->writeJson(Code::CODE_BAD_REQUEST, [], 'fail');
}
Body;
        $method->setBody($methodBody);
        $method->addComment("@apiSuccess {Number} code");
        $method->addComment("@apiSuccess {Object[]} data");
        $method->addComment("@apiSuccess {String} msg");
        $method->addComment("@apiSuccessExample {json} Success-Response:");
        $method->addComment("HTTP/1.1 200 OK");
        $method->addComment("{\"code\":200,\"data\":{},\"msg\":\"success\"}");
        $method->addComment("@author: AutomaticGeneration < 1067197739@qq.com >");
    }

    function addGetAllDataMethod(ClassType $phpClass)
    {
        $method = $phpClass->addMethod('getAll');
        $apiUrl = str_replace(['App\\HttpController', '\\'], ['', '/'], $this->config->getBaseNamespace());
        //配置基础注释
        $method->addComment("@api {get|post} {$apiUrl}/{$this->setRealTableName()}/getAll");
        $method->addComment("@apiName getAll");
        $method->addComment("@apiGroup {$apiUrl}/{$this->setRealTableName()}");
        $method->addComment("@apiPermission {$this->config->getAuthName()}");
        $method->addComment("@apiDescription 获取一个列表");
        $this->config->getAuthSessionName() && ($method->addComment("@apiParam {String}  {$this->config->getAuthSessionName()} 权限验证token"));
        $method->addComment("@apiParam {String} [page=1]");
        $method->addComment("@apiParam {String} [limit=20]");
        $method->addComment("@apiParam {String} [keyword] 关键字,根据表的不同而不同");
        $mysqlPoolNameArr = (explode('\\', $this->config->getMysqlPoolClass()));
        $mysqlPoolName = end($mysqlPoolNameArr);
        $modelNameArr = (explode('\\', $this->config->getModelClass()));
        $modelName = end($modelNameArr);
        $beanNameArr = (explode('\\', $this->config->getBeanClass()));
        $beanName = end($beanNameArr);
        if (empty($this->config->getMysqlPoolName())) {
            $methodBody = "\$db = {$mysqlPoolName}::defer();\n";
        } else {
            $methodBody = "\$db = {$mysqlPoolName}::defer('{$this->config->getMysqlPoolName()}');\n";
        }
        $methodBody .= <<<Body
\$param = \$this->request()->getRequestParam();
\$page = (int)(\$param['page']??1);
\$limit = (int)(\$param['limit']??20);
\$model = new {$modelName}(\$db);
\$data = \$model->getAll(\$page, \$param['keyword']??null, \$limit);
\$this->writeJson(Code::CODE_OK, \$data, 'success');
Body;
        $method->setBody($methodBody);
        $method->addComment("@apiSuccess {Number} code");
        $method->addComment("@apiSuccess {Object[]} data");
        $method->addComment("@apiSuccess {String} msg");
        $method->addComment("@apiSuccessExample {json} Success-Response:");
        $method->addComment("HTTP/1.1 200 OK");
        $method->addComment("{\"code\":200,\"data\":{},\"msg\":\"success\"}");
        $method->addComment("@author: AutomaticGeneration < 1067197739@qq.com >");
    }


    /**
     * 处理表真实名称
     * setRealTableName
     * @return bool|mixed|string
     * @author tioncico
     * Time: 下午11:55
     */
    function setRealTableName()
    {
        if ($this->config->getRealTableName()) {
            return $this->config->getRealTableName();
        }
        //先去除前缀
        $tableName = substr($this->config->getTableName(), strlen($this->config->getTablePre()));
        //去除后缀
        foreach ($this->config->getIgnoreString() as $string) {
            $tableName = rtrim($tableName, $string);
        }
        //下划线转驼峰,并且首字母大写
        $tableName = ucfirst(Str::camel($tableName));
        $this->config->setRealTableName($tableName);
        return $tableName;
    }
    function getColumnDeaflutValue($column)
    {

    }

    /**
     * convertDbTypeToDocType
     * @param $fieldType
     * @return string
     * @author Tioncico
     * Time: 19:49
     */
    protected function convertDbTypeToDocType($fieldType)
    {
        $newFieldType = strtolower(strstr($fieldType, '(', true));
        if ($newFieldType == '') $newFieldType = strtolower($fieldType);
        if (in_array($newFieldType, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'])) {
            $newFieldType = 'int';
        } elseif (in_array($newFieldType, ['float', 'double', 'real', 'decimal', 'numeric'])) {
            $newFieldType = 'float';
        } elseif (in_array($newFieldType, ['char', 'varchar', 'text'])) {
            $newFieldType = 'string';
        } else {
            $newFieldType = 'mixed';
        }
        return $newFieldType;
    }

    /**
     * createPHPDocument
     * @param $fileName
     * @param $fileContent
     * @param $tableColumns
     * @return bool|int
     * @author Tioncico
     * Time: 19:49
     */
    protected function createPHPDocument($fileName, $fileContent, $tableColumns)
    {
        // if ($this->config->isConfirmWrite()) {
        //     if (file_exists($fileName . '.php')) {
        //         echo "(Controller)当前路径已经存在文件,是否覆盖?(y/n)\n";
        //         if (trim(fgets(STDIN)) == 'n') {
        //             echo "已结束运行\n";
        //             return false;
        //         }
        //     }
        // }
        
        if (file_exists($fileName . '.php')) {
            if($this->config->isConfirmWrite()){
                //开启覆盖
                $content = "<?php\n\n{$fileContent}\n";
                $result = file_put_contents($fileName . '.php', $content);
            }else{
                $result = false;
            }
        }else{
            $content = "<?php\n\n{$fileContent}\n";
            $result = file_put_contents($fileName . '.php', $content); 
        }
        
        
        return $result == false ? $fileName . '.php'.'已存在' : $fileName . '.php';
    }


}
