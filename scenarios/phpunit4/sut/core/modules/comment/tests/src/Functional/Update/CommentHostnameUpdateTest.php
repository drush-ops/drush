<?php

namespace Drupal\Tests\comment\Functional\Update;

use Drupal\comment\Entity\Comment;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests that comment hostname settings are properly updated.
 *
 * @group comment
 * @group legacy
 */
class CommentHostnameUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-8-rc1.bare.standard.php.gz',
    ];
  }

  /**
   * Tests comment_update_8600().
   *
   * @see comment_update_8600
   */
  public function testCommentUpdate8600() {
    /** @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $manager */
    $manager = $this->container->get('entity.definition_update_manager');

    /** @var \Drupal\Core\Field\BaseFieldDefinition $definition */
    $definition = $manager->getFieldStorageDefinition('hostname', 'comment');
    // Check that 'hostname' base field doesn't have a default value callback.
    $this->assertNull($definition->getDefaultValueCallback());

    $this->runUpdates();

    $definition = $manager->getFieldStorageDefinition('hostname', 'comment');
    // Check that 'hostname' base field default value callback was set.
    $this->assertEquals(Comment::class . '::getDefaultHostname', $definition->getDefaultValueCallback());
  }

}
