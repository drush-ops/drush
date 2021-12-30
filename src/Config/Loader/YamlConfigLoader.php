<?php

namespace Drush\Config\Loader;

use Symfony\Component\Yaml\Yaml;
use Consolidation\Config\Loader\ConfigLoader;

/**
 * Load configuration files, and fill in any property values that
 * need to be expanded.
 */
class YamlConfigLoader extends ConfigLoader
{
    public function load($path): self
    {
        $this->setSourceName($path);

        // We silently skip any nonexistent config files, so that
        // clients may simply `load` all of their candidates.
        if (!file_exists($path)) {
            $this->config = [];
            return $this;
        }
        $this->config = (array) Yaml::parse(file_get_contents($path));
        return $this;
    }
}
