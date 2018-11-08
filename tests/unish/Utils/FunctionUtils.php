<?php
namespace Unish\Utils;

trait FunctionUtils
{
    protected function callProtected($object, $methodName, $args = [])
    {
        $r = new \ReflectionMethod($object, $methodName);
        $r->setAccessible(true);
        return $r->invokeArgs($object, $args);
    }
}
