<?php

namespace Drupal\woot;

use Drupal\devel\DevelDumperManagerInterface;

/**
 * Test service that depends on another service.
 *
 * This is used to test whether it is possible to perform database updates if a
 * new service is introduced in an existing module that depends on another
 * service that is part of a new module that is being added in the update.
 *
 * Note that the service definition is missing from `woot.services.yml`. This is
 * intentional, it is added as part of the update test.
 *
 * @see \Unish\UpdateDBTest::testUpdateModuleWithServiceDependency()
 *
 * @see https://www.drupal.org/project/drupal/issues/2863986
 * @see https://github.com/drush-ops/drush/issues/3193
 */
class DependingService
{

    /**
     * @var \Drupal\devel\DevelDumperManagerInterface
     */
    protected $develDumperManager;

    public function __construct(DevelDumperManagerInterface $develDumperManager)
    {
        $this->develDumperManager = $develDumperManager;
    }
}
