<?php
namespace Drush\Backend;

use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\HostPath;
use Drush\Drush;

class BackendPathEvaluator
{
    /**
     * Evaluate will check to see if the provided host path
     * contains a path alias. If it does, the alias will
     * be resolved, and the result of the resolution will be
     * injected into the HostPath, replacing the alias.
     *
     * @param HostPath $path The host and path to evaluate aliases on.
     */
    public function evaluate(HostPath $path)
    {
        $resolvedPath = $this->resolve($path);
        if (!$resolvedPath) {
            return;
        }

        $path->replacePathAlias($resolvedPath);
    }

    /**
     * Resolve will check to see if the provided host path
     * contains a path alias. If it does, the alias will
     * be resolved, and the result of the resolution will be
     * returned.
     *
     * @param HostPath $path The host and path to resolve aliases on.
     * @return string
     */
    public function resolve(HostPath $path)
    {
        if (!$path->hasPathAlias()) {
            return false;
        }

        // If HostPath is `@site:%files`, then the path alias is `files`.
        $pathAlias = $path->getPathAlias();
        return $this->lookup($path->getSiteAlias(), $pathAlias);
    }

    /**
     * Lookup will use the provided alias record to look up and return
     * the value of a path alias.
     *
     * @param SiteAlias $aliasRecord the host to use for lookups
     * @param $pathAlias the alias to look up (`files`, not `%files`)
     * @return string
     */
    public function lookup(SiteAlias $aliasRecord, $pathAlias)
    {
        if ($aliasRecord->has("paths.$pathAlias")) {
            return $aliasRecord->get("paths.$pathAlias");
        }

        return $this->request($aliasRecord, $pathAlias);
    }

    /**
     * Request the value of the path alias from the site associated with
     * the alias record.
     *
     * @param SiteAlias $aliasRecord the host to use for lookups
     * @param string $pathAlias the alias to look up (`files`, not `%files`)
     * @return string
     */
    public function request(SiteAlias $aliasRecord, $pathAlias)
    {
        // The drupal:directory command uses a path evaluator, which
        // calls this function, so we cannot use dd here, as that
        // would be recursive.
        $process = Drush::drush($aliasRecord, 'core-status', [], ['project' => $pathAlias, 'fields' => '%paths', 'format' => 'json']);
        $process->setSimulated(false);
        $process->mustRun();
        $json = $process->getOutputAsJson();
        if (isset($json['%paths']["%{$pathAlias}"])) {
            return $json['%paths']["%{$pathAlias}"];
        }
        throw new \Exception(dt('Cannot evaluate path alias %{path} for site alias {site}', ['path' => $pathAlias, 'site' => $aliasRecord->name()]));
    }
}
