<?php

declare(strict_types=1);

namespace Drush\Config;

trait ConfigAwareTrait
{
    use \Consolidation\Config\ConfigAwareTrait {
        \Consolidation\Config\ConfigAwareTrait::getConfig as parentGetConfig;
    }

    /**
     * Replaces same method in ConfigAwareTrait in order to provide a
     * DrushConfig as return type. Helps with IDE completion.
     *
     * @see https://stackoverflow.com/a/37687295.
     */
    public function getConfig(): DrushConfig
    {
        $return = $this->parentGetConfig();
        assert($return instanceof DrushConfig, 'Expected DrushConfig, got ' . $return::class . '.');
        return $return;
    }
}
