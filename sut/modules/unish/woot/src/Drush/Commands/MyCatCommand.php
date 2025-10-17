<?php

/**
 * An example of an invokable command (require Symfony 7.4+).
 */

declare(strict_types=1);

namespace Drupal\woot\Drush\Commands;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: self::NAME,
  description: 'This command will concatenate two parameters.',
  aliases: ['my-cat'],
  help: 'If the --flip flag is provided, then the result is the concatenation of two and one.',
//  usages: ['bet alpha --flip'],
)]
final class MyCatCommand {

  const NAME = 'my:cat';

  public function __invoke(
    OutputInterface $output,
    #[Argument('The first parameter.')] string $one,
    #[Argument('The second parameter.')] string $two,
    #[Option('Whether or not the second parameter should come first in the result')] bool $flip = FALSE,
  ): int
  {
    if ($flip) {
      $output->writeln("{$two}{$one}");
    }
    else {
      $output->writeln("{$one}{$two}");
    }
    return Command::SUCCESS;
  }
}
