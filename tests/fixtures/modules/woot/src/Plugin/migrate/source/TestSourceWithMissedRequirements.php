<?php

declare(strict_types=1);

namespace Drupal\woot\Plugin\migrate\source;

use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\RequirementsInterface;

/**
 * @MigrateSource(
 *   id = "test_source_with_missed_requirements",
 * )
 */
class TestSourceWithMissedRequirements extends SourcePluginBase implements RequirementsInterface
{
    public function fields(): array
    {
        return [];
    }

    public function __toString(): string
    {
        return 'test_source_with_missed_requirements';
    }

    public function getIds(): array
    {
        return ['id' => ['type' => 'integer']];
    }

    public function checkRequirements(): never
    {
        throw new RequirementsException('message', [
          'type1' => ['a', 'b', 'c'],
          'type2' => ['x', 'y', 'z'],
        ]);
    }

    protected function initializeIterator(): \Iterator
    {
        return new \ArrayIterator([]);
    }
}
