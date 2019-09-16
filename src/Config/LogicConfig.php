<?php

/**
 * @Author: jingzhou
 * @Date:   2019-09-09 12:57:45
 * @Last Modified by:   IT Work
 * @Last Modified time: 2019-09-15 19:32:54
 */
namespace AutomaticGeneration\Config;
use EasySwoole\Spl\SplBean;
class LogicConfig extends SplBean
{
	protected $logicName;//名称
    protected $baseDirectory;//生成的目录
    protected $baseNamespace;//生成的命名空间
    protected $ignoreString=[
        'list',
        'log'
    ];
    protected $isConfirmWrite=true;

    /**
     * @return mixed
     */
    public function getLogicName()
    {
        return $this->logicName;
    }

    /**
     * @param mixed $LogicName
     */
    public function setLogicName($logicName): void
    {
        $this->logicName = $logicName;
    }
    /**
     * @return mixed
     */
    public function getExtendClass()
    {
        return $this->extendClass;
    }

    /**
     * @param mixed $extendClass
     */
    public function setExtendClass($extendClass)
    {
        $this->extendClass = $extendClass;
    }
    /**
     * @return mixed
     */
    public function getBaseDirectory()
    {
        return $this->baseDirectory;
    }

    /**
     * @param mixed $baseDirectory
     */
    public function setBaseDirectory($baseDirectory)
    {
        $this->baseDirectory = $baseDirectory;
    }

    /**
     * @return mixed
     */
    public function getBaseNamespace()
    {
        return $this->baseNamespace;
    }

    /**
     * @param mixed $baseNamespace
     */
    public function setBaseNamespace($baseNamespace)
    {
        $this->baseNamespace = $baseNamespace;
        //设置下基础目录
        $pathArr = explode('\\',$baseNamespace);
        $app = array_shift($pathArr);
        if ($app=='App'){
            $this->setBaseDirectory(EASYSWOOLE_ROOT . '/' .\AutomaticGeneration\AppLogic::getAppPath() . implode('/',$pathArr));
        }
    }

    /**
     * @return bool
     */
    public function isConfirmWrite(): bool
    {
        return $this->isConfirmWrite;
    }

    /**
     * @param bool $isConfirmWrite
     */
    public function setIsConfirmWrite(bool $isConfirmWrite): void
    {
        $this->isConfirmWrite = $isConfirmWrite;
    }



}