<?php
namespace Drupal\devel\Commands;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Uuid\Php;
use Drupal\Core\Utility\Token;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\Output;

/**
 * For commands that are parts of modules, Drush expects to find commandfiles in
 * __MODULE__/src/Commands, and the namespace is Drupal/__MODULE__/Commands.
 *
 * In addition to a commandfile like this one, you need to add a drush.services.yml
 * in root of your module like this module does.
 */
class DevelCommands extends DrushCommands {

  protected $token;

  protected $container;

  protected $eventDispatcher;

  protected $moduleHandler;

  public function __construct(Token $token, $container, $eventDispatcher, $moduleHandler) {
    parent::__construct();
    $this->token = $token;
    $this->container = $container;
    $this->eventDispatcher = $eventDispatcher;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   */
  public function getModuleHandler() {
    return $this->moduleHandler;
  }

  /**
   * @return mixed
   */
  public function getEventDispatcher() {
    return $this->eventDispatcher;
  }

  /**
   * @return mixed
   */
  public function getContainer() {
    return $this->container;
  }

  /**
   * @return Token
   */
  public function getToken() {
    return $this->token;
  }

  /**
   * Uninstall, and Install modules.

   * @command devel:reinstall
   * @param $modules A comma-separated list of module names.
   * @aliases dre,devel-reinstall
   * @allow-additional-options pm-uninstall,pm-enable
   */
  public function reinstall($modules) {
    $modules = StringUtils::csvToArray($modules);

    $modules_str = implode(',', $modules);
    drush_invoke_process('@self', 'pm:uninstall', [$modules_str], []);
    drush_invoke_process('@self', 'pm:enable', [$modules_str], []);
  }

  /**
   * List implementations of a given hook and optionally edit one.
   *
   * @command devel:hook
   * @param $hook The name of the hook to explore.
   * @param $implementation The name of the implementation to edit. Usually omitted.
   * @usage devel-hook cron
   *   List implementations of hook_cron().
   * @aliases fnh,fn-hook,hook,devel-hook
   * @optionset_get_editor
   */
  function hook($hook, $implementation) {
    // Get implementations in the .install files as well.
    include_once './core/includes/install.inc';
    drupal_load_updates();
    $info = $this->codeLocate($implementation . "_$hook");
    $exec = drush_get_editor();
    drush_shell_exec_interactive($exec, $info['file']);
  }

  /**
   * @hook interact hook
   */
  public function hookInteract(Input $input, Output $output) {
    if (!$input->getArgument('implementation')) {
      if ($hook_implementations = $this->getModuleHandler()->getImplementations($input->getArgument('hook'))) {
        if (!$choice = $this->io()->choice('Enter the number of the hook implementation you wish to view.', array_combine($hook_implementations, $hook_implementations))) {
          throw new UserAbortException();
        }
        $input->setArgument('implementation', $choice);
      }
      else {
        throw new \Exception(dt('No implementations'));
      }
    }
  }

  /**
   * List implementations of a given event and optionally edit one.
   *
   * @command devel:event
   * @param $event The name of the event to explore. If omitted, a list of events is shown.
   * @param $implementation The name of the implementation to show. Usually omitted.
   * @usage devel-event
   *   Pick a Kernel event, then pick an implementation, and then view its source code.
   * @usage devel-event kernel.terminate
   *   Pick a terminate subscribers implementation and view its source code.
   * @aliases fne,fn-event,event
   */
  function event($event, $implementation) {
    $info= $this->codeLocate($implementation);
    $exec = drush_get_editor();
    drush_shell_exec_interactive($exec, $info['file']);
  }

  /**
   * @hook interact devel:event
   */
  public function interactEvent(Input $input, Output $output) {
    $dispatcher = $this->getEventDispatcher();
    if (!$input->getArgument('event')) {
      // @todo Expand this list.
      $events = array('kernel.controller', 'kernel.exception', 'kernel.request', 'kernel.response', 'kernel.terminate', 'kernel.view');
      $events = array_combine($events, $events);
      if (!$event = $this->io()->choice('Enter the event you wish to explore.', $events)) {
        throw new UserAbortException();
      }
      $input->setArgument('event', $event);
    }
    if ($implementations = $dispatcher->getListeners($event)) {
      foreach ($implementations as $implementation) {
        $callable = get_class($implementation[0]) . '::' . $implementation[1];
        $choices[$callable] = $callable;
      }
      if (!$choice = $this->io()->choice('Enter the number of the implementation you wish to view.', $choices)) {
        throw new UserAbortException();
      }
      $input->setArgument('implementation', $choice);
    }
    else {
      throw new \Exception(dt('No implementations.'));
    }
  }

  /**
   * List available tokens.
   *
   * @command devel:token
   * @aliases token,devel-token
   * @field-labels
   *   group: Group
   *   token: Token
   *   name: Name
   * @default-fields group,token,name
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
  public function token($options = ['format' => 'table']) {
    $all = $this->getToken()->getInfo();
    foreach ($all['tokens'] as $group => $tokens) {
      foreach ($tokens as $key => $token) {
        $rows[] = [
          'group' => $group,
          'token' => $key,
          'name' => $token['name'],
        ];
      }
    }
    return new RowsOfFields($rows);
  }

  /**
   * Generate a UUID.
   *
   * @command devel:uuid
   * @aliases uuid,devel-uuid
   * @usage drush devel-uuid
   *   Outputs a Universally Unique Identifier.
   *
   * @return string
   */
  public function uuid() {
    $uuid = new Php();
    return $uuid->generate();
  }


  /**
   * Get source code line for specified function or method.
   */
  function codeLocate($function_name) {
    // Get implementations in the .install files as well.
    include_once './core/includes/install.inc';
    drupal_load_updates();

    if (strpos($function_name, '::') === FALSE) {
      if (!function_exists($function_name)) {
        throw new \Exception(dt('Function not found'));
      }
      $reflect = new \ReflectionFunction($function_name);
    }
    else {
      list($class, $method) = explode('::', $function_name);
      if (!method_exists($class, $method)) {
        throw new \Exception(dt('Method not found'));
      }
      $reflect = new \ReflectionMethod($class, $method);
    }
    return array('file' => $reflect->getFileName(), 'startline' => $reflect->getStartLine(), 'endline' => $reflect->getEndLine());

  }

  /**
   * Get a list of available container services.
   *
   * @command devel:services
   * @param $prefix A prefix to filter the service list by.
   * @aliases devel-container-services,dcs,devel-services
   * @usage drush devel-services
   *   Gets a list of all available container services
   * @usage drush dcs plugin.manager
   *   Get all services containing "plugin.manager"
   *
   * @return array
   */
  public function services($prefix = NULL, $options = ['format' => 'yaml']) {
    $container = $this->getContainer();

    // Get a list of all available service IDs.
    $services = $container->getServiceIds();

    // If there is a prefix, try to find matches.
    if (isset($prefix)) {
      $services = preg_grep("/$prefix/", $services);
    }

    if (empty($services)) {
      throw new \Exception(dt('No container services found.'));
    }

    sort($services);
    return $services;
  }
}