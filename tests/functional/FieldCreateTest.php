<?php

namespace Unish;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Webmozart\PathUtil\Path;

/**
 * @group commands
 */
class FieldCreateTest extends CommandUnishTestCase
{

    public function setup(): void
    {
        parent::setup();
        if (!$this->getSites()) {
            $this->setUpDrupal(1, true);
            // Create a content entity with bundles.
            CreateEntityType::createContentEntity($this);
            $this->drush('pm-enable', ['text,field_ui,unish_article']);
            $this->drush('php:script', ['create_unish_article_bundles'], ['script-path' => Path::join(__DIR__, 'resources')]);
        }
    }

    public function testFieldCreate()
    {
        // Arguments.
        $this->drush('field:create', [], [], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('The entityType argument is required', $this->getErrorOutputRaw());
        $this->drush('field:create', ['foo'], [], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('Entity type with id \'foo\' does not exist.', $this->getErrorOutputRaw());
        $this->drush('field:create', ['user'], [], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('The bundle argument is required.', $this->getErrorOutputRaw());
        $this->drush('field:create', ['user', 'user'], [], null, null, self::EXIT_ERROR);
        $this->assertStringNotContainsString('bundle', $this->getErrorOutputRaw());

        // New field storage
        $this->drush('field:create', ['unish_article', 'alpha'], ['field-label' => 'Test', 'field-name' => 'field_test2', 'field-type' => 'entity_reference', 'field-widget' => 'entity_reference_autocomplete', 'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('The target-type option is required.', $this->getErrorOutputRaw());
        /// @todo --target-bundle not yet validated.
        // $this->drush('field:create', ['unish_article', 'alpha'], ['field-label' => 'Test', 'field-name' => 'field_test3', 'field-type' => 'entity_reference', 'field-widget' => 'entity_reference_autocomplete', 'cardinality' => '-1', 'target-type' => 'unish_article', 'target-bundle' => 'NO-EXIST']);
        // $this->assertStringContainsString('TODO', $this->getErrorOutputRaw());
        $this->drush('field:create', ['unish_article', 'alpha'], ['field-label' => 'Test', 'field-name' => 'field_test3', 'field-type' => 'entity_reference', 'field-widget' => 'entity_reference_autocomplete', 'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED, 'target-type' => 'unish_article', 'target-bundle' => 'beta']);
        $this->assertStringContainsString("Successfully created field 'field_test3' on unish_article type with bundle 'alpha'", $this->getErrorOutputRaw());
        $this->assertStringContainsString("http://dev/admin/structure/unish_article_types/manage/alpha/fields/unish_article.alpha.field_test3", $this->getSimplifiedErrorOutput());
        $php = "return Drupal::entityTypeManager()->getStorage('field_config')->load('unish_article.alpha.field_test3')->getSettings()";
        $this->drush('php:eval', [$php], ['format' => 'json']);
        $settings = $this->getOutputFromJSON();
        $this->assertSame('unish_article', $settings['target_type']);
        $this->assertEquals(['beta' => 'beta'], $settings['handler_settings']['target_bundles']);
        $this->drush('field:create', ['unish_article', 'alpha'], ['field-name' => 'field_test3', 'field-label' => 'Body'], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('--existing option', $this->getSimplifiedErrorOutput());

        // Existing storage
        $this->drush('field:create', ['unish_article', 'beta'], ['existing-field-name' => 'field_test3', 'field-label' => 'Body', 'field-widget' => 'text_textarea_with_summary']);
        $this->assertStringContainsString('Success', $this->getErrorOutputRaw());
        $this->drush('field:create', ['unish_article', 'beta'], ['existing-field-name' => 'field_test3', 'field-label' => 'Body', 'field-widget' => 'text_textarea_with_summary'], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('Field with name \'field_test3\' already exists on bundle \'beta\'', $this->getErrorOutputRaw());
    }

    public function testFieldInfo()
    {
        $this->drush('field:create', ['unish_article', 'alpha'], ['field-label' => 'Test', 'field-name' => 'field_test4', 'field-description' => 'baz', 'field-type' => 'entity_reference', 'is-required' => true, 'field-widget' => 'entity_reference_autocomplete', 'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED, 'target-type' => 'unish_article', 'target-bundle' => 'beta']);
        $this->assertStringContainsString("Successfully created field 'field_test4' on unish_article type with bundle 'alpha'", $this->getSimplifiedErrorOutput());

        $this->drush('field:info', ['unish_article'], [], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('The bundle argument is required.', $this->getSimplifiedErrorOutput());
        $this->drush('field:info', ['unish_article', 'alpha'], ['format' => 'json', 'fields' => '*']);
        $json = $this->getOutputFromJSON('field_test4');
        $this->assertSame('field_test4', $json['field_name']);
        $this->assertTrue($json['required']);
        $this->assertSame('entity_reference', $json['field_type']);
        $this->assertSame('baz', $json['description']);
        $this->assertSame(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED, $json['cardinality']);
        $this->assertFalse($json['translatable']);
        $this->assertArrayHasKey('beta', $json['target_bundles']);
    }

    public function testFieldDelete()
    {
        $this->drush('field:create', ['unish_article', 'alpha'], ['field-label' => 'Test', 'field-name' => 'field_test5', 'field-description' => 'baz', 'field-type' => 'entity_reference', 'is-required' => true, 'field-widget' => 'entity_reference_autocomplete', 'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED, 'target-type' => 'unish_article', 'target-bundle' => 'beta']);
        $this->assertStringContainsString("Successfully created field 'field_test5' on unish_article type with bundle 'alpha'", $this->getSimplifiedErrorOutput());

        $this->drush('field:delete', ['unish_article'], [], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('The bundle argument is required.', $this->getErrorOutputRaw());
        $this->drush('field:delete', ['unish_article', 'alpha'], [], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('The field-name option is required.', $this->getErrorOutputRaw());

        $this->drush('field:delete', ['unish_article', 'alpha'], ['field-name' => 'field_testZZZZZ'], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString("Field with name 'field_testZZZZZ' does not exist on bundle 'alpha'", $this->getErrorOutputRaw());
        $this->drush('field:delete', ['unish_article', 'alpha'], ['field-name' => 'field_test5']);
        $this->assertStringContainsString(" The field Test has been deleted from the Alpha bundle.", $this->getErrorOutputRaw());
    }
}
