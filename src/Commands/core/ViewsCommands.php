<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\views\Views;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;

final class ViewsCommands extends DrushCommands
{
    use AutowireTrait;

    const DEV = 'views:dev';
    const EXECUTE = 'views:execute';
    const LIST = 'views:list';
    const ENABLE = 'views:enable';
    const DISABLE = 'views:disable';


    public function __construct(
        protected ConfigFactoryInterface $configFactory,
        protected ModuleHandlerInterface $moduleHandler,
        protected EntityTypeManagerInterface $entityTypeManager,
        protected RendererInterface $renderer
    ) {
    }

    public function getConfigFactory(): ConfigFactoryInterface
    {
        return $this->configFactory;
    }

    public function getModuleHandler(): ModuleHandlerInterface
    {
        return $this->moduleHandler;
    }

    public function getEntityTypeManager(): EntityTypeManagerInterface
    {
        return $this->entityTypeManager;
    }

    public function getRenderer(): RendererInterface
    {
        return $this->renderer;
    }

    /**
     * Set several Views settings to more developer-oriented values.
     */
    #[CLI\Command(name: self::DEV, aliases: ['vd', 'views-dev'])]
    #[CLI\ValidateModulesEnabled(modules: ['views'])]
    public function dev(): void
    {
        $settings = [
            'ui.show.listing_filters' => true,
            'ui.show.master_display' => true,
            'ui.show.advanced_column' => true,
            'ui.always_live_preview' => false,
            'ui.always_live_preview_button' => true,
            'ui.show.preview_information' => true,
            'ui.show.sql_query.enabled' => true,
            'ui.show.sql_query.where' => 'above',
            'ui.show.performance_statistics' => true,
            'ui.show.additional_queries' => true,
        ];

        $config = $this->getConfigFactory()->getEditable('views.settings');

        foreach ($settings as $setting => $value) {
            $config->set($setting, $value);
            // Convert boolean values into a string to print.
            if (is_bool($value)) {
                $value = $value ? 'TRUE' : 'FALSE';
            } elseif (is_string($value)) {
                // Wrap string values in quotes.
                $value = "\"$value\"";
            }
            $this->logger()->success(dt('!setting set to !value', [
                '!setting' => $setting,
                '!value' => $value
            ]));
        }

        // Save the new config.
        $config->save();

        $this->logger()->success(dt('New views configuration saved.'));
    }

    /**
     * Get a list of all views in the system.
     */
    #[CLI\Command(name: self::LIST, aliases: ['vl', 'views-list'])]
    #[CLI\Option(name: 'tags', description: 'A comma-separated list of views tags by which to filter the results.')]
    #[CLI\Option(name: 'status', description: 'Filter views by status. Choices: enabled, disabled.')]
    #[CLI\Usage(name: 'drush vl', description: 'Show a list of all available views.')]
    #[CLI\Usage(name: 'drush vl --name=blog', description: 'Show a list of views which names contain \'blog\'.')]
    #[CLI\Usage(name: 'drush vl --tags=tag1,tag2', description: "Show a list of views tagged with 'tag1' or 'tag2'.")]
    #[CLI\Usage(name: 'drush vl --status=enabled', description: 'Show a list of enabled views.')]
    #[CLI\FieldLabels(labels: [
        'machine-name' => 'Machine name',
        'label' => 'Name',
        'description' => 'Description',
        'status' => 'Status',
        'tag' => 'Tag',
    ])]
    #[CLI\DefaultTableFields(fields: ['machine-name', 'label', 'description', 'status'])]
    #[CLI\ValidateModulesEnabled(modules: ['views'])]
    #[CLI\FilterDefaultField(field: 'machine_name')]
    public function vlist($options = ['name' => self::REQ, 'tags' => self::REQ, 'status' => self::REQ, 'format' => 'table']): ?RowsOfFields
    {
        $disabled_views = [];
        $enabled_views = [];

        $views = $this->getEntityTypeManager()->getStorage('view')->loadMultiple();

        // Get the --name option.
        $name = StringUtils::csvToArray($options['name']);
        $with_name = $name !== [];

        // Get the --tags option.
        $tags =  StringUtils::csvToArray($options['tags']);
        $with_tags = $tags !== [];

        // Get the --status option. Store user input apart to reuse it after.
        $status = $options['status'];

        // @todo See https://github.com/consolidation/annotated-command/issues/53
        if ($status && !in_array($status, ['enabled', 'disabled'])) {
            throw new \Exception(dt('Invalid status: @status. Available options are "enabled" or "disabled"', ['@status' => $status]));
        }

        // Setup a row for each view.
        foreach ($views as $view) {
            // If options were specified, check that first mismatch push the loop to the
            // next view.
            if ($with_name && !stristr($view->id(), $name[0])) {
                continue;
            }
            if ($with_tags && !in_array($view->get('tag'), $tags)) {
                continue;
            }

            $status_bool = $status == 'enabled';
            if ($status && ($view->status() !== $status_bool)) {
                continue;
            }

            $row = [
            'machine-name' => $view->id(),
            'label' => $view->label(),
            'description' =>  $view->get('description'),
            'status' =>  $view->status() ? dt('Enabled') : dt('Disabled'),
            'tag' =>  $view->get('tag'),
            ];

            // Place the row in the appropriate array, so we can have disabled views at
            // the bottom.
            if ($view->status()) {
                  $enabled_views[] = $row;
            } else {
                  $disabled_views[] = $row;
            }
        }

        // Sort alphabetically.
        asort($disabled_views);
        asort($enabled_views);

        if (count($enabled_views) || count($disabled_views)) {
            $rows = array_merge($enabled_views, $disabled_views);
            return new RowsOfFields($rows);
        } else {
            $this->logger()->notice(dt('No views found.'));
            // Satisfy this method's type hint.
            return null;
        }
    }

    /**
     * Execute a view and show a count of the results, or the rendered HTML.
     */
    #[CLI\Command(name: self::EXECUTE, aliases: ['vex', 'views-execute'])]
    #[CLI\Argument(name: 'view_name', description: 'The name of the view to execute.')]
    #[CLI\Argument(name: 'display', description: 'The display ID to execute. If none specified, the default display will be used.')]
    #[CLI\Argument(name: 'view_args', description: 'A comma delimited list of values, corresponding to contextual filters.')]
    #[CLI\Option(name: 'count', description: 'Display a count of the results instead of each row.')]
    #[CLI\Option(name: 'show-admin-links', description: 'Show contextual admin links in the rendered markup.')]
    #[CLI\Usage(name: 'drush views:execute my_view', description: 'Show the rendered HTML for the default display for the my_view View.')]
    #[CLI\Usage(name: 'drush views:execute my_view page_1 3 --count', description: 'Show a count of my_view:page_1 where the first contextual filter value is 3.')]
    #[CLI\Usage(name: 'drush views:execute my_view page_1 3,foo', description: "Show the rendered HTML of my_view:page_1 where the first two contextual filter values are 3 and 'foo' respectively.")]
    #[CLI\ValidateEntityLoad(entityType: 'view', argumentName: 'view_name')]
    #[CLI\ValidateModulesEnabled(modules: ['views'])]
    public function execute(string $view_name, $display = null, $view_args = null, $options = ['count' => 0, 'show-admin-links' => false]): ?string
    {

        $view = Views::getView($view_name);

        // Set the display and execute the view.
        $view->setDisplay($display);
        $view->preExecute(StringUtils::csvToArray($view_args));
        $view->execute();

        if (empty($view->result)) {
            $this->logger()->success(dt('No results returned for this View.'));
            return null;
        } elseif ($options['count']) {
            $this->io()->writeln($view->result);
            return null;
        } else {
            // Don't show admin links in markup by default.
            $view->hide_admin_links = !$options['show-admin-links'];
            $build = $view->preview();
            return (string) $this->getRenderer()->renderPlain($build);
        }
    }

    /**
     * Get a list of all Views and analyze warnings.
     */
    public function analyze(): ?RowsOfFields
    {
        $messages = null;
        $messages_count = 0;
        $rows = [];

        $views = $this->getEntityTypeManager()->getStorage('view')->loadMultiple();

        if (!empty($views)) {
            $analyzer = \Drupal::service('views.analyzer');
            foreach ($views as $view_name => $view) {
                $view = $view->getExecutable();

                if ($messages = $analyzer->getMessages($view)) {
                    $rows[] = [$messages['type'], $messages['message']];
                }
            }

            $this->logger()->success(dt('A total of @total views were analyzed and @messages problems were found.', ['@total' => count($views), '@messages' => $messages_count]));
            return new RowsOfFields($rows);
        } else {
            $this->logger()->success(dt('There are no views to analyze'));
            return null;
        }
    }

    /**
     * Enable the specified views.
     */
    #[CLI\Command(name: self::ENABLE, aliases: ['ven', 'views-enable'])]
    #[CLI\Argument(name: 'views', description: 'A comma delimited list of view names.')]
    #[CLI\Usage(name: 'drush ven frontpage,taxonomy_term', description: 'Enable the frontpage and taxonomy_term views.')]
    #[CLI\ValidateEntityLoad(entityType: 'view', argumentName: 'views')]
    public function enable(string $views): void
    {
        $view_names = StringUtils::csvToArray($views);
        if ($views = $this->getEntityTypeManager()->getStorage('view')->loadMultiple($view_names)) {
            foreach ($views as $view) {
                $view->enable();
                $view->save();
            }
        }
        $this->logger()->success(dt('!str enabled.', ['!str' => implode(', ', $view_names)]));
    }

    /**
     * Disable the specified views.
     */
    #[CLI\Command(name: self::DISABLE, aliases: ['vdis', 'views-disable'])]
    #[CLI\ValidateEntityLoad(entityType: 'view', argumentName: 'views')]
    #[CLI\Argument(name: 'views', description: 'A comma delimited list of view names.')]
    #[CLI\Usage(name: 'drush vdis frontpage taxonomy_term', description: 'Disable the frontpage and taxonomy_term views.')]
    public function disable(string $views): void
    {
        $view_names = StringUtils::csvToArray($views);
        if ($views = $this->getEntityTypeManager()->getStorage('view')->loadMultiple($view_names)) {
            foreach ($views as $view) {
                $view->disable();
                $view->save();
            }
        }
        $this->logger()->success(dt('!str disabled.', ['!str' => implode(', ', $view_names)]));
    }

    /**
     * Adds a cache clear option for views.
     */
    #[CLI\Hook(type: HookManager::ON_EVENT, target: CacheCommands::EVENT_CLEAR)]
    public function cacheClear(&$types, $include_bootstrapped_types): void
    {
        if ($include_bootstrapped_types && $this->getModuleHandler()->moduleExists('views')) {
            $types['views'] = 'views_invalidate_cache';
        }
    }
}
