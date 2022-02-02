<?php

namespace Drush\SiteAlias;

/**
 * A host path is a path on some machine. The machine may be specified
 * by a label, and the label may be an @alias or a site specification.
 * If there is no label, then the local machine is assumed.
 *
 * Examples:
 *
 *   @alias
 *   @alias:/path
 *   host:/path
 *   user@host:/path
 *   user@host/drupal-root#uri:/path
 *   /path
 *
 * Note that /path does not have to begin with a '/'; it may
 * be a relative path, or it may begin with a path alias,
 * e.g. '%files'.
 *
 * It is permissible to have an alias or site specification
 * without a path, but it is not valid to have just a host
 * with no path.
 */
class HostPath extends \Consolidation\SiteAlias\HostPath
{
}
