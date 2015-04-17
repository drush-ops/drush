<?php

/**
 * @file
 *   An Adapter class that allows Drush to call Symfony Console commands.
 */

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\EventDispatcher\EventDispatcher;

use Drupal\AppConsole\Command\Command;
use Drupal\AppConsole\Console\Shell;
use Drupal\AppConsole\Console\Application;
use Drupal\AppConsole\Command\Helper\ShellHelper;
use Drupal\AppConsole\Command\Helper\KernelHelper;
use Drupal\AppConsole\Command\Helper\DialogHelper;
use Drupal\AppConsole\Command\Helper\RegisterCommandsHelper;
use Drupal\AppConsole\Utils\StringUtils;
use Drupal\AppConsole\Utils\Validators;
use Drupal\AppConsole\Command\Helper\TranslatorHelper;
use Drupal\AppConsole\UserConfig;
use Drupal\AppConsole\Command\Helper\DrupalAutoloadHelper;
use Drupal\AppConsole\EventSubscriber\ShowGeneratedFilesListener;
use Drupal\AppConsole\EventSubscriber\ShowWelcomeMessageListener;
use Drupal\AppConsole\Command\Helper\MessageHelper;
use Drupal\AppConsole\Command\Helper\ChainCommandHelper;
use Drupal\AppConsole\EventSubscriber\CallCommandListener;
use Drupal\AppConsole\EventSubscriber\ShowCompletedMessageListener;
use Drupal\AppConsole\EventSubscriber\ValidateDependenciesListener;

function consoleadapter_find_console()
{
    // Once the type of the Drupal Console has been updated, we will find it in our
    // vendor directory.  For now, it is mis-installed to 'modules/console'.
    $locations = array(
        drush_get_context('DRUSH_VENDOR_PATH', '') . '/drupal/console/',
        DRUSH_BASE_PATH . '/modules/console/',
    );
    foreach ($locations as $path) {
        if (is_dir($path)) {
            return $path;
        }
    }
    return FALSE;
}

function consoleadapter_preflight() {
    // Find the Drupal App Console root; fail if it is not available.
    $consoleRoot = consoleadapter_find_console();
    if (!$consoleRoot) {
        return drush_set_error('DRUSH_NO_CONSOLE_APP', dt("Could not find Drupal Console Application"));
    }

    // Now we will initialize and run the Drupal App Console
    $config = new UserConfig();

    $translatorHelper = new TranslatorHelper();
    $translatorHelper->loadResource($config->get('application.language'), $consoleRoot);

    $application = new Application($config);
    $application->setDirectoryRoot($consoleRoot);

    $helpers = [
        'kernel' => new KernelHelper(),
        'shell' => new ShellHelper(new Shell($application)),
        'dialog' => new DialogHelper(),
        'register_commands' => new RegisterCommandsHelper($application),
        'stringUtils' => new StringUtils(),
        'validators' => new Validators(),
        'translator' => $translatorHelper,
        'drupal-autoload' => new DrupalAutoloadHelper(),
        'message' => new MessageHelper($translatorHelper),
        'chain' => new ChainCommandHelper(),
    ];

    $application->addHelpers($helpers);

    $dispatcher = new EventDispatcher();
    $dispatcher->addSubscriber(new ValidateDependenciesListener());
    $dispatcher->addSubscriber(new ShowWelcomeMessageListener());
    $dispatcher->addSubscriber(new ShowGeneratedFilesListener());
    $dispatcher->addSubscriber(new CallCommandListener());
    $dispatcher->addSubscriber(new ShowCompletedMessageListener());

    $application->setDispatcher($dispatcher);
    $application->setDefaultCommand('list');

    consoleadapter_add_global_commands($application);

    drush_set_context('CONSOLE_APPLICATION', $application);
}

//    $registerCommandsHelper->getCommands()
//    $consoleCommands = $registerCommandsHelper->getConsoleCommands();
//    $customCommands = $registerCommandsHelper->getCustomCommands();


function consoleadapter_add_global_commands($application) {
    $registerCommandsHelper = $application->getHelperSet()->get('register_commands');
    $items = consoleadapter_convert_console_commands_to_drush($registerCommandsHelper->getConsoleCommands());
    drush_set_context('CONSOLE_APPLICATION_COMMANDS', $items);
}

function consoleadapter_add_module_commands($root) {
    $application = drush_get_context('CONSOLE_APPLICATION');
    $kernel = $application->bootstrap($root);
    $commands = drush_get_context('CONSOLE_APPLICATION_COMMANDS');
    // Add commands defined by Drupal modules
    $registerCommandsHelper = $application->getHelperSet()->get('register_commands');
    $items = consoleadapter_convert_console_commands_to_drush($registerCommandsHelper->getCustomCommands());
    drush_set_context('CONSOLE_APPLICATION_COMMANDS', array_merge($commands, $items));

    return $kernel;
}

function consoleadapter_callback() {
    // Get the application object
    $application = drush_get_context('CONSOLE_APPLICATION');
    // Recover our command record
    $command = drush_get_command();

    // Build the command line arguments for the redispatch to the Console application
    // TODO: if we wrote our own version of ArgvInput, we could pass $drush_args
    // and $command_options to it directly, and avoid having to join-and-reparse
    // the args and options.
    $argv[] = 'console';
    $argv[] = $command['console-command-name'];
    $drush_args = drush_get_arguments();
    array_shift($drush_args);
    $command_options = drush_get_options_for_command($command);
    $options = array();
    foreach ($command_options as $key => $value) {
        $option = "--$key";
        if (!$value) {
            $option .= '=0';
        }
        elseif ($value !== TRUE) {
            $option .= "=$value";
        }
        $options[] = $option;
    }
    $argv = array_merge($argv, $drush_args, $options);

    // Console exits, so we'd better tell the shutdown function that that's okay.
    drush_set_context("DRUSH_EXECUTION_COMPLETED", TRUE);
    $application->run(new ArgvInput($argv));
}

function consoleadapter_convert_console_commands_to_drush($console_command_list) {
    $items = array();
    foreach ($console_command_list as $console_command) {
        $console_command_name = $console_command->getName();
        $drush_name = 'x-' . strtr($console_command_name, ':', '-');
        $definition = $console_command->getDefinition();
        $arguments = array();
        foreach ($definition->getArguments() as $arg) {
            // We also have $arg->getDefault() and $arg->isRequired() that we could use here
            // to create a more complex Drush help record.
            $arguments[$arg->getName()] = $arg->getDescription();
        }
        $options = array();
        foreach ($definition->getOptions() as $opt) {
            // We also have $opt->getDefault() and $opt->isValueOptional() and $opt->getShortcut()
            $options[$opt->getName()] = $opt->getDescription();
        }
        $items[$drush_name] = array(
            // 'console-command' => $console_command,
            'console-command-name' => $console_command_name,
            'description' => $console_command->getDescription(),
            'aliases' => $console_command->getAliases(),
            'arguments' => $arguments,
            'options' => $options,
            'core' => array('8+'),
            'callback' => 'consoleadapter_callback',
            'commandfile' => 'core',
            'path' => FALSE,
            'category' => 'console',
        ) + _drush_command_defaults($drush_name);
    }
    return $items;
}
