<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinAwareTrait;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drupal\Core\State\StateInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Yaml\Yaml;

class StateCommands extends DrushCommands implements StdinAwareInterface
{
    use StdinAwareTrait;

    protected $state;

    public function __construct(StateInterface $state)
    {
        $this->state = $state;
    }

    public function getState(): StateInterface
    {
        return $this->state;
    }

    /**
     * Display a state value.
     *
     * @command state:get
     *
     * @param string $key The key name.
     * @usage drush state:get system.cron_last
     *   Displays last cron run timestamp
     * @usage drush state:get drupal_css_cache_files --format=yaml
     *   Displays an array of css files in yaml format.
     * @aliases sget,state-get
     */
    public function get(string $key, $options = ['format' => 'string']): PropertyList
    {
        $value = $this->getState()->get($key);
        return new PropertyList([$key => $value]);
    }

    /**
     * Set a state value.
     *
     * @command state:set
     *
     * @param string $key The state key, for example: <info>system.cron_last</info>.
     * @param mixed $value The value to assign to the state key. Use <info>-</info> to read from Stdin.
     * @option input-format Type for the value. Other recognized values: string, integer, float, boolean, json, yaml.
     * @usage drush sset system.maintenance_mode 1 --input-format=integer
     *  Put site into Maintenance mode.
     * @usage drush state:set system.cron_last 1406682882 --input-format=integer
     *  Sets a timestamp for last cron run.
     * @usage php -r "print json_encode(array(\'drupal\', \'simpletest\'));"  | drush state-set --input-format=json foo.name -
     *   Set a key to a complex value (e.g. array)
     * @aliases sset,state-set
     */
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
     *
     * @command state:delete
     *
     * @param string $key The state key, for example <info>system.cron_last</info>.
     * @usage drush state:del system.cron_last
     *   Delete state entry for system.cron_last.
     * @aliases sdel,state-delete
     */
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
    public static function format($value, $format)
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
                $value = Yaml::parse($value, false);
                break;
        }
        return $value;
    }
}
