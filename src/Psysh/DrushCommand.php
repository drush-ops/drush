<?php
/**
 * @file
 * Contains \Drush\Psysh\DrushCommand.
 *
 * DrushCommand is a PsySH proxy command which accepts a Drush command config
 * array and tries to build an appropriate PsySH command for it.
 */

namespace Drush\Psysh;

use Consolidation\AnnotatedCommand\AnnotatedCommand;
use Psy\Command\Command as BaseCommand;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Main Drush command.
 */
class DrushCommand extends BaseCommand
{

    /**
     * @var \Consolidation\AnnotatedCommand\AnnotatedCommand
     */
    private $command;

    /**
     * DrushCommand constructor.
     *
     * @param \Consolidation\AnnotatedCommand\AnnotatedCommand $command
     *   Original (annotated) Drush command.
     */
    public function __construct(AnnotatedCommand $command)
    {
        $this->command = $command;
        parent::__construct();
    }

    /**
     * Get the namespace of this command.
     */
    public function getNamespace()
    {
        $parts = explode(':', $this->getName());
        return count($parts) >= 2 ? array_shift($parts) : 'global';
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName($this->command->getName())
            ->setAliases($this->command->getAliases())
            ->setDefinition($this->command->getDefinition())
            ->setDescription($this->command->getDescription())
            ->setHelp($this->buildHelpFromCommand());
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $args = $input->getArguments();
        $first = array_shift($args);

        // If the first argument is an alias, assign the next argument as the
        // command.
        if (strpos($first, '@') === 0) {
            $alias = $first;
            $command = array_shift($args);
        } else {
            // Otherwise, default the alias to '@self' and use the first argument as the
            // command.
            $alias = '@self';
            $command = $first;
        }

        $options = array_diff_assoc($input->getOptions(), $this->getDefinition()->getOptionDefaults());
        // Force the 'backend' option to TRUE.
        $options['backend'] = true;

        $return = drush_invoke_process($alias, $command, array_values($args), $options, ['interactive' => true]);

        if (($return['error_status'] > 0) && !empty($return['error_log'])) {
            foreach ($return['error_log'] as $error_type => $errors) {
                $output->write($errors);
            }
            // Add a newline after so the shell returns on a new line.
            $output->writeln('');
        } else {
            $output->page(drush_backend_get_result());
        }
    }

    /**
     * Build a command help from the Drush configuration array.
     *
     * Currently it's a word-wrapped description, plus any examples provided.
     *
     * @return string
     *   The help string.
     */
    protected function buildHelpFromCommand()
    {
        $help = wordwrap($this->command->getDescription());

        $examples = [];
        foreach ($this->command->getExampleUsages() as $ex => $def) {
            // Skip empty examples and things with obvious pipes...
            if (($ex === '') || (strpos($ex, '|') !== false)) {
                continue;
            }

            $ex = preg_replace('/^drush\s+/', '', $ex);
            $examples[$ex] = $def;
        }

        if (!empty($examples)) {
            $help .= "\n\ne.g.";

            foreach ($examples as $ex => $def) {
                $help .= sprintf("\n<return>// %s</return>\n", wordwrap(OutputFormatter::escape($def), 75, "</return>\n<return>// "));
                $help .= sprintf("<return>>>> %s</return>\n", OutputFormatter::escape($ex));
            }
        }

        return $help;
    }
}
