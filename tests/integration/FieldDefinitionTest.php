<?php

declare(strict_types=1);

namespace Unish;

use Drush\Drupal\Commands\field\FieldDefinitionCommands;

class FieldDefinitionTest extends UnishIntegrationTestCase
{
    public function testFieldDefinition()
    {
        $this->drush(FieldDefinitionCommands::TYPES, [], ['format' => 'json']);
        $json = $this->getOutputFromJSON();
        $this->assertArrayHasKey('text_with_summary', $json);
        $this->assertEquals(0, $json['text_with_summary']['settings']['display_summary']);

        $this->drush(FieldDefinitionCommands::WIDGETS, [], ['format' => 'json']);
        $json = $this->getOutputFromJSON();
        $this->assertArrayHasKey('file_generic', $json);
        $this->assertEquals('throbber', $json['file_generic']['default_settings']['progress_indicator']);
        $this->assertArrayHasKey('number', $json);
        // Test the option.
        $this->drush(FieldDefinitionCommands::WIDGETS, [], ['field-type' => 'file', 'format' => 'json']);
        $json = $this->getOutputFromJSON();
        $this->assertArrayHasKey('file_generic', $json);
        $this->assertArrayNotHasKey('number', $json);

        $this->drush(FieldDefinitionCommands::FORMATTERS, [], ['format' => 'json']);
        $json = $this->getOutputFromJSON();
        $this->assertArrayHasKey('file_video', $json);
        $this->assertFalse($json['file_video']['default_settings']['muted']);
    }
}
