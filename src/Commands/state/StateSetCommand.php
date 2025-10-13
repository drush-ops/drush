<?php

declare(strict_types=1);

namespace Drush\Commands\state;

use Drupal\Core\State\StateInterface;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

use function Drush\Commands\core\gettype;

#[AsCommand(
    name: self::NAME,
    description: 'Set a state value.',
    aliases: ['sset', 'state-set']
)]
final class StateSetCommand extends Command
{
    use AutowireTrait;

    public const NAME = 'state:set';

    public function __construct(
        protected StateInterface $state
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('key', InputArgument::REQUIRED, 'The state key, for example: <info>system.cron_last</info>.')
            ->addArgument('value', InputArgument::REQUIRED, 'The value to assign to the state key. Use <info>-</info> to read from Stdin.')
            ->addOption('input-format', null, InputOption::VALUE_REQUIRED, 'Type for the value. Other recognized values: string, integer, float, boolean, json, yaml.', 'auto')
            ->addUsage('sset system.maintenance_mode 1 --input-format=integer')
            ->addUsage('state:set system.cron_last 1406682882 --input-format=integer')
            ->addUsage('php -r "print json_encode(array(\'drupal\', \'simpletest\'));"  | drush state-set --input-format=json foo.name -')
            ->addUsage('state:set twig_debug TRUE')
            ->addUsage('state:set twig_autoreload TRUE')
            ->addUsage('state:set twig_cache_disable TRUE')
            ->addUsage('state:set disable_rendered_output_cache_bins TRUE');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = $input->getArgument('key');
        $value = $input->getArgument('value');
        $inputFormat = $input->getOption('input-format');

        // Special flag indicating that the value has been passed via STDIN.
        if ($value === '-') {
            // See https://github.com/symfony/symfony/issues/37835#issuecomment-674386588.
            // If testing this will get input added by `CommandTester::setInputs` method.
            $inputStream = ($input instanceof StreamableInputInterface) ? $input->getStream() : STDIN;
            $value = stream_get_contents($inputStream);
        }

        // If the value is a string (usual case, unless we are called from code),
        // then format the input.
        if (is_string($value)) {
            $value = $this->format($value, $inputFormat);
        }

        $this->state->set($key, $value);

        return self::SUCCESS;
    }

    /**
     * Cast a value according to the provided format
     *
     * @param mixed $value.
     * @param string $format
     *   Allowed values: auto, integer, float, bool, boolean, json, yaml.
     *
     * @return mixed
     *   The value, casted as needed.
     */
    public static function format(mixed $value, string $format): mixed
    {
        if ($format === 'auto') {
            if (is_numeric($value)) {
                $value += 0; // http://php.net/manual/en/function.is-numeric.php#107326
                $format = \gettype($value);
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
