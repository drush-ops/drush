<?php

namespace Drush\Commands\help;

use Consolidation\AnnotatedCommand\Help\HelpDocument;
use Symfony\Component\Console\Command\Command;

class DrushHelpDocument extends HelpDocument
{
    /**
     * @inheritdoc
     */
    public function generateBaseHelpDom(Command $command): \DomDocument
    {
        // Global options should not appear in our help output.
        $command->setApplication(null);

        return parent::generateBaseHelpDom($command);
    }

    protected function alterHelpDocument(Command $command, \DomDocument $dom): \DomDocument
    {
        return parent::alterHelpDocument($command, $dom);
    }
}
