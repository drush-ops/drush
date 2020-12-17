<?php

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
    public function fields()
    {
        return [];
    }

    public function __toString()
    {
        return 'test_source_with_missed_requirements';
    }

    public function getIds()
    {
        return ['id' => ['type' => 'integer']];
    }

    public function checkRequirements()
    {
        throw new RequirementsException('message', [
          'type1' => ['a', 'b', 'c'],
          'type2' => ['x', 'y', 'z'],
        ]);
    }

    protected function initializeIterator()
    {
        return new \ArrayIterator([]);
    }
}
