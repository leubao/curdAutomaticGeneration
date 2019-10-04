<?php

/**
 * 逻辑层生成
 * @Author: jingzhou
 * @Date:   2019-09-09 14:59:23
 * @Last Modified by:   IT Work
 * @Last Modified time: 2019-09-17 12:54:43
 */
namespace AutomaticGeneration;

use AutomaticGeneration\Config\LogicConfig;
use EasySwoole\Utility\File;
use EasySwoole\Utility\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

/**
 * easyswoole model快速构建器
 * Class BeanBuilder
 * @package AutomaticGeneration
 */
class LogicBuilder
{
    /**
     * @var $config ModelConfig
     */
    protected $config;
    protected $className;

    /**
     * BeanBuilder constructor.
     * @param  $config
     * @throws \Exception
     */
    public function __construct(LogicConfig $config)
    {
        $this->config = $config;
        $this->createBaseDirectory($config->getBaseDirectory());
        //$LogicName = $this->config->getLogicName();
        //$this->className = $this->config->getBaseNamespace() . '\\' . $LogicName;
    }

    public function getLogicName()
    {
        
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
    public function generateLogic()
    {
        $phpNamespace = new PhpNamespace($this->config->getBaseNamespace());
        $phpClass = $this->addClassBaseContent($phpNamespace, $this->config->getLogicName());
        $fileName = $this->config->getBaseDirectory().'/'.ucfirst(Str::camel($this->config->getLogicName()));
        return $this->createPHPDocument($fileName, $phpNamespace);
    }


    /**
     * 新增基础类内容
     * addClassBaseContent
     * @param $logicName
     * @param $phpNamespace
     * @return ClassType
     * @author it
     * Time: 21:38
     */
    protected function addClassBaseContent($phpNamespace, $logicName): ClassType
    {
        $phpClass = $phpNamespace->addClass($logicName);
        //配置类基本信息
        if ($this->config->getExtendClass()) {
            $phpClass->addExtend($this->config->getExtendClass());
        }
        $phpClass->addComment("{$logicName}");
        $phpClass->addComment("Class {$logicName}");
        $phpClass->addComment('Create With Automatic Generator');
        return $phpClass;
    }

    /**
     * createPHPDocument
     * @param $fileName
     * @param $fileContent
     * @return bool|int
     * @author it
     * Time: 19:49
     */
    protected function createPHPDocument($fileName, $fileContent)
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

    /**
     * @return mixed
     */
    public function getClassName()
    {
        return $this->className;
    }

}