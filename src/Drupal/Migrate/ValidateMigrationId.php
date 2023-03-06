<?php

namespace Drush\Drupal\Migrate;

use Attribute;
use Drush\Attributes\NoArgumentsBase;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidateMigrationId extends NoArgumentsBase
{
    protected const NAME = 'validate-migration-id';
}
