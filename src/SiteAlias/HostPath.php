<?php
namespace Drush\SiteAlias;

use Consolidation\Config\Config;
use Consolidation\Config\ConfigInterface;
use Webmozart\PathUtil\Path;

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
class HostPath
{
    /** @var AliasRecord */
    protected $alias_record;

    /** @var string */
    protected $original_path;

    /** @var string */
    protected $path;

    protected function __construct($alias_record, $original_path, $path = '')
    {
        $this->alias_record = $alias_record;
        $this->original_path = $original_path;
        $this->path = $path;
    }

    public static function create(SiteAliasManager $manager, $alias_path)
    {
        // Split the alias path up into
        //  - $parts[0]: everything before the first ":"
        //  - $parts[1]: everything after the ":", if there was one.
        $parts = explode(':', $alias_path, 2);

        // Determine whether or not $parts[0] is a site spec or an alias
        // record.  If $parts[0] is not in the right form, the result
        // will be 'false'. This will throw if $parts[0] is an @alias
        // record, but the requested alias cannot be found.
        $alias_record = $manager->get($parts[0]);

        if (!isset($parts[1])) {
            return static::determinePathOrAlias($manager, $alias_record, $alias_path, $parts[0]);
        }

        // If $parts[0] did not resolve to a site spec or alias record,
        // but there is a $parts[1], then $parts[0] must be a machine name.
        // Unless it was an alias that could not be found.
        if ($alias_record === false) {
            if (SiteAliasName::isAliasName($parts[0])) {
                throw new \Exception('Site alias ' . $parts[0] . ' not found.');
            }
            $alias_record = new AliasRecord(['host' => $parts[0]]);
        }

        // Create our alias path
        return new HostPath($alias_record, $alias_path, $parts[1]);
    }

    public function getAliasRecord()
    {
        return $this->alias_record;
    }

    public function isRemote()
    {
        return $this->alias_record->isRemote();
    }

    public function getOriginal()
    {
        return $this->original_path;
    }

    public function getPath()
    {
        if (empty($this->path)) {
            return $this->alias_record->root();
        }
        if ($this->alias_record->hasRoot()) {
            return Path::makeAbsolute($this->path, $this->alias_record->root());
        }
        return $this->path;
    }

    public function hasPathAlias()
    {
        $pathAlias = $this->getPathAlias();
        return !empty($pathAlias);
    }

    public function getPathAlias()
    {
        if (preg_match('#%([^/]*).*#', $this->path, $matches)) {
            return $matches[1];
        }
        return '';
    }

    public function replacePathAlias($resolvedPath)
    {
        $pathAlias = $this->getPathAlias();
        if (!empty($pathAlias)) {
            $this->path = rtrim($resolvedPath, '/') . substr($this->path, strlen($pathAlias) + 1);
        }
    }

    public function getHost()
    {
        return $this->alias_record->remoteHostWithUser();
    }

    public function fullyQualifiedPath()
    {
        $host = $this->getHost();
        if (!empty($host)) {
            return $host . ':' . $this->getPath();
        }
        return $this->getPath();
    }

    /**
     * Our fully qualified path passes the result through Path::makeAbsolute()
     * which canonicallizes the path, removing any trailing slashes.
     * That is what we want most of the time; however, the trailing slash is
     * sometimes significant, e.g. for rsync, so we provide a separate API
     * for those cases where the trailing slash should be preserved.
     */
    public function fullyQualifiedPathPreservingTrailingSlash()
    {
        $fqp = $this->fullyQualifiedPath();

        if ((substr($this->path, strlen($this->path) - 1) == '/') && (substr($fqp, strlen($fqp) - 1) != '/')) {
            $fqp .= '/';
        }
        return $fqp;
    }

    protected static function determinePathOrAlias($manager, $alias_record, $alias_path, $single_part)
    {
        // If $alias_record is false, then $single_part must be a path.
        if ($alias_record === false) {
            return new HostPath($manager->getSelf(), $alias_path, $single_part);
        }

        // Otherwise, we have a alias record without a path.
        // In this instance, the alias record _must_ have a root.
        if (!$alias_record->hasRoot()) {
            throw new \Exception("$alias_path does not define a path.");
        }
        return new HostPath($alias_record, $alias_path);
    }
}
