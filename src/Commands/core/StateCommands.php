<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinAwareTrait;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drupal\Core\State\StateInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class StateCommands extends DrushCommands implements StdinAwareInterface
{
    use StdinAwareTrait;

    const GET = 'state:get';
    const SET = 'state:set';
    const DELETE = 'state:delete';

    public function __construct(protected StateInterface $state)
    {
    }

    public static function create(ContainerInterface $container): self
    {
        $commandHandler = new static(
            $container->get('state')
        );

        return $commandHandler;
    }

    public function getState(): StateInterface
    {
        return $this->state;
    }

    /**
     * Display a state value.
     */
    #[CLI\Command(name: self::GET, aliases: ['sget', 'state-get'])]
    #[CLI\Argument(name: 'key', description: 'The key name.')]
    #[CLI\Usage(name: 'drush state:get system.cron_last', description: 'Displays last cron run timestamp')]
    #[CLI\Usage(name: 'drush state:get drupal_css_cache_files --format=yaml', description: 'Displays an array of css files in yaml format.')]
    public function get(string $key, $options = ['format' => 'string']): PropertyList
    {
        $value = $this->getState()->get($key);
        return new PropertyList([$key => $value]);
    }

    /**
     * Set a state value.
     */
    #[CLI\Command(name: self::SET, aliases: ['sset', 'state-set'])]
    #[CLI\Argument(name: 'key', description: 'The state key, for example: <info>system.cron_last</info>.')]
    #[CLI\Argument(name: 'value', description: 'The value to assign to the state key. Use <info>-</info> to read from Stdin.')]
    #[CLI\Option(name: 'input-format', description: 'Type for the value. Other recognized values: string, integer, float, boolean, json, yaml.')]
    #[CLI\Usage(name: 'drush sset system.maintenance_mode 1 --input-format=integer', description: 'Put site into Maintenance mode.')]
    #[CLI\Usage(name: 'drush state:set system.cron_last 1406682882 --input-format=integer', description: 'Sets a timestamp for last cron run.')]
    #[CLI\Usage(name: 'php -r "print json_encode(array(\'drupal\', \'simpletest\'));"  | drush state-set --input-format=json foo.name -', description: 'Set a key to a complex value (e.g. array)')]
    #[CLI\Usage(name: 'drush state:set twig_debug TRUE', description: 'Enable the Twig debug mode (since Drupal 10.1)')]
    #[CLI\Usage(name: 'drush state:set twig_autoreload TRUE', description: 'Enable Twig auto reload (since Drupal 10.1)')]
    #[CLI\Usage(name: 'drush state:set twig_cache_disable TRUE', description: 'Disable the Twig, page, render and dynamic page caches (since Drupal 10.1)')]
    public function set(string $key, $value, $options = ['input-format' => 'auto']): void
    {

        if (!isset($value)) {
            throw new \Exception(dt('No state value specified.'));
        }

        // Special flag indicating that the value has been passed via STDIN.
        if ($value === '-') {
            $value = $this->stdin()->contents();
        }

        // If the value is a string (usual case, unless we are called from code),
        // then format the input.
        if (is_string($value)) {
            $value = $this->format($value, $options['input-format']);
        }

        $this->getState()->set($key, $value);
    }

    /**
     * Delete a state entry.
     */
    #[CLI\Command(name: self::DELETE, aliases: ['sdel', 'state-delete'])]
    #[CLI\Argument(name: 'key', description: 'The state key, for example <info>system.cron_last</info>.')]
    #[CLI\Usage(name: 'drush state:del system.cron_last', description: 'Delete state entry for system.cron_last.')]
    public function delete(string $key): void
    {
        $this->getState()->delete($key);
    }

  /*
   * Cast a value according to the provided format
   *
   * @param mixed $value.
   * @param string $format
   *   Allowed values: auto, integer, float, bool, boolean, json, yaml.
   *
   * @return $value
   *  The value, casted as needed.
   */
    public static function format(mixed $value, string $format): mixed
    {
        if ($format == 'auto') {
            if (is_numeric($value)) {
                $value += 0; // http://php.net/manual/en/function.is-numeric.php#107326
                $format = gettype($value);
            } elseif (($value == 'TRUE') || ($value == 'FALSE')) {
                $format = 'bool';
            }
        }

        // Now, we parse the object.
        switch ($format) {
            case 'integer':
                $value = (int)$value;
                break;
            // from: http://php.net/gettype
            // for historical reasons "double" is returned in case of a float, and not simply "float"
            case 'double':
            case 'float':
                $value = (float)$value;
                break;
            case 'bool':
            case 'boolean':
                if ($value == 'TRUE') {
                     $value = true;
                } elseif ($value == 'FALSE') {
                    $value = false;
                } else {
                    $value = (bool)$value;
                }
                break;
            case 'json':
                $value = json_decode($value, true);
                break;
            case 'yaml':
                $value = Yaml::parse($value);
                break;
        }
        return $value;
    }
}
