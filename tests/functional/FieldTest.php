<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drush\Commands\config\ConfigCommands;
use Drush\Commands\core\PhpCommands;
use Drush\Commands\field\FieldBaseInfoCommands;
use Drush\Commands\field\FieldBaseOverrideCreateCommands;
use Drush\Commands\field\FieldCreateCommands;
use Drush\Commands\field\FieldDeleteCommands;
use Drush\Commands\field\FieldInfoCommands;
use Drush\Commands\pm\PmCommands;
use Symfony\Component\Filesystem\Path;

/**
 * @group commands
 */
class FieldTest extends CommandUnishTestCase
{
    public function setup(): void
    {
        parent::setup();
        if (!$this->getSites()) {
            $this->setUpDrupal(1, true);
            // Create a content entity with bundles.
            CreateEntityType::createContentEntity($this);
            $this->drush(PmCommands::INSTALL, ['text,field_ui,unish_article']);
            $this->drush(PhpCommands::SCRIPT, ['create_unish_article_bundles'], ['script-path' => Path::join(__DIR__, 'resources')]);
        }
    }

    public function testFieldCreate()
    {
        // Arguments.
        $this->drush(FieldCreateCommands::CREATE, [], [], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('The entityType argument is required', $this->getErrorOutputRaw());
        $this->drush(FieldCreateCommands::CREATE, ['foo'], [], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('Entity type with id \'foo\' does not exist.', $this->getErrorOutputRaw());
        $this->drush(FieldCreateCommands::CREATE, ['unish_article'], [], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('The bundle argument is required.', $this->getErrorOutputRaw());
        $this->drush(FieldCreateCommands::CREATE, ['user', 'user'], [], null, null, self::EXIT_ERROR);
        $this->assertStringNotContainsString('bundle', $this->getErrorOutputRaw());

        // New field storage
        $this->drush(FieldCreateCommands::CREATE, ['unish_article', 'alpha'], ['field-label' => 'Test', 'field-name' => 'field_test2', 'field-type' => 'entity_reference', 'field-widget' => 'entity_reference_autocomplete', 'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('The target-type option is required.', $this->getErrorOutputRaw());
        $this->drush(FieldCreateCommands::CREATE, ['unish_article', 'alpha'], ['field-label' => 'Test', 'field-name' => 'field_test6', 'field-type' => 'entity_reference', 'field-widget' => 'entity_reference_autocomplete', 'cardinality' => '-1', 'target-type' => 'unish_article', 'target-bundle' => 'NO-EXIST'], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString("Bundle 'NO-EXIST' does not exist on entity type with id 'unish_article'.", $this->getErrorOutputRaw());
        $this->drush(FieldCreateCommands::CREATE, ['unish_article', 'alpha'], ['field-label' => 'Test', 'field-name' => 'field_test3', 'field-type' => 'entity_reference', 'field-widget' => 'entity_reference_autocomplete', 'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED, 'target-type' => 'unish_article', 'target-bundle' => 'beta']);
        $this->assertStringContainsString("Successfully created field 'field_test3' on unish_article type with bundle 'alpha'", $this->getErrorOutputRaw());
        $this->assertStringContainsString("http://dev/admin/structure/unish_article_types/manage/alpha/fields/unish_article.alpha.field_test3", $this->getSimplifiedErrorOutput());
        $php = "return Drupal::entityTypeManager()->getStorage('field_config')->load('unish_article.alpha.field_test3')->getSettings()";
        $this->drush(PhpCommands::EVAL, [$php], ['format' => 'json']);
        $settings = $this->getOutputFromJSON();
        $this->assertSame('unish_article', $settings['target_type']);
        $this->assertEquals(['beta' => 'beta'], $settings['handler_settings']['target_bundles']);
        $this->drush(FieldCreateCommands::CREATE, ['unish_article', 'alpha'], ['field-name' => 'field_test3', 'field-label' => 'Body'], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('--existing option', $this->getSimplifiedErrorOutput());

        // Existing storage
        $this->drush(FieldCreateCommands::CREATE, ['unish_article', 'beta'], ['existing-field-name' => 'field_test3', 'field-label' => 'Body', 'field-widget' => 'text_textarea_with_summary']);
        $this->assertStringContainsString('Success', $this->getErrorOutputRaw());
        $this->drush(FieldCreateCommands::CREATE, ['unish_article', 'beta'], ['existing-field-name' => 'field_test3', 'field-label' => 'Body', 'field-widget' => 'text_textarea_with_summary'], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('Field with name \'field_test3\' already exists on bundle \'beta\'', $this->getErrorOutputRaw());
        if (version_compare(\Drupal::VERSION, '10.1.0') < 0) {
            $this->markTestSkipped('Allowed formats available since Drupal 10.1.0');
        }
        // Allowed formats
        $this->drush(FieldCreateCommands::CREATE, ['unish_article', 'alpha'], ['field-name' => 'field_test_allowed_formats', 'field-label' => 'Text', 'field-type' => 'string', 'allowed-formats' => 'minimal'], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('The "--allowed-formats" option does not exist.', $this->getSimplifiedErrorOutput());
        $this->drush(FieldCreateCommands::CREATE, ['unish_article', 'alpha'], ['field-name' => 'field_test_allowed_formats', 'field-label' => 'Text', 'field-type' => 'text_long', 'cardinality' => 1, 'allowed-formats' => 'baz'], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('The following text formats do not exist: baz', $this->getSimplifiedErrorOutput());
        $this->drush(FieldCreateCommands::CREATE, ['unish_article', 'alpha'], ['field-name' => 'field_test_allowed_formats', 'field-label' => 'Text', 'field-type' => 'text_long', 'cardinality' => 1, 'allowed-formats' => 'plain_text']);
        $this->assertStringContainsString("Successfully created field 'field_test_allowed_formats' on unish_article type with bundle 'alpha'", $this->getErrorOutputRaw());
    }

    public function testFieldInfo()
    {
        $this->drush(FieldCreateCommands::CREATE, ['unish_article', 'alpha'], ['field-label' => 'Test', 'field-name' => 'field_test4', 'field-description' => 'baz', 'field-type' => 'entity_reference', 'is-required' => true, 'field-widget' => 'entity_reference_autocomplete', 'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED, 'target-type' => 'unish_article', 'target-bundle' => 'beta']);
        $this->assertStringContainsString("Successfully created field 'field_test4' on unish_article type with bundle 'alpha'", $this->getSimplifiedErrorOutput());

        $this->drush(FieldInfoCommands::INFO, ['unish_article'], [], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('The bundle argument is required.', $this->getSimplifiedErrorOutput());
        $this->drush(FieldInfoCommands::INFO, ['unish_article', 'alpha'], ['format' => 'json', 'fields' => '*']);
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
        $this->drush(FieldCreateCommands::CREATE, ['unish_article', 'alpha'], ['field-label' => 'Test', 'field-name' => 'field_test5', 'field-description' => 'baz', 'field-type' => 'entity_reference', 'is-required' => true, 'field-widget' => 'entity_reference_autocomplete', 'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED, 'target-type' => 'unish_article', 'target-bundle' => 'beta']);
        $this->assertStringContainsString("Successfully created field 'field_test5' on unish_article type with bundle 'alpha'", $this->getSimplifiedErrorOutput());

        $this->drush(FieldDeleteCommands::DELETE, ['unish_article'], ['field-name' => 'field_test5'], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('The bundle argument is required.', $this->getErrorOutputRaw());
        $this->drush(FieldDeleteCommands::DELETE, ['unish_article', 'alpha'], [], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('The field-name option is required.', $this->getErrorOutputRaw());

        $this->drush(FieldDeleteCommands::DELETE, ['unish_article', 'alpha'], ['field-name' => 'field_testZZZZZ'], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString("Field with name 'field_testZZZZZ' does not exist.", $this->getErrorOutputRaw());
        $this->drush(FieldDeleteCommands::DELETE, ['unish_article', 'alpha'], ['field-name' => 'field_test5']);
        $this->assertStringContainsString(" The field Test has been deleted from the Alpha bundle.", $this->getErrorOutputRaw());

        // All bundles
        $this->drush(FieldCreateCommands::CREATE, ['unish_article', 'alpha'], ['field-label' => 'Test', 'field-name' => 'field_test5', 'field-description' => 'baz', 'field-type' => 'entity_reference', 'is-required' => true, 'field-widget' => 'entity_reference_autocomplete', 'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED, 'target-type' => 'unish_article', 'target-bundle' => 'beta']);
        $this->drush(FieldDeleteCommands::DELETE, ['unish_article'], ['field-name' => 'field_test5', 'all-bundles' => true]);
        $this->assertStringContainsString("The field Test has been deleted from the Alpha bundle.", $this->getErrorOutputRaw());
    }

    public function testFieldBaseInfo()
    {
        $this->drush(FieldBaseInfoCommands::BASE_INFO, ['user'], ['format' => 'json', 'fields' => '*']);
        $json = $this->getOutputFromJSON();
        $this->assertArrayHasKey('name', $json);
        $this->assertSame('Name', $json['name']['label']);
    }

    public function testFieldBaseCreateOverride()
    {
        $options = [
          'field-name' => 'name',
          'field-label' => 'Handle',
          'field-description' => 'The way this person wishes to called',
          'is-required' => true,
        ];
        $this->drush(FieldBaseOverrideCreateCommands::BASE_OVERRIDE_CREATE, ['user', 'user'], $options);
        $this->drush(ConfigCommands::GET, ['core.base_field_override.user.user.name'], ['format' => 'json']);
        $json = $this->getOutputFromJSON();
        $this->assertSame('Handle', $json['label']);
        $this->assertSame(true, $json['required']);
    }
}
