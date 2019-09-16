<?php

/**
 * @Author: jingzhou
 * @Date:   2019-09-09 00:40:53
 * @Last Modified by:   IT Work
 * @Last Modified time: 2019-09-16 00:42:12
 */

namespace AutomaticGeneration;

use AutomaticGeneration\Config\ValidatorConfig;
use EasySwoole\Http\Message\Status;
use EasySwoole\MysqliPool\Mysql;
use EasySwoole\Utility\File;
use EasySwoole\Utility\Str;
use App\Utils\Validator\Validate;
use http\Message\Body;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

/**
 * easyswoole 验证器快速构建器
 * Class ControllerBuilder
 * @package AutomaticGeneration
 */
class ValidatorBuilder
{
	/**
     * @var $config BeanConfig;
     */
    protected $config;
    /**
     * BeanBuilder constructor.
     * @param        $config
     * @throws \Exception
     */
    public function __construct(ValidatorConfig $config)
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
    public function generateValidator()
    {
        $realTableName = $this->setRealTableName();
        $phpNamespace = new PhpNamespace($this->config->getBaseNamespace());
        
        $phpNamespace->addUse(Status::class);
        //$phpNamespace->addUse(Validate::class);
        $phpNamespace->addUse(Mysql::class);


        $phpClass = $phpNamespace->addClass($realTableName);

        $validateData = $this->getValidateArr();
        $phpClass->addProperty('rule', $validateData['rule'])
            ->setVisibility('protected')
            ->addComment('@var rule[] 验证规则');

        $phpClass->addProperty('message', $validateData['message'])
            ->setVisibility('protected')
            ->addComment('@var message[] 不通过提示');

        $phpClass->addProperty('scene', $validateData['scene'])
            ->setVisibility('protected')
            ->addComment('@var scene[] 验证场景');



        $phpClass->addExtend($this->config->getExtendClass());
        $phpClass->addComment("{$this->config->getTableComment()}");
        $phpClass->addComment("Class {$realTableName}");
        $phpClass->addComment('Create With Automatic Generator');

        //$this->addValidateMethod($phpClass);
        $fileName = $this->config->getBaseDirectory().'/'.$this->config->getValidatorName();
        return $this->createPHPDocument($fileName, $phpNamespace, $this->config->getTableColumns());
    }
    public function getValidateArr()
    {
        $rule = [];
        $message = [];
        $rule = [];

        //存在时验证
        $rule['keyword'] = 'chsAlphaNum';
        $rule['page'] = 'number';
        $rule['limit'] = 'number';
        //全部规则
        foreach ($this->config->getTableColumns() as $column) {
            if ($column['Key'] == 'PRI') {
                $this->config->setPrimaryKey($column['Field']);
            } 
            //规则
            $rule[$column['Field']] = 'require';
            //提示
            $message[$column['Field'].'.require'] = $column['Comment'];
            
            $field[] = $column['Field'];
        }
        //场景
        $scene['add'] = array_merge(array_diff($field, $this->config->getPrimaryKey()));
        $scene['update'] = $field;
        $scene['getAll'] = ['page','limit','keyword'];
        $scene['getOne'] = [
            $this->config->getPrimaryKey()
        ];
        $scene['delete'] = [
            $this->config->getPrimaryKey()
        ];
        return ['rule' => $rule, 'message' => $message,   'scene' =>  $scene];
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