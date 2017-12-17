<?php
namespace Drush\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\SiteAlias\SiteAliasManagerAwareTrait;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Load this example by using the --include option - e.g. `drush --include=/path/to/drush/examples`
 */
class SiteAliasAlterCommands extends DrushCommands implements SiteAliasManagerAwareInterface  {

  use SiteAliasManagerAwareTrait;

  /**
   * A few example alterations to site aliases.
   *
   * @hook pre-init *
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Consolidation\AnnotatedCommand\AnnotationData $annotationData
   */
  public function alter(InputInterface $input, AnnotationData $annotationData) {
    $self = $this->siteAliasManager()->getSelf();
    if ($self->isRemote()) {

      // Always pass along ssh keys.
      if (!$self->has('ssh.options')) {
        // Don't edit the alias - edit the general config service instead.
        $this->getConfig()->set('ssh.options', '-o ForwardAgent=yes');
      }

      // Change the SSH user.
      $input->setOption('remote-user', 'mw2');
    }
  }
}
