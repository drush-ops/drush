<?php

declare(strict_types=1);

namespace Drush\Drupal\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @deprecated use \Drush\Commands\sql\sanitize\SanitizePluginInterface instead.
 */
interface SanitizePluginInterface
{
    public function sanitize($result, CommandData $commandData);
    public function messages(array &$messages, InputInterface $input);
}
