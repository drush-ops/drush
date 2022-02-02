<?php

namespace Drush\SiteAlias\Util;

use Symfony\Component\Yaml\Yaml;
use Consolidation\SiteAlias\DataFileLoaderInterface;

class InternalYamlDataFileLoader implements DataFileLoaderInterface
{
    /**
     * @inheritdoc
     */
    public function load($path): array
    {
        return (array) Yaml::parse(file_get_contents($path));
    }
}
