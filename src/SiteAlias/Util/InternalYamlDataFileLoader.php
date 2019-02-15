<?php
namespace Drush\SiteAlias\Util;

use Drush\Internal\Config\Yaml\Yaml;
use Consolidation\SiteAlias\DataFileLoaderInterface;

class InternalYamlDataFileLoader implements DataFileLoaderInterface
{
    /**
     * @inheritdoc
     */
    public function load($path)
    {
        return (array) Yaml::parse(file_get_contents($path));
    }
}
