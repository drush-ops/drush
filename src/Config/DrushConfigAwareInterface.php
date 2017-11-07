<?php
namespace Drush\Config;

use Robo\Contract\ConfigAwareInterface;

interface DrushConfigAwareInterface extends ConfigAwareInterface
{
    /**
     * Get a config reference. Type hinted to DrushConfig for completion's benefit.
     *
     * @return \Drush\Config\DrushConfig
     */
    public function getConfig();
}
