<?php

namespace Drush\Commands;

use Drush\Exec\ExecTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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

    protected function configure(): void
    {
        $this
        ->addArgument('search', mode: InputArgument::OPTIONAL, description: 'Optional argument to retrieve the cartoons matching an index number, keyword search or "random". If omitted the latest cartoon will be retrieved.')
        ->addOption('image-viewer', null, InputOption::VALUE_REQUIRED, 'Command to use to view images (e.g. xv, firefox). Defaults to "display" (from ImageMagick).', 'open')
        ->addUsage('drush xkcd sandwich')
        ->addUsage('drush xkcd 123 --image-viewer=eog')
        ->addUsage('drush xkcd random --image-viewer=firefox');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->doFetch($input->getArgument('search'), $input->getOptions());
        return self::SUCCESS;
    }

    protected function doFetch($search, array $options): void
    {
        if (empty($search)) {
            $this->startBrowser(uri: 'https://xkcd.com', browser: true);
        } elseif (is_numeric($search)) {
            $this->startBrowser(uri: 'https://xkcd.com/' . $search, browser: true);
        } elseif ($search == 'random') {
            $xkcd_response = @json_decode(file_get_contents('https://xkcd.com/info.0.json'));
            if (!empty($xkcd_response->num)) {
                $this->startBrowser(uri: 'https://xkcd.com/' . rand(1, $xkcd_response->num), browser: true);
            }
        }
    }
}
