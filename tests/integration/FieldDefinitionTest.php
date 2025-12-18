<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\field\FieldFormattersCommand;
use Drush\Commands\field\FieldTypesCommand;
use Drush\Commands\field\FieldWidgetsCommand;
use Drush\Commands\pm\PmInstallCommand;
use Drush\Commands\pm\PmUninstallCommand;

class FieldDefinitionTest extends UnishIntegrationTestCase
{
    public function testFieldDefinition(): void
    {
        $this->drush(FieldTypesCommand::NAME, [], ['format' => 'json', 'fields' => 'id,label,settings']);
        $json = $this->getOutputFromJSON();
        $this->assertArrayHasKey('boolean', $json);
        $this->assertEquals('On', $json['boolean']['settings']['on_label']);

        $this->drush(PmInstallCommand::NAME, ['file'], ['yes' => true]);
        $this->drush(FieldWidgetsCommand::NAME, [], ['format' => 'json', 'fields' => 'id,label,default_settings']);
        $json = $this->getOutputFromJSON();
        $this->assertArrayHasKey('file_generic', $json);
        $this->assertEquals('throbber', $json['file_generic']['default_settings']['progress_indicator']);
        $this->assertArrayHasKey('number', $json);
        // Test the option.
        $this->drush(FieldWidgetsCommand::NAME, [], ['field-type' => 'file', 'format' => 'json']);
        $json = $this->getOutputFromJSON();
        $this->assertArrayHasKey('file_generic', $json);
        $this->assertArrayNotHasKey('number', $json);

        $this->drush(FieldFormattersCommand::NAME, [], ['format' => 'json', 'fields' => 'id,label,default_settings']);
        $json = $this->getOutputFromJSON();
        $this->assertArrayHasKey('file_video', $json);
        $this->assertFalse($json['file_video']['default_settings']['muted']);
    }

    public function tearDown(): void
    {
        $this->drush(PmUninstallCommand::NAME, ['file'], ['yes' => true]);
    }
}
