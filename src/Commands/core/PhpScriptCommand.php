<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Component\Serialization\Yaml;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drupal\Core\Command\OutputFormat;
use Drush\Config\DrushConfig;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * Runs a PHP script and prints what it returns.
 *
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Run a php script after a full Drupal bootstrap.',
    aliases: ['scr', 'php-script'],
    usages: [
        'php:script example --script-path=/path/to/scripts:/another/path',
        'php:script -',
        'php:script foo -- apple --cider',
    ],
    help: <<<'HELP'
        A useful alternative to eval command when your php is lengthy or you can't be bothered to figure out bash quoting. If you plan to share a script with others, consider making a full Drush command instead, since that's more self-documenting. Drush provides commandline options to the script via a variable called $extra.

        See https://www.drush.org/latest/examples/helloworld.script for an example script.
        HELP,
)]
final class PhpScriptCommand
{
    use AutowireTrait;

    public const string NAME = 'php:script';

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('Script name and arguments. First argument is script name, remaining are passed to the script.')]
        array $extra = [],
        #[Option('An alternative output format such as json or yaml. By default the returned value prints via var_export().')]
        ?OutputFormat $format = null,
    ): int {

        $script = array_shift($extra);

        if ($script === '-') {
            // See https://github.com/symfony/symfony/issues/37835#issuecomment-674386588.
            // Under CommandTester::setInputs() the stream holds the test input.
            $stream = $input instanceof StreamableInputInterface ? $input->getStream() : null;
            $output->write(stream_get_contents($stream ?? STDIN), false, OutputInterface::OUTPUT_RAW);
            return Command::SUCCESS;
        }

        $return = include $script;
        // include() returns 1 for a script without a return statement.
        // http://us3.php.net/manual/en/function.include.php#example-120
        if ($return === 1) {
            $return = null;
        }

        $result = match ($format) {
            OutputFormat::Json => json_encode($return, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            OutputFormat::Yaml => Yaml::encode($return),
            default => $return === null ? '' : var_export($return, true),
        };
        // Yaml::encode() ends with a newline of its own; writeln adds one.
        $result = rtrim($result, "\n");
        if ($result !== '') {
            $output->writeln($result, OutputInterface::OUTPUT_RAW);
        }

        return is_int($return) ? $return : Command::SUCCESS;
    }
}
