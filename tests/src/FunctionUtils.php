<?php
namespace Drush;

trait FunctionUtils
{
    protected $sut;

    protected function callProtected($methodName, $args = [])
    {
        $r = new \ReflectionMethod($this->sut, $methodName);
        $r->setAccessible(true);
        return $r->invokeArgs($this->sut, $args);
    }
}
