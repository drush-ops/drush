<?php

namespace Drush\Commands\help;

use Consolidation\AnnotatedCommand\Help\HelpDocument;
use Symfony\Component\Console\Command\Command;

class DrushHelpDocument extends HelpDocument
{

    /**
     * @inheritdoc
     */
    public function generateBaseHelpDom(Command $command)
    {
        // Global options should not appear in our help output.
        $command->setApplication(null);

        return parent::generateBaseHelpDom($command);
    }

    protected function alterHelpDocument(Command $command, \DomDocument $dom)
    {
        return parent::alterHelpDocument($command, $dom);
    }
}
