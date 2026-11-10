<?php

declare(strict_types=1);

namespace Drush\Commands\state;

use Drupal\Core\Render\Markup;
use Drupal\Core\State\StateInterface;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: self::NAME,
    description: 'Set a state value.',
    aliases: ['sset', 'state-set'],
    usages: [
        'state:set system.maintenance_mode 1 --input-format=integer',
        'state:set system.cron_last 1406682882 --input-format=integer',
        'php -r "print json_encode(array(\'drupal\', \'simpletest\'));"  | drush state:set --input-format=json foo.name -',
        'state:set twig_debug TRUE',
        'state:set twig_autoreload TRUE',
        'state:set twig_cache_disable TRUE',
        'state:set disable_rendered_output_cache_bins TRUE',
    ],
)]
final class StateSetCommand
{
    use AutowireTrait;
    use StateTrait;

    public const string NAME = 'state:set';

    /**
     * Accepted values of --input-format.
     */
    public const array INPUT_FORMATS = ['auto', 'string', 'integer', 'float', 'boolean', 'json', 'yaml'];

    public function __construct(
        protected StateInterface $state,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('The state key, for example system.cron_last.')]
        string $key,
        #[Argument('The value to assign to the state key. Use - to read from stdin.')]
        string $value,
        #[Option('Type for the value: auto, string, integer, float, boolean, json or yaml. Auto detects numbers and TRUE/FALSE.', suggestedValues: self::INPUT_FORMATS)]
        string $inputFormat = 'auto',
    ): int {
        if (!in_array($inputFormat, self::INPUT_FORMATS, true)) {
            throw new \InvalidArgumentException((string) $this->t('Unknown input format: @format. Allowed values: @allowed', [
                '@format' => $inputFormat,
                '@allowed' => implode(', ', self::INPUT_FORMATS),
            ]));
        }

        // Special flag indicating that the value has been passed via stdin.
        if ($value === '-') {
            // See https://github.com/symfony/symfony/issues/37835#issuecomment-674386588.
            // Under CommandTester this reads what setInputs() provided.
            $stream = $input instanceof StreamableInputInterface ? $input->getStream() : null;
            $value = stream_get_contents($stream ?? STDIN);
        }

        $value = self::format($value, $inputFormat);
        $this->state->set($key, $value);

        // The rendered value is console text, not HTML, so keep t() from
        // escaping its quotes.
        $rendered = is_scalar($value) ? var_export($value, true) : json_encode($value, JSON_THROW_ON_ERROR);
        $io = new SymfonyStyle($input, $output);
        $io->success((string) $this->t('Set state entry @key to @value', [
            '@key' => $key,
            '@value' => Markup::create($rendered),
        ]));

        return Command::SUCCESS;
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
