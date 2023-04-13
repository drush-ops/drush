<?php

declare(strict_types=1);

namespace Drush\SiteAlias\Util;

use Symfony\Component\Yaml\Yaml;
use Consolidation\SiteAlias\DataFileLoaderInterface;

class InternalYamlDataFileLoader implements DataFileLoaderInterface
{
    public function load($path): array
    {
        return (array) Yaml::parse(file_get_contents($path));
    }
}
