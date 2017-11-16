<?php
namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\SiteAlias\HostPath;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\SiteAlias\SiteAliasManagerAwareTrait;

use Drush\Backend\BackendPathEvaluator;

class DrupalDirectoryCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{

    use SiteAliasManagerAwareTrait;

    /** @var BackendPathEvaluator */
    protected $pathEvaluator;

    public function __construct()
    {
        // TODO: once the BackendInvoke service exists, inject it here
        // and use it to get the path evaluator
        $this->pathEvaluator = new BackendPathEvaluator();
    }

    /**
     * Return the filesystem path for modules/themes and other key folders.
     *
     * @command drupal:directory
     * @param string $target A module/theme name, or special names like root, files, private, or an alias : path alias string such as @alias:%files. Defaults to root.
     * @option component The portion of the evaluated path to return.  Defaults to 'path'; 'name' returns the site alias of the target.
     * @option local-only Reject any target that specifies a remote site.
     * @usage cd `drush dd devel`
     *   Navigate into the devel module directory
     * @usage cd `drush dd`
     *   Navigate to the root of your Drupal site
     * @usage cd `drush dd files`
     *   Navigate to the files directory.
     * @usage drush dd @alias:%files
     *   Print the path to the files directory on the site @alias.
     * @usage edit `drush dd devel`/devel.module
     *   Open devel module in your editor (customize 'edit' for your editor)
     * @aliases dd,drupal-directory
     */
    public function drupalDirectory($target = 'root', $options = ['local-only' => false])
    {
        $path = $this->getPath($target, $options['local-only']);

        // If getPath() is working right, it will turn
        // %blah into the path to the item referred to by the key 'blah'.
        // If there is no such key, then no replacement is done.  In the
        // case of the dd command, we will consider it an error if
        // any keys are -not- replaced in _drush_core_directory.
        if ($path && (strpos($path, '%') === false)) {
            return $path;
        } else {
            throw new \Exception(dt("Target '{target}' not found.", ['target' => $target]));
        }
    }

    /**
     * Given a target (e.g. @site:%modules), return the evaluated directory path.
     *
     * @param $target
     *   The target to evaluate.  Can be @site or /path or @site:path
     *   or @site:%pathalias, etc. (just like rsync parameters)
     * @param $local_only
     *   When true, fail if the site alias is remote.
     */
    protected function getPath($target = 'root', $local_only = false)
    {
        // In the dd command, if the path does not begin with / or % or @ ett.,
        // then we will assume an implicit "%".
        if (preg_match('#^[a-zA-Z0-9_-]*$#', $target)) {
            $target = "%$target";
        }
        // Set up the evaluated path; fail if --local-only and the site alias is remote
        $evaluatedPath = HostPath::create($this->siteAliasManager(), $target);
        if ($local_only && $evaluatedPath->isRemote()) {
            throw new \Exception(dt('{target} was remote, and --local-only was specified', ['target' => $target]));
        }
        $this->pathEvaluator->evaluate($evaluatedPath);
        return $evaluatedPath->fullyQualifiedPath();
    }
}
