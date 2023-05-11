<?php

declare(strict_types=1);

namespace Drush\Commands\generate\Generators\Drush;

use DrupalCodeGenerator\Asset\Asset;
use DrupalCodeGenerator\Asset\File;
use DrupalCodeGenerator\Asset\Resolver\ResolverInterface;
use Drush\Drush;

/**
 * Ensures that existing composer.json file has drush services entry.
 */
final class ComposerJsonResolver implements ResolverInterface
{
  /**
   * {@inheritdoc}
   */
    public function resolve(Asset $asset, string $path): ?File
    {
        if (!$asset instanceof File) {
            throw new \InvalidArgumentException('Wrong asset type.');
        }

        $existing_json = json_decode(\file_get_contents($path), true);
        if (empty($existing_json['extra']['drush']['services'])) {
            $existing_json['extra']['drush']['services']['drush.services.yml'] = '^' . Drush::getMajorVersion();
            return clone $asset->content(
                \json_encode($existing_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            );
        }

        return null;
    }
}
