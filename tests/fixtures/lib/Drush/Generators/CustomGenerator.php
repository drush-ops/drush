<?php

declare(strict_types=1);

namespace Custom\Library\Drush\Generators;

use DrupalCodeGenerator\Asset\AssetCollection;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;

#[Generator(
    name: 'drush:testing-generator',
    description: 'An internal generator used for tests',
    hidden: true,
    type: GeneratorType::OTHER,
)]
class CustomGenerator extends BaseGenerator
{
    public function generate(array &$vars, AssetCollection $assets): void
    {
        $assets->addFile('drush/foo.bar');
    }
}
