<?php

namespace Drush\Commands\core;

use Drush\Drush;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;

class CheckBootstrapCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * This command executes successfully if Drupal has been fully bootstrapped.
     *
     * @see DRUSH_BOOTSTRAP_DRUPAL_FULL
     *
     * @command check-bootstrap:full
     * @aliases check-bootstrap, cb
     * @bootstrap max
     */
    public function isBootstrapFull()
    {
      $this->checkBootstrapPhase(DRUSH_BOOTSTRAP_DRUPAL_FULL);
    }

    /**
     * This command executes successfully if Drupal's database has been bootstrapped.
     *
     * @see DRUSH_BOOTSTRAP_DRUPAL_DATABASE
     *
     * @command check-bootstrap:db
     * @bootstrap max
     */
    public function isBootstrapDatabase()
    {
      $this->checkBootstrapPhase(DRUSH_BOOTSTRAP_DRUPAL_DATABASE);
    }

    /**
     * This command executes successfully if Drupal's settings.php have been read.
     *
     * @see DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION
     *
     * @command check-bootstrap:config
     * @bootstrap max
     */
    public function isBootstrapConfiguration()
    {
      $this->checkBootstrapPhase(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);
    }

    /**
     * This command executes successfully if Drupal's settings.php was found.
     *
     * @see DRUSH_BOOTSTRAP_DRUPAL_SITE
     *
     * @command check-bootstrap:site
     * @bootstrap max
     */
    public function isBootstrapSite()
    {
      $this->checkBootstrapPhase(DRUSH_BOOTSTRAP_DRUPAL_SITE);
    }

    /**
     * This command executes successfully if a Drupal site was found.
     *
     * @see DRUSH_BOOTSTRAP_DRUPAL_ROOT
     *
     * @command check-bootstrap:root
     * @bootstrap max
     */
    public function isBootstrapRoot()
    {
      $this->checkBootstrapPhase(DRUSH_BOOTSTRAP_DRUPAL_ROOT);
    }

    protected function checkBootstrapPhase($phase)
    {
      if (Drush::bootstrapManager()->hasBootstrapped($phase)) {
        drush_set_context('DRUSH_EXIT_CODE', DRUSH_SUCCESS);
      }
      else {
        drush_set_context('DRUSH_EXIT_CODE', DRUSH_FRAMEWORK_ERROR);
      }
    }
}
