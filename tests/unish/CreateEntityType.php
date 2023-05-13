<?php

namespace Unish;

use Drush\Commands\generate\GenerateCommands;
use Symfony\Component\Filesystem\Path;

class CreateEntityType
{
    /**
     * Note: Generators seem to hang during integration tests.
     */
    public static function createContentEntity($testCase): void
    {
        $answers = [
             // Module name?
            'Unish Article',
             // Module machine name?
            'unish_article',
             // Module description?
            'A test module.',
             // Package.
            'Unish',
             // Dependencies (comma separated).
            'drupal:text',
             // Would you like to create module file?
            'No',
             // Would you like to create install file?
            'No',
             // Would you like to create README.md file?
            'No',
        ];
        $testCase->drush(GenerateCommands::GENERATE, ['module'], ['verbose' => null, 'answer' => $answers, 'destination' => Path::join($testCase->webroot(), 'modules/contrib')], null, null, $testCase::EXIT_SUCCESS, null, ['SHELL_INTERACTIVE' => 1]);

        // Create a content entity type and enable its module.
        // Note that only the values below are used. The keys are for documentation.
        $answers = [
            // Module machine name.
            'unish_article',
            // Module name.
            'Unish Article',
            // Entity type label.
            'Unish Article',
            // Entity type ID.
            'unish_article',
            // Entity class.
            'UnishArticle',
            // Entity base path.
            '/admin/content/unish_article',
            // Make the entity type fieldable?
            'yes',
            // Make the entity type revisionable?
            'no',
            // Make the entity type translatable?
            'no',
            // The entity type has bundle?
            'Yes',
            // Create canonical page?
            'No',
            // Create entity template?
            'No',
            // Create CRUD permissions?
            'No',
            // Add "label" base field?
            'Yes',
            // Add "status" base field?
            'yes',
            // Add "created" base field?
            'yes',
            // Add "changed" base field?
            'yes',
            // Add "author" base field?
            'yes',
            // Add "description" base field?
            'no',
            // Create REST configuration for the entity?
            'no',
        ];
        $testCase->drush(GenerateCommands::GENERATE, ['content-entity'], ['answer' => $answers, 'destination' => Path::join($testCase::webroot(), 'modules/contrib/unish_article')], null, null, $testCase::EXIT_SUCCESS, null, ['SHELL_INTERACTIVE' => 1]);
    }
}
