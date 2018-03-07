<?php

namespace Drupal\woot;

use Consolidation\AnnotatedCommand\CommandInfoAltererInterface;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Consolidation\AnnotatedCommand\Parser\DefaultsWithDescriptions;

class WootCommandInfoAlterer implements CommandInfoAltererInterface
{
    public function alterCommandInfo(CommandInfo $commandInfo, $commandFileInstance)
    {
        if ($commandInfo->getName() === 'woot:altered') {
            $commandInfo->setAliases('woot-new-alias');
        }
    }
}
