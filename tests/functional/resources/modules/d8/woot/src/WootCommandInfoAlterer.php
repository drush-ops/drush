<?php

namespace Drupal\woot;

use Consolidation\AnnotatedCommand\CommandInfoAltererInterface;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

class WootCommandInfoAlterer implements CommandInfoAltererInterface
{
    /**
     * @var \Drupal\Core\Logger\LoggerChannelInterface
     */
    protected $logger;

    public function __construct(LoggerChannelFactoryInterface $loggerFactory)
    {
        $this->logger = $loggerFactory->get('drush');
    }

    public function alterCommandInfo(CommandInfo $commandInfo, $commandFileInstance)
    {
        if ($commandInfo->getName() === 'woot:altered') {
            $commandInfo->setAliases('woot-new-alias');
            $this->logger->debug(dt("Module 'woot' changed the alias of 'woot:altered' command into 'woot-new-alias' in " . __METHOD__ . '().'));
        }
    }
}
