<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use Consolidation\SiteAlias\HostPath;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drush\Attributes as CLI;
use Drush\Backend\BackendPathEvaluator;

final class DrupalDirectoryCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    const DIRECTORY = 'drupal:directory';

    /** @var BackendPathEvaluator */
    protected $pathEvaluator;

    public function __construct()
    {
        $this->pathEvaluator = new BackendPathEvaluator();
    }

    /**
     * Return the filesystem path for modules/themes and other key folders.
     */
    #[CLI\Command(name: self::DIRECTORY, aliases: ['dd', 'drupal-directory'])]
    #[CLI\Argument(name: 'target', description: 'A module/theme name, or special names like root, files, private, or an <info>alias:path</info> string such as @alias:%files.')]
    #[CLI\Option(name: 'local-only', description: 'Reject any target that specifies a remote site.')]
    #[CLI\Usage(name: 'cd $(drush dd devel)', description: 'Navigate into the devel module directory')]
    #[CLI\Usage(name: 'cd $(drush dd)', description: 'Navigate to the root of your Drupal site')]
    #[CLI\Usage(name: 'cd $(drush dd files)', description: 'Navigate to the files directory.')]
    #[CLI\Usage(name: 'drush dd @alias:%files', description: 'Print the path to the files directory on the site @alias.')]
    #[CLI\Usage(name: 'edit $(drush dd devel)/devel.module', description: 'Open devel module in your editor')]
    public function drupalDirectory(string $target = 'root', $options = ['local-only' => false]): string
    {
        $path = $this->getPath($target, $options['local-only']);

        // If getPath() is working right, it will turn
        // %blah into the path to the item referred to by the key 'blah'.
        // If there is no such key, then no replacement is done.  In the
        // case of the dd command, we will consider it an error if
        // any keys are -not- replaced.
        if ($path && (!str_contains($path, '%'))) {
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
