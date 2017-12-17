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
    /** @var AliasRecord The alias record obtained from the host path */
    protected $alias_record;

    /** @var string The entire original host path (e.g. @alias:/path) */
    protected $original_path;

    /** @var string The "path" component from the host path */
    protected $path;

    /** @var string The alias record is implicit (e.g. 'path' instead of '@self:path') */
    protected $implicit;

    /**
     * HostPath constructor
     *
     * @param AliasRecord $alias_record The alias record or site specification record
     * @param string $original_path The original host path
     * @param string $path Just the 'path' component
     */
    protected function __construct($alias_record, $original_path, $path = '', $implicit = false)
    {
        $this->alias_record = $alias_record;
        $this->original_path = $original_path;
        $this->path = $path;
        $this->implicit = $implicit;
    }

    /**
     * Factory method to create a host path.
     *
     * @param SiteAliasManager $manager We need to be provided a reference
     *   to the alias manager to create a host path
     * @param string $hostPath The path to create.
     */
    public static function create(SiteAliasManager $manager, $hostPath)
    {
        // Split the alias path up into
        //  - $parts[0]: everything before the first ":"
        //  - $parts[1]: everything after the ":", if there was one.
        $parts = explode(':', $hostPath, 2);

        // Determine whether or not $parts[0] is a site spec or an alias
        // record.  If $parts[0] is not in the right form, the result
        // will be 'false'. This will throw if $parts[0] is an @alias
        // record, but the requested alias cannot be found.
        $alias_record = $manager->get($parts[0]);

        if (!isset($parts[1])) {
            return static::determinePathOrAlias($manager, $alias_record, $hostPath, $parts[0]);
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
        return new HostPath($alias_record, $hostPath, $parts[1]);
    }

    /**
     * Return the alias record portion of the host path.
     *
     * @return AliasRecord
     */
    public function getAliasRecord()
    {
        return $this->alias_record;
    }

    /**
     * Returns true if this host path points at a remote machine
     *
     * @return bool
     */
    public function isRemote()
    {
        return $this->alias_record->isRemote();
    }

    /**
     * Return the original host path string, as provided to the create() method.
     *
     * @return string
     */
    public function getOriginal()
    {
        return $this->original_path;
    }

    /**
     * Return just the path portion of the host path
     *
     * @return string
     */
    public function getPath()
    {
        if (empty($this->path)) {
            return $this->alias_record->root();
        }
        if ($this->alias_record->hasRoot() && !$this->implicit) {
            return Path::makeAbsolute($this->path, $this->alias_record->root());
        }
        return $this->path;
    }

    /**
     * Returns 'true' if the path portion of the host path begins with a
     * path alias (e.g. '%files'). Path aliases must appear at the beginning
     * of the path.
     *
     * @return bool
     */
    public function hasPathAlias()
    {
        $pathAlias = $this->getPathAlias();
        return !empty($pathAlias);
    }

    /**
     * Return just the path alias portion of the path (e.g. '%files'), or
     * empty if there is no alias in the path.
     *
     * @return string
     */
    public function getPathAlias()
    {
        if (preg_match('#%([^/]*).*#', $this->path, $matches)) {
            return $matches[1];
        }
        return '';
    }

    /**
     * Replaces the path alias portion of the path with the resolved path.
     *
     * @param string $resolvedPath The converted path alias (e.g. 'sites/default/files')
     * @return $this
     */
    public function replacePathAlias($resolvedPath)
    {
        $pathAlias = $this->getPathAlias();
        if (empty($pathAlias)) {
            return $this;
        }
        // Once the path alias is resolved, replace the alias in the $path with the result.
        $this->path = rtrim($resolvedPath, '/') . substr($this->path, strlen($pathAlias) + 1);

        // Using a path alias such as %files is equivalent to making explicit
        // use of @self:%files. We set implicit to false here so that the resolved
        // path will be returned as an absolute path rather than a relative path.
        $this->implicit = false;

        return $this;
    }

    /**
     * Return the host portion of the host path, including the user.
     *
     * @return string
     */
    public function getHost()
    {
        return $this->alias_record->remoteHostWithUser();
    }

    /**
     * Return the fully resolved path, e.g. user@server:/path/to/drupalroot/sites/default/files
     *
     * @return string
     */
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
     *
     * @return string
     */
    public function fullyQualifiedPathPreservingTrailingSlash()
    {
        $fqp = $this->fullyQualifiedPath();
        if ((substr($this->path, strlen($this->path) - 1) == '/') && (substr($fqp, strlen($fqp) - 1) != '/')) {
            $fqp .= '/';
        }
        return $fqp;
    }

    /**
     * Helper method for HostPath::create(). When the host path contains no
     * ':', this method determines whether the string that was provided is
     * a host or a path.
     *
     * @param SiteAliasManager $manager
     * @param AliasRecord|bool $alias_record
     * @param string $hostPath
     * @param string $single_part
     */
    protected static function determinePathOrAlias(SiteAliasManager $manager, $alias_record, $hostPath, $single_part)
    {
        // If $alias_record is false, then $single_part must be a path.
        if ($alias_record === false) {
            return new HostPath($manager->getSelf(), $hostPath, $single_part, true);
        }

        // Otherwise, we have a alias record without a path.
        // In this instance, the alias record _must_ have a root.
        if (!$alias_record->hasRoot()) {
            throw new \Exception("$hostPath does not define a path.");
        }
        return new HostPath($alias_record, $hostPath);
    }
}
