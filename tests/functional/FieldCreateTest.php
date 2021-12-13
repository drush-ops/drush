<?php

namespace Unish;

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
        $this->assertStringContainsString('Not enough arguments (missing: "entityType")', $this->getErrorOutputRaw());
        $this->drush('field:create', ['foo'], [], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('Entity type with id \'foo\' does not exist.', $this->getErrorOutputRaw());
        $this->drush('field:create', ['user'], [], null, null, self::EXIT_ERROR);
        $this->assertStringNotContainsString('The bundle argument is required.', $this->getErrorOutputRaw());
        $this->drush('field:create', ['user', 'user'], [], null, null, self::EXIT_ERROR);
        $this->assertStringNotContainsString('bundle', $this->getErrorOutputRaw());

        // New field storage
        $this->drush('field:create', ['unish_article', 'alpha'], ['field-label' => 'Test', 'field-name' => 'field_test2', 'field-type' => 'entity_reference', 'field-widget' => 'entity_reference_autocomplete', 'cardinality' => '-1'], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('The target-type option is required.', $this->getErrorOutputRaw());
        /// @todo --target-bundle not yet validated.
        // $this->drush('field:create', ['unish_article', 'alpha'], ['field-label' => 'Test', 'field-name' => 'field_test3', 'field-type' => 'entity_reference', 'field-widget' => 'entity_reference_autocomplete', 'cardinality' => '-1', 'target-type' => 'unish_article', 'target-bundle' => 'NO-EXIST']);
        // $this->assertStringContainsString('TODO', $this->getErrorOutputRaw());
        $this->drush('field:create', ['unish_article', 'alpha'], ['field-label' => 'Test', 'field-name' => 'field_test3', 'field-type' => 'entity_reference', 'field-widget' => 'entity_reference_autocomplete', 'cardinality' => '-1', 'target-type' => 'unish_article', 'target-bundle' => 'beta']);
        $this->assertStringContainsString("Successfully created field 'field_test3' on unish_article type with bundle 'alpha'", $this->getErrorOutputRaw());
        $this->assertStringContainsString("Further customisation can be done at the following url:
http://dev/admin/structure/unish_article_types/manage/alpha/fields/unish_article.alpha.field_test3", $this->getSimplifiedErrorOutput());
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
}
