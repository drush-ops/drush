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
    #[CLI\Option(name: 'search', description: 'Optional argument to retrieve the cartoons matching an index number, keyword search or "random". If omitted the latest cartoon will be retrieved.')]
    #[CLI\Option(name: 'image-viewer', description: 'Command to use to view images (e.g. xv, firefox). Defaults to "display" (from ImageMagick).')]
    #[CLI\Option(name: 'google-custom-search-api-key', description: 'Google Custom Search API Key, available from https://code.google.com/apis/console/. Default key limited to 100 queries/day globally.')]
    #[CLI\Usage(name: 'drush xkcd', description: 'Retrieve and display the latest cartoon.')]
    #[CLI\Usage(name: 'drush xkcd sandwich', description: 'Retrieve and display cartoons about sandwiches.')]
    #[CLI\Usage(name: 'drush xkcd 123 --image-viewer=eog', description: 'Retrieve and display cartoon #123 in eog.')]
    #[CLI\Usage(name: 'drush xkcd random --image-viewer=firefox', description: 'Retrieve and display a random cartoon in Firefox.')]
    public function fetch($search = null, $options = ['image-viewer' => 'open', 'google-custom-search-api-key' => 'AIzaSyDpE01VDNNT73s6CEeJRdSg5jukoG244ek'])
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
        } else {
            // This uses an API key with a limited number of searches per.
            $search_response = @json_decode(file_get_contents('https://www.googleapis.com/customsearch/v1?key=' . $options['google-custom-search-api-key'] . '&cx=012652707207066138651:zudjtuwe28q&q=' . $search));
            if (!empty($search_response->items)) {
                foreach ($search_response->items as $item) {
                    $this->startBrowser($item->link);
                }
            } else {
                throw new \Exception(dt('The search failed or produced no results.'));
            }
        }
    }
}
