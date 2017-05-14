<?php
namespace Drush\Commands;

/**
 * Run these commands using the --include option - e.g. `drush --include=/path/to/drush/examples xkcd`
 */

class XkcdCommands extends DrushCommands {

  /**
   * Retrieve and display xkcd cartoons.
   *
   * @command xkcd-fetch
   * @param $search Optional argument to retrieve the cartoons matching an index number, keyword search or "random". If omitted the latest cartoon will be retrieved.
   * @option image-viewer Command to use to view images (e.g. xv, firefox). Defaults to "display" (from ImageMagick).
   * @option google-custom-search-api-key Google Custom Search API Key, available from https://code.google.com/apis/console/. Default key limited to 100 queries/day globally.
   * @usage drush xkcd
   *   Retrieve and display the latest cartoon.
   * @usage drush xkcd sandwich
   *   Retrieve and display cartoons about sandwiches.
   * @usage drush xkcd 123 --image-viewer=eog
   *   Retrieve and display cartoon #123 in eog.
   * @usage drush xkcd random --image-viewer=firefox
   *   Retrieve and display a random cartoon in Firefox.
   * @aliases @xkcd
   */
  public function fetch($search = NULL, $options = ['image-viewer' => 'open', 'google-custom-search-api-key' => NULL]) {
    if (empty($search)) {
      drush_start_browser('http://xkcd.com');
    }
    elseif (is_numeric($search)) {
      drush_start_browser('http://xkcd.com/' . $search);
    }
    elseif ($search == 'random') {
      $xkcd_response = @json_decode(file_get_contents('http://xkcd.com/info.0.json'));
      if (!empty($xkcd_response->num)) {
        drush_start_browser('http://xkcd.com/' . rand(1, $xkcd_response->num));
      }
    }
    else {
      // This uses an API key with a limited number of searches per.
      $search_response = @json_decode(file_get_contents('https://www.googleapis.com/customsearch/v1?key=' . drush_get_option('google-custom-search-api-key', 'AIzaSyDpE01VDNNT73s6CEeJRdSg5jukoG244ek') . '&cx=012652707207066138651:zudjtuwe28q&q=' . $search));
      if (!empty($search_response->items)) {
        foreach ($search_response->items as $item) {
          drush_start_browser($item->link);
        }
      }
      else {
        throw new \Exception(dt('The search failed or produced no results.'));
      }
    }
  }
}
