<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\OutputFormatters\Options\FormatterOptions;

final class CoreCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    const VERSION = 'version';
    const GLOBAL_OPTIONS = 'core:global-options';

    /**
     * All global options.
     */
    #[CLI\Command(name: self::GLOBAL_OPTIONS, aliases: ['core-global-options'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Topics(isTopic: true)]
    #[CLI\FieldLabels(labels: ['name' => 'Name', 'description' => 'Description'])]
    #[CLI\FilterDefaultField(field: 'name')]
    public function globalOptions($options = ['format' => 'table']): RowsOfFields
    {
        $application = Drush::getApplication();
        $def = $application->getDefinition();
        foreach ($def->getOptions() as $key => $value) {
            $name = '--' . $key;
            if ($value->getShortcut()) {
                $name = '-' . $value->getShortcut() . ', ' . $name;
            }
            $rows[] = [
                'name' => $name,
                'description' => $value->getDescription(),
            ];
        }

        // Also document the keys that are recognized by PreflightArgs. It would be possible to redundantly declare
        // those as global options. We don't do that for now, to avoid confusion.
        $ancient = [
            'config' => 'Specify an additional config file to load. See example.drush.yml. Example: /path/file',
            'alias-path' => 'Specifies additional paths where Drush will search for alias files. Example: /path/alias1:/path/alias2',
            'include' => 'Additional directories to search for Drush commands. Commandfiles should be placed in a subdirectory called <info>Commands</info>. Example: path/dir',
            'local' => 'Don\'t look outside the Composer project for Drush config.',
            'strict' => 'Return an error on unrecognized options. --strict=0 allows unrecognized options.',
            'ssh-options' => 'A string of extra options that will be passed to the ssh command. Example: -p 100',
        ];
        foreach ($ancient as $name => $description) {
            $rows[] = [
                'name' => '--' . $name,
                'description' => $description,
            ];
        }
        usort($rows, function ($a, $b) {
            return strnatcmp($a['name'], $b['name']);
        });
        return new RowsOfFields($rows);
    }

    /**
     * Show Drush version.
     */
    #[CLI\Command(name: self::VERSION)]
    #[CLI\Format(listDelimiter: ':', tableStyle: 'compact')]
    #[CLI\FieldLabels(labels: ['drush-version' => 'Drush version'])]
    public function version($options = ['format' => 'table']): PropertyList
    {
        $versionPropertyList = new PropertyList(['drush-version' => Drush::getVersion()]);
        $versionPropertyList->addRendererFunction(
            function ($key, $cellData, FormatterOptions $options) {
                if ($key == 'drush-version') {
                    return Drush::sanitizeVersionString($cellData);
                }
                return $cellData;
            }
        );

        return $versionPropertyList;
    }
}
