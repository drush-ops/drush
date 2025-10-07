<?php

declare(strict_types=1);

namespace Drush\Drupal\Migrate;

use Attribute;
use Drush\Attributes\NoArgumentsBase;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class ValidateMigrationId extends NoArgumentsBase
{
    protected const NAME = 'validate_migration_id';
}
