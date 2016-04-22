<?php
namespace Consolidation\AnnotatedCommand;

class AnnotatedCommandFactory
{
    protected $listeners = [];

    public function __construct()
    {
    }

    public function addListener($listener)
    {
        $this->listeners[] = $listener;
    }

    protected function notify($commandFileInstance)
    {
        foreach ($this->listeners as $listener) {
            if ($listener instanceof CommandCreationListenerInterface) {
                $listener->notifyCommandFileAdded($commandFileInstance);
            }
            if (is_callable($listener)) {
                $listener($commandFileInstance);
            }
        }
    }

    public function createCommandsFromClass($commandFileInstance)
    {
        $this->notify($commandFileInstance);
        $commandInfoList = $this->getCommandInfoListFromClass($commandFileInstance);
        return $this->createCommandsFromClassInfo($commandInfoList, $commandFileInstance);
    }

    public function getCommandInfoListFromClass($classNameOrInstance)
    {
        $commandInfoList = [];

        // Ignore special functions, such as __construct and __call, and
        // accessor methods such as getFoo and setFoo, while allowing
        // set or setup.
        $commandMethodNames = array_filter(
            get_class_methods($classNameOrInstance) ?: [],
            function ($m) {
                return !preg_match('#^(_|get[A-Z]|set[A-Z])#', $m);
            }
        );

        foreach ($commandMethodNames as $commandMethodName) {
            $commandInfoList[] = new CommandInfo($classNameOrInstance, $commandMethodName);
        }

        return $commandInfoList;
    }

    public function createCommandInfo($classNameOrInstance, $commandMethodName)
    {
        return new CommandInfo($classNameOrInstance, $commandMethodName);
    }

}
