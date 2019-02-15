<?php
namespace Drush\Commands\example_site_wide_command;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Drush\Commands\DrushCommands;

use Drush\Style\DrushStyle;
use Drush\Utils\StringUtils;

/**
 * Site-wide commands for the System-Under-Test site
 */

class NestedSutCommands extends DrushCommands
{
    /**
     * Show a fabulous picture.
     *
     * @command sut:nested
     * @hidden
     */
    public function example()
    {
        $this->logger()->notice(dt("This is an example site-wide command committed to the repository in the SUT nested inside a custom/example-site-wide-command directory."));
    }
}
