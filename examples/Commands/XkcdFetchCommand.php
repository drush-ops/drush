<?php

namespace Drush\Commands;

use Drush\Exec\ExecTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Run these commands using the --include option - e.g. `drush --include=/path/to/drush/examples xkcd`
 */

#[AsCommand(
  name: 'xkcd:fetch',
  description: 'Retrieve and display xkcd cartoons.',
  aliases: ['xkcd'],
)]
final class XkcdFetchCommand extends Command
{
  use ExecTrait;

  protected function configure(): void {
    $this
      ->addOption('search', null, InputOption::VALUE_REQUIRED, 'Optional argument to retrieve the cartoons matching an index number, keyword search or "random". If omitted the latest cartoon will be retrieved.')
      ->addOption('image-viewer', null, InputOption::VALUE_REQUIRED, 'Command to use to view images (e.g. xv, firefox). Defaults to "display" (from ImageMagick).', 'open')
      ->addOption('google-custom-search-api-key', null, InputOption::VALUE_REQUIRED, 'Google Custom Search API Key, available from https://code.google.com/apis/console/. Default key limited to 100 queries/day globally.', 'AIzaSyDpE01VDNNT73s6CEeJRdSg5jukoG244ek')
      ->addUsage('drush xkcd sandwich')
      ->addUsage('drush xkcd 123 --image-viewer=eog')
      ->addUsage('drush xkcd random --image-viewer=firefox');
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $this->doFetch($input->getOption('search'), $input->getOptions());
    return self::SUCCESS;
  }

  protected function doFetch($search, array $options): void
  {
    if (empty($search)) {
      $this->startBrowser(uri: 'http://xkcd.com', browser: true);
    } elseif (is_numeric($search)) {
      $this->startBrowser(uri: 'http://xkcd.com/' . $search, browser: true);
    } elseif ($search == 'random') {
      $xkcd_response = @json_decode(file_get_contents('http://xkcd.com/info.0.json'));
      if (!empty($xkcd_response->num)) {
        $this->startBrowser(uri: 'http://xkcd.com/' . rand(1, $xkcd_response->num), browser: true);
      }
    } else {
      // This uses an API key with a limited number of searches per.
      $search_response = @json_decode(file_get_contents('https://www.googleapis.com/customsearch/v1?key=' . $options['google-custom-search-api-key'] . '&cx=012652707207066138651:zudjtuwe28q&q=' . $search));
      if (!empty($search_response->items)) {
        foreach ($search_response->items as $item) {
          $this->startBrowser(uri: $item->link, browser: true);
        }
      } else {
        throw new \Exception(dt('The search failed or produced no results.'));
      }
    }
  }
}
