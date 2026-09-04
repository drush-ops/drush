<?php

namespace Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Exec\ExecTrait;

/**
 * Run these commands using the --include option - e.g. `drush --include=/path/to/drush/examples xkcd`
 *
 * For an example of a Drush extension with tests for Drush:
 * - https://github.com/drush-ops/example-drush-extension
 */

class XkcdCommands extends DrushCommands
{
    use ExecTrait;

    /**
     * Retrieve and display xkcd cartoons.
     */
    #[CLI\Command(name: 'xkcd:fetch', aliases: ['xkcd'])]
    #[CLI\Option(name: 'search', description: 'Retrieve the cartoons matching an index number, keyword search or "random"')]
    #[CLI\Option(name: 'image-viewer', description: 'Command to use to view images (e.g. xv, firefox). Defaults to "display" (from ImageMagick).')]
    #[CLI\Usage(name: 'drush xkcd sandwich', description: 'Retrieve and display cartoons about sandwiches.')]
    #[CLI\Usage(name: 'drush xkcd 123 --image-viewer=eog', description: 'Retrieve and display cartoon #123 in eog.')]
    #[CLI\Usage(name: 'drush xkcd random --image-viewer=firefox', description: 'Retrieve and display a random cartoon in Firefox.')]
    public function fetch($search = null, $options = ['image-viewer' => 'open'])
    {
        $this->doFetch($search, $options);
    }

    /**
     * @param $search
     * @param array $options
     * @throws \Exception
     */
    protected function doFetch($search, array $options): void
    {
        if (empty($search)) {
            $this->startBrowser('http://xkcd.com');
        } elseif (is_numeric($search)) {
            $this->startBrowser('http://xkcd.com/' . $search);
        } elseif ($search == 'random') {
            $xkcd_response = @json_decode(file_get_contents('http://xkcd.com/info.0.json'));
            if (!empty($xkcd_response->num)) {
                $this->startBrowser('http://xkcd.com/' . rand(1, $xkcd_response->num));
            }
        }
    }
}
