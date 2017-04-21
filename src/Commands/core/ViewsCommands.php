<?php
namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;
use Drush\Log\LogLevel;
use Drupal\views\Views;

class ViewsCommands extends DrushCommands {
  /**
   * Set several Views settings to more developer-oriented values.
   *
   * @command views-dev
   *
   * @validate-module-enabled views
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases vd
   */
  public function dev() {
    $settings = array(
      'ui.show.listing_filters' => TRUE,
      'ui.show.master_display' => TRUE,
      'ui.show.advanced_column' => TRUE,
      'ui.always_live_preview' => FALSE,
      'ui.always_live_preview_button' => TRUE,
      'ui.show.preview_information' => TRUE,
      'ui.show.sql_query.enabled' => TRUE,
      'ui.show.sql_query.where' => 'above',
      'ui.show.performance_statistics' => TRUE,
      'ui.show.additional_queries' => TRUE,
    );

    $config = \Drupal::configFactory()->getEditable('views.settings');

    foreach ($settings as $setting => $value) {
      $config->set($setting, $value);
      // Convert boolean values into a string to print.
      if (is_bool($value)) {
        $value = $value ? 'TRUE' : 'FALSE';
      }
      // Wrap string values in quotes.
      elseif (is_string($value)) {
        $value = "\"$value\"";
      }
      $this->logger->log(LogLevel::SUCCESS, dt('!setting set to !value', array(
        '!setting' => $setting,
        '!value' => $value
      )));
    }

    // Save the new config.
    $config->save();

    $this->logger->log(LogLevel::SUCCESS, (dt('New views configuration saved.')));
  }

  /**
   * Get a list of all views in the system.
   *
   * @command views-list
   *
   * @option name A string contained in the view's name to filter the results with.
   * @option tags A comma-separated list of views tags by which to filter the results.
   * @option status Filter views by status. Choices: enabled, disabled.
   * @usage drush vl
   *   Show a list of all available views.
   * @usage drush vl --name=blog
   *   Show a list of views which names contain 'blog'.
   * @usage drush vl --tags=tag1,tag2
   *   Show a list of views tagged with 'tag1' or 'tag2'.
   * @usage drush vl --status=enabled
   *   Show a list of enabled views.
   * @table-style default
   * @field-labels
   *   machine-name: Machine name
   *   label: Name
   *   description: Description
   *   status: Status
   *   tag: Tag
   * @default-fields machine-name,label,description,status
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases vl
   * @validate-module-enabled views
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
  public function vlist($options = ['name' => '', 'tags' => '', 'status' => NULL, 'format' => 'table', 'fields' => '']) {
    $disabled_views = array();
    $enabled_views = array();

    $views = \Drupal::entityTypeManager()->getStorage('view')->loadMultiple();

    // Get the --name option.
    $name = \_convert_csv_to_array($options['name']);
    $with_name = !empty($name) ? TRUE : FALSE;

    // Get the --tags option.
    $tags = \_convert_csv_to_array($options['tags']);
    $with_tags = !empty($tags) ? TRUE : FALSE;

    // Get the --status option. Store user input apart to reuse it after.
    $status = $options['status'];

    // @todo See https://github.com/consolidation/annotated-command/issues/53
    if ($status && !in_array($status, array('enabled', 'disabled'))) {
      throw new \Exception(dt('Invalid status: @status. Available options are "enabled" or "disabled"', array('@status' => $status)));
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

      $row = array(
        'machine-name' => $view->id(),
        'label' => $view->label(),
        'description' =>  $view->get('description'),
        'status' =>  $view->status() ? dt('Enabled') : dt('Disabled'),
        'tag' =>  $view->get('tag'),
      );

      // Place the row in the appropriate array, so we can have disabled views at
      // the bottom.
      if ($view->status()) {
        $enabled_views[] = $row;
      }
      else{
        $disabled_views[] = $row;
      }
    }

    // Sort alphabetically.
    asort($disabled_views);
    asort($enabled_views);

    if (count($enabled_views) || count($disabled_views)) {
      $rows = array_merge($enabled_views, $disabled_views);
      return new RowsOfFields($rows);
    }
    else {
      $this->logger->log(LogLevel::OK, dt('No views found.'));
    }
  }

  /**
   * Execute a view and show a count of the results, or the rendered HTML.
   *
   * @command views-execute
   *
   * @param string $view_name The name of the view to execute.
   * @param string $display The display ID to execute. If none specified, the default display will be used.
   * @param string $view_args A comma delimited list of values, corresponding to contextual filters.
   * @option count Display a count of the results instead of each row.
   * @option show-admin-links Show contextual admin links in the rendered markup.
   * @usage drush views-execute my_view
   *   Show the rendered HTML for the default display for the my_view View.
   * @usage drush views-execute my_view page_1 3 --count
   *   Show a count of my_view:page_1 where the first contextual filter value is 3.
   * @usage drush views-execute my_view page_1 3,foo
   *   Show the rendered HTML of my_view:page_1 where the first two contextual filter values are 3 and 'foo' respectively.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @complete \Drush\Commands\core\ViewsCommands::complete
   * @validate-entity-load view view_name
   * @aliases vex
   * @validate-module-enabled views
   *
   * @return string
   */
  public function execute($view_name, $display = NULL, $view_args = NULL, $options = ['count' => 0, 'show-admin-links' => 0]) {

    $view = Views::getView($view_name);

    // Set the display and execute the view.
    $view->setDisplay($display);
    $view->preExecute(_convert_csv_to_array($view_args));
    $view->execute();

    if (empty($view->result)) {
      $this->logger->log(LogLevel::WARNING, dt('No results returned for this View.'));
      return NULL;
    }
    elseif ($options['count']) {
      drush_backend_set_result(count($view->result));
      drush_print(count($view->result));
      return NULL;
    }
    else {
      // Don't show admin links in markup by default.
      $view->hide_admin_links = !$options['show-admin-links'];
      $build = $view->preview();
      return (string) \Drupal::service('renderer')->renderPlain($build);
    }
  }

  /**
   * Get a list of all Views and analyze warnings.
   *
   * @command views-analyze
   * @todo 'drupal dependencies' => array('views', 'views_ui'),
   * @todo Command has not  been fully tested. How to generate a message?
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @field-labels
   *   type: Type
   *   message: Message
   * @aliases va
   * @validate-module-enabled views
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields|void
   */
  public function analyze() {
    $messages = NULL;
    $messages_count = 0;
    $rows = [];

    $views = \Drupal::entityTypeManager()->getStorage('view')->loadMultiple();

    if (!empty($views)) {
      $analyzer = \Drupal::service('views.analyzer');
      foreach ($views as $view_name => $view) {
        $view = $view->getExecutable();

        if ($messages = $analyzer->getMessages($view)) {
          $rows[] = [$messages['type'], $messages['message']];
        }
      }

      $this->logger->log(LogLevel::OK, dt('A total of @total views were analyzed and @messages problems were found.', array('@total' => count($views), '@messages' => $messages_count)));
      return new RowsOfFields($rows);
    }
    else {
      $this->logger->log(LogLevel::OK, dt('There are no views to analyze'));
    }
  }

  /**
   * Enable the specified views.
   *
   * @command views-enable
   * @param string $views A comma delimited list of view names.
   * @validate-entity-load view views
   * @usage drush ven frontpage,taxonomy_term
   *   Enable the frontpage and taxonomy_term views.
   * @complete \Drush\Commands\core\ViewsCommands::complete
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases ven
   */
  public function enable($views) {
    $view_names = _convert_csv_to_array($views);
    if ($views = \Drupal::entityTypeManager()->getStorage('view')->loadMultiple($view_names)) {
      foreach ($views as $view) {
        $view->enable();
        $view->save();
      }
    }
    $this->logger->log(LogLevel::OK, dt('!str enabled.', ['!str' => implode(', ', $view_names)]));
  }

  /**
   * Disable the specified views.
   *
   * @command views-disable
   * @validate-entity-load view views
   * @param string $views A comma delimited list of view names.
   * @usage drush vdis frontpage taxonomy_term
   *   Disable the frontpage and taxonomy_term views.
   * @complete \Drush\Commands\core\ViewsCommands::complete
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases vdis
   */
  public function disable($views) {
    $view_names = _convert_csv_to_array($views);
    if ($views = \Drupal::entityTypeManager()->getStorage('view')->loadMultiple($view_names)) {
      foreach ($views as $view) {
        $view->disable();
        $view->save();
      }
    }
    $this->logger->log(LogLevel::OK, dt('!str disabled.', ['!str' => implode(', ', $view_names)]));
  }

  /**
   * A completion callback.
   *
   * @return array
   *   An array of available view names.
   */
  static function complete() {
    drush_bootstrap_max();
    return array('values' => array_keys(\Drupal::entityTypeManager()->getStorage('view')->loadMultiple()));
  }

  /**
   * Adds a cache clear option for views.
   *
   * @hook on-event cache-clear
   */
  function cacheClear(&$types, $include_bootstrapped_types) {
    if ($include_bootstrapped_types && \Drupal::moduleHandler()->moduleExists('views')) {
      $types['views'] = 'views_invalidate_cache';
    }
  }
}
