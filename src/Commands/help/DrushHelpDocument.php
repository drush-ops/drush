<?php

namespace Drush\Commands\help;

use Consolidation\AnnotatedCommand\Help\HelpDocument;
use Symfony\Component\Console\Command\Command;

class DrushHelpDocument extends HelpDocument {

  /**
   * @inheritdoc
   */
  public function generateBaseHelpDom(Command $command)
  {
    // Global options should not appear in our help output.
    $command->setApplication(NULL);

    return parent::generateBaseHelpDom($command);
  }
}