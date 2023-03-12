<?php

declare(strict_types=1);

namespace Drush\SiteAlias;

/**
 * Parse a string that contains a site alias name, and provide convenience
 * methods to access the parts.
 *
 * When provided by users, aliases must be in one of the following forms:
 *
 *   - @sitename.env: List only sitename and environment.
 *
 *   - @env: Look up a named environment in instances where the site root
 *       is known (e.g. via cwd). In this form, there is an implicit sitename
 *       'self' which is replaced by the actual site alias name once known.
 *
 *   - @sitename: Provides only the sitename; uses the 'default' environment,
 *       or 'dev' if there is no 'default' (or whatever is there if there is
 *       only one). With this form, the site alias name has no environment
 *       until the appropriate default environment is looked up. This form
 *       is checked only after `@env` returns no matches.
 *
 * There are also two special aliases that are recognized:
 *
 *   - @self: The current bootstrapped site.
 *
 *   - @none: No alias ('root' and 'uri' unset).
 *
 * The special alias forms have no environment component.
 *
 * When provided to an API, the '@' is optional.
 *
 * Note that @sitename and @env are ambiguous. Aliases in this form
 * (that are not one of the special aliases) will first be assumed
 * to be @env, and may be converted to @sitename later.
 *
 * Note that:
 *
 * - 'sitename' and 'env' MUST NOT contain a '.' (unlike previous
 *     versions of Drush).
 * - Users SHOULD NOT create any environments that have the same name
 *     as any site name (and visa-versa).
 * - All environments in one site record SHOULD be different versions
 *     of the same site (e.g. dev / test / live).
 */
class SiteAliasName extends \Consolidation\SiteAlias\SiteAliasName
{
}
