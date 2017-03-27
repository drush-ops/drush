<?php
namespace Drush\Commands\core;

use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drupal;
use Drush\Commands\DrushCommands;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Drupal\DrupalUtil;
use Drush\Log\LogLevel;


class CoreCommands extends DrushCommands {

  /**
   * Run all cron hooks in all active modules for specified site.
   *
   * @command core-cron
   * @aliases cron
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @topics docs-cron
   */
  public function cron() {
    $result = Drupal::service('cron')->run();
    if ($result) {
      $this->logger()->log('Cron run successful.', LogLevel::SUCCESS);
    }
    else {
      throw new \Exception(dt('Cron run failed.'));
    }
  }

  /**
   * Compile all Twig template(s).
   *
   * @command twig-compile
   * @aliases twigc
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   */
  public function twigCompile() {
    require_once DRUSH_DRUPAL_CORE . "/themes/engines/twig/twig.engine";
    // Scan all enabled modules and themes.
    // @todo refactor since \Drush\Boot\DrupalBoot::commandfile_searchpaths is similar.
    $ignored_modules = drush_get_option_list('ignored-modules', array());
    $modules = array_keys(\Drupal::moduleHandler()->getModuleList());
    $module_list = array_combine($modules, $modules);
    $cid = drush_cid_install_profile();
    if ($cached = drush_cache_get($cid)) {
      $ignored_modules[] = $cached->data;
    }
    foreach (array_diff($module_list, $ignored_modules) as $module) {
      $searchpaths[] = drupal_get_path('module', $module);
    }

    $themes = \Drupal::service('theme_handler')->listInfo();
    foreach ($themes as $name => $theme) {
      $searchpaths[] = $theme->getPath();
    }

    foreach ($searchpaths as $searchpath) {
      foreach ($file = drush_scan_directory($searchpath, '/\.html.twig/', array('tests')) as $file) {
        $relative = str_replace(drush_get_context('DRUSH_DRUPAL_ROOT'). '/', '', $file->filename);
        // @todo Dynamically disable twig debugging since there is no good info there anyway.
        twig_render_template($relative, array('theme_hook_original' => ''));
        $this->logger()->info(dt('Compiled twig template !path', array('!path' => $relative)));
      }
    }
  }

  /**
   * Information about things that may be wrong in your Drupal installation.
   *
   * @command core-requirements
   * @option severity Only show status report messages with a severity greater than or equal to the specified value.
   * @option ignore Comma-separated list of requirements to remove from output. Run with --format=yaml to see key values to use.
   * @aliases status-report, rq
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @usage drush core-requirements
   *   Show all status lines from the Status Report admin page.
   * @usage drush core-requirements --severity=2
   *   Show only the red lines from the Status Report admin page.
   * @usage drush core-requirements --format=json
   *   Print a short report in JSON format, where severity 2=error, 1=warning, and 0/-1=OK
   * @table-style default
   * @field-labels
   *   title: Title
   *   severity: Severity
   *   sid: SID
   *   description: Description
   *   value: Summary
   *   reason: Reason
   *   weight: Weight
   * @default-fields title,severity,value
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
  public function requirements($options = ['format' => 'table', 'severity' => -1, 'ignore' => NULL]) {
    include_once DRUSH_DRUPAL_CORE . '/includes/install.inc';
    $severities = array(
      REQUIREMENT_INFO => dt('Info'),
      REQUIREMENT_OK => dt('OK'),
      REQUIREMENT_WARNING => dt('Warning'),
      REQUIREMENT_ERROR => dt('Error'),
    );

    drupal_load_updates();

    $requirements = \Drupal::moduleHandler()->invokeAll('requirements', ['runtime']);
    // If a module uses "$requirements[] = " instead of
    // "$requirements['label'] = ", then build a label from
    // the title.
    foreach($requirements as $key => $info) {
      if (is_numeric($key)) {
        unset($requirements[$key]);
        $new_key = strtolower(str_replace(' ', '_', $info['title']));
        $requirements[$new_key] = $info;
      }
    }
    $ignore_requirements = _convert_csv_to_array($options['ignore']);
    foreach ($ignore_requirements as $ignore) {
      unset($requirements[$ignore]);
    }
    ksort($requirements);

    $min_severity = $options['severity'];
    $i=0;
    foreach($requirements as $key => $info) {
      $severity = array_key_exists('severity', $info) ? $info['severity'] : -1;
      $rows[$i] = [
        'title' => (string) $info['title'],
        'value' => (string) $info['value'],
        'description' => DrupalUtil::drush_render($info['description']),
        'sid' => $severity,
        'severity' => @$severities[$severity]
      ];
      if ($severity < $min_severity) {
        unset($rows[$i]);
      }
      $i++;
    }
    $result = new RowsOfFields($rows);
    return $result;
  }

  /**
   * Return the filesystem path for modules/themes and other key folders.
   *
   * @command drupal-directory
   * @param string $target A module/theme name, or special names like root, files, private, or an alias : path alias string such as @alias:%files. Defaults to root.
   * @option component The portion of the evaluated path to return.  Defaults to 'path'; 'name' returns the site alias of the target.
   * @option local-only Reject any target that specifies a remote site.
   * @usage cd `drush dd devel`
   *   Navigate into the devel module directory
   * @usage cd `drush dd`
   *   Navigate to the root of your Drupal site
   * @usage cd `drush dd files`
   *   Navigate to the files directory.
   * @usage drush dd @alias:%files
   *   Print the path to the files directory on the site @alias.
   * @usage edit `drush dd devel`/devel.module
   *   Open devel module in your editor (customize 'edit' for your editor)
   * @aliases dd
   * @bootstrap DRUSH_BOOTSTRAP_NONE
   */
  public function drupalDirectory($target = 'root', $options = ['component' => 'path', 'local-only' => FALSE]) {
    $path = $this->getPath($target, $options['component'], $options['local-only']);

    // If getPath() is working right, it will turn
    // %blah into the path to the item referred to by the key 'blah'.
    // If there is no such key, then no replacement is done.  In the
    // case of the dd command, we will consider it an error if
    // any keys are -not- replaced in _drush_core_directory.
    if ($path && (strpos($path, '%') === FALSE)) {
      return $path;
    }
    else {
      throw new \Exception(dt("Target '!target' not found.", array('!target' => $target)));
    }
  }

  /**
   * Given a target (e.g. @site:%modules), return the evaluated directory path.
   *
   * @param $target
   *   The target to evaluate.  Can be @site or /path or @site:path
   *   or @site:%pathalias, etc. (just like rsync parameters)
   * @param $component
   *   The portion of the evaluated path to return.  Possible values:
   *   'path' - the full path to the target (default)
   *   'name' - the name of the site from the path (e.g. @site1)
   *   'user-path' - the part after the ':' (e.g. %modules)
   *   'root' & 'uri' - the Drupal root and URI of the site from the path
   *   'path-component' - The ':' and the path
   */
  protected function getPath($target = 'root', $component = 'path', $local_only = FALSE) {
    // Normalize to a sitealias in the target.
    $normalized_target = $target;
    if (strpos($target, ':') === FALSE) {
      if (substr($target, 0, 1) != '@') {
        // drush_sitealias_evaluate_path() requires bootstrap to database.
        if (!drush_bootstrap_to_phase(DRUSH_BOOTSTRAP_DRUPAL_DATABASE)) {
          throw new \Exception((dt('You need to specify an alias or run this command within a Drupal site.')));
        }
        $normalized_target = '@self:';
        if (substr($target, 0, 1) != '%') {
          $normalized_target .= '%';
        }
        $normalized_target .= $target;
      }
    }
    $additional_options = array();
    $values = drush_sitealias_evaluate_path($normalized_target, $additional_options, $local_only);
    if (isset($values[$component])) {
      // Hurray, we found the destination.
      return $values[$component];
    }
  }

  /**
   * All global options.
   *
   * @command core-global-options
   * @hidden
   * @topic
   * @bootstrap DRUSH_BOOTSTRAP_NONE
   *
   * @field-labels
   *   name: Name
   *   description: Description
   * @default-fields name,description
   *
   * @return RowsOfFields
   */
  public function globalOptions($options = ['format' => 'table']) {
    $application = \Drush::getApplication();
    $def = $application->getDefinition();
    foreach ($def->getOptions() as $key => $value) {
      $rows[] = [
        'name' => '--'. $key,
        'description' => $value->getDescription(),
      ];
    }
    return new RowsOfFields($rows);
  }

  /**
   * Show Drush version.
   *
   * @command version
   * @bootstrap DRUSH_BOOTSTRAP_NONE
   * @table-style compact
   * @list-delimiter :
   * @field-labels
   *   drush-version: Drush version
   *
   * @return \Consolidation\OutputFormatters\StructuredData\PropertyList
   *
   */
  public function version($options = ['format' => 'table']) {
    return new PropertyList(['drush-version' => \Drush::getVersion()]);
  }

  /**
   * Execute a shell command. Usually used with a site alias.
   *
   * Used by shell aliases that start with !.
   *
   * @command core-execute
   * @param $args The shell command to be executed.
   * @option escape Escape parameters before executing them with the shell. Default is escape; use --no-escape to disable.
   * @optionset_proc_build
   * @handle-remote-commands
   * @usage drush core-execute git pull origin rebase -- --no-ff
   *   Retrieve latest code from git
   * @aliases exec,execute
   * @topics docs-aliases
   */
  public function execute(array $args, array $options = ['escape' => TRUE]) {
    $result = TRUE;
    if ($options['escape']) {
      for ($x = 0; $x < count($args); $x++) {
        // escape all args except for command separators.
        if (!in_array($args[$x], array('&&', '||', ';'))) {
          $args[$x] = drush_escapeshellarg($args[$x]);
        }
      }
    }
    $cmd = implode(' ', $args);
    // If we selected a Drupal site, then cwd to the site root prior to exec
    $cwd = FALSE;
    if ($selected_root = \Drush::bootstrapManager()->getRoot()) {
      if (is_dir($selected_root)) {
        $cwd = getcwd();
        drush_op('chdir', $selected_root);
      }
    }
    if ($alias = drush_get_context('DRUSH_TARGET_SITE_ALIAS')) {
      $site = drush_sitealias_get_record($alias);
      if (!empty($site['site-list'])) {
        $sites = drush_sitealias_resolve_sitelist($site);
        foreach ($sites as $site_name => $site_spec) {
          $result = $this->executeCmd($site_spec, $cmd);
          if (!$result) {
            break;
          }
        }
      }
      else {
        $result = $this->executeCmd($site, $cmd);
      }
    }
    else {
      // Must be a local command.
      $result = (drush_shell_proc_open($cmd) == 0);
    }
    // Restore the cwd if we changed it
    if ($cwd) {
      drush_op('chdir', $selected_root);
    }
    if (!$result) {
      throw new \Exception(dt("Command !command failed.", array('!command' => $cmd)));
    }
    return $result;
  }

  /**
   * Helper function for drush_core_execute: run one shell command
   */
  protected function executeCmd($site, $cmd) {
    if (!empty($site['remote-host'])) {
      // Remote, so execute an ssh command with a bash fragment at the end.
      $exec = drush_shell_proc_build($site, $cmd, TRUE);
      return (drush_shell_proc_open($exec) == 0);
    }
    elseif (!empty($site['root']) && is_dir($site['root'])) {
      return (drush_shell_proc_open('cd ' . drush_escapeshellarg($site['root']) . ' && ' . $cmd) == 0);
    }
    return (drush_shell_proc_open($cmd) == 0);
  }


}
