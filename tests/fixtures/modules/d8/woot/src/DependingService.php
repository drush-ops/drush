<?php

namespace Drupal\woot;

use Drupal\drush_empty_module\MuchServiceManyWow;

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
     * @var \Drupal\drush_empty_module\MuchServiceManyWow
     */
    protected $muchServiceManyWow;

    public function __construct(MuchServiceManyWow $muchServiceManyWow)
    {
        $this->muchServiceManyWow = $muchServiceManyWow;
    }
}
