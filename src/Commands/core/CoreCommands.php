<?php
namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;

class CoreCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{

    use SiteAliasManagerAwareTrait;

    /**
     * All global options.
     *
     * @command core:global-options
     * @hidden
     * @topic
     * @table-style default
     * @field-labels
     *   name: Name
     *   description: Description
     * @default-fields name,description
     * @aliases core-global-options
     *
     * @filter-default-field name
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     */
    public function globalOptions($options = ['format' => 'table'])
    {
        $application = Drush::getApplication();
        $def = $application->getDefinition();
        foreach ($def->getOptions() as $key => $value) {
            $name = '--'. $key;
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
        $ancient = drush_get_global_options();
        foreach (['config', 'alias-path', 'include', 'local', 'backend', 'strict', 'ssh-options'] as $name) {
            $rows[] = [
                'name' => '--' . $name,
                'description' => $ancient[$name]['description'],
            ];
        }
        usort($rows, function ($a, $b) {
            return strnatcmp($a['name'], $b['name']);
        });
        return new RowsOfFields($rows);
    }

    /**
     * Show Drush version.
     *
     * @command version
     * @table-style compact
     * @list-delimiter :
     * @field-labels
     *   drush-version: Drush version
     *
     * @return \Consolidation\OutputFormatters\StructuredData\PropertyList
     *
     */
    public function version($options = ['format' => 'table'])
    {
        return new PropertyList(['drush-version' => Drush::getVersion()]);
    }
}
