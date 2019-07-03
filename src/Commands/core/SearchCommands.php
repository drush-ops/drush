<?php

namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;

class SearchCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
  use SiteAliasManagerAwareTrait;

  /**
   * Show how many items remain to be indexed out of the total.
   *
   * @command search:status
   * @usage drush search:status
   * @aliases search-status
   *
   * @table-style compact
   * @list-delimiter :
   * @field-labels
   *   remaining: Items not yet indexed
   *   total: Total items
   * @default-fields remaining,total
   * @bootstrap max
   *
   * @return \Consolidation\OutputFormatters\StructuredData\PropertyList
   */
  public function status($options = ['format' => 'table']) {
    list($remaining, $total) = $this->getStatus();
    $data = [
      'remaining' => $remaining,
      'total' => $total,
    ];
    return new PropertyList($data);
  }

  /**
   * Index the remaining search items without wiping the index.
   *
   * @command search:index
   * @aliases search-index
   * @bootstrap max
   */
  public function index() {
    list($remaining, $total) = $this->getStatus();
    register_shutdown_function('search_update_totals');
    $failures = 0;
    while ($remaining > 0) {
      $done = $total - $remaining;
      $percent = $done / $total * 100;
      $this->logger()->info(dt('!percent complete. Remaining items to be indexed: !count', [
        '!percent' => number_format($percent, 2),
        '!count' => $remaining,
      ]));

      // Use drush_invoke_process() to start subshell. Avoids out of memory issue.
      $eval = "search_cron();";
      drush_invoke_process('@self', 'php-eval', [$eval]);
      $previous_remaining = $remaining;
      list($remaining) = $this->getStatus();
      // Make sure we're actually making progress.
      if ($remaining == $previous_remaining) {
        $failures++;
        if ($failures == 3) {
          $this->logger()->error(dt('Indexing stalled with @number items remaining.', [
            '@number' => $remaining,
          ]));
          return;
        }
      }
      // Only count consecutive failures.
      else {
        $failures = 0;
      }
    }
  }

  /**
   * Force the search index to be rebuilt.
   *
   * @param array $options An associative array of options whose values come
   *   from cli, aliases, config, etc.
   *
   * @option immediate
   *   Rebuild the index immediately, instead of waiting for cron.
   *
   * @command search:reindex
   * @aliases search-reindex
   * @throws \Exception
   * @bootstrap max
   */
  public function reindex(array $options = ['immediate' => NULL]) {
    $this->output()->writeln(dt('The search index must be fully rebuilt before any new items can be indexed.'));
    if ($options['immediate']) {
      $this->output()->writeln(dt('Rebuilding the index may take a long time.'));
    }
    if (!$this->io()->confirm(dt('Do you really want to continue?'))) {
      throw new UserAbortException();
    }

    // D8 CR: https://www.drupal.org/node/2326575
    $search_page_repository = \Drupal::service('search.search_page_repository');
    foreach ($search_page_repository->getIndexableSearchPages() as $entity) {
      $entity->getPlugin()->markForReindex();
    }

    if ($options['immediate']) {
      $this->index();
      $this->logger()->info(dt('The search index has been rebuilt.'));
    }
    else {
      $this->logger()->info(dt('The search index will be rebuilt.'));
    }
  }

  /**
   * Get search status as an array containing remaining and total.
   *
   * @return array
   *   Search status as an array containing remaining and total.
   */
  protected function getStatus() {
    $remaining = 0;
    $total = 0;
    $search_page_repository = \Drupal::service('search.search_page_repository');
    foreach ($search_page_repository->getIndexableSearchPages() as $entity) {
      $status = $entity->getPlugin()->indexStatus();
      $remaining += $status['remaining'];
      $total += $status['total'];
    }
    return [$remaining, $total];
  }

}
