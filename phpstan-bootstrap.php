<?php

use Composer\Autoload\ClassLoader;

// Deal with dynamic autoloader that we use for symfony cross version compat.
$loader = new ClassLoader();
$loader->addPsr4('Drush\\', 'src-symfony-compatibility/v6');
$loader->register();
