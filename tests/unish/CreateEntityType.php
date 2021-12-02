<?php

namespace Unish;

use Webmozart\PathUtil\Path;

class CreateEntityType
{
    /**
     * Note: Generators seem to hang during integration tests.
     */
    public static function createContentEntity($testCase): void
    {
        $answers = [
            'name' => 'Unish Article',
            'machine_name' => 'unish_article',
            'description' => 'A test module',
            'package' => 'unish',
            'dependencies' => 'drupal:text',
        ];
        $testCase->drush('generate', ['module'], ['verbose' => null, 'answer' => $answers, 'destination' => Path::join($testCase->webroot(), 'modules/contrib')], null, null, $testCase::EXIT_SUCCESS, null, ['SHELL_INTERACTIVE' => 1]);
        // Create a content entity type and enable its module.
        // Note that only the values below are used. The keys are for documentation.
        $answers = [
            'name' => 'unish_article',
            'entity_type_label' => 'UnishArticle',
            'entity_type_id' => 'unish_article',
            'entity_base_path' => 'admin/content/unish_article',
            'fieldable' => 'yes',
            'revisionable' => 'no',
            'translatable' => 'no',
            'bundle' => 'Yes',
            'canonical page' => 'No',
            'entity template' => 'No',
            'CRUD permissions' => 'No',
            'label base field' => 'Yes',
            'status_base_field' => 'yes',
            'created_base_field' => 'yes',
            'changed_base_field' => 'yes',
            'author_base_field' => 'yes',
            'description_base_field' => 'no',
            'rest_configuration' => 'no',
        ];
        $testCase->drush('generate', ['content-entity'], ['answer' => $answers, 'destination' => Path::join($testCase::webroot(), 'modules/contrib/unish_article')], null, null, $testCase::EXIT_SUCCESS, null, ['SHELL_INTERACTIVE' => 1]);
    }
}
