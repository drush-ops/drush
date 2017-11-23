<?php
namespace Drush\Backend;

use Drush\SiteAlias\AliasRecord;
use Drush\SiteAlias\HostPath;

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
        return $this->lookup($path->getAliasRecord(), $pathAlias);
    }

    /**
     * Lookup will use the provided alias record to look up and return
     * the value of a path alias.
     *
     * @param AliasRecord $aliasRecord the host to use for lookups
     * @param $pathAlias the alias to look up (`files`, not `%files`)
     * @return string
     */
    public function lookup(AliasRecord $aliasRecord, $pathAlias)
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
     * @param AliasRecord $aliasRecord the host to use for lookups
     * @param string $pathAlias the alias to look up (`files`, not `%files`)
     * @return string
     */
    public function request(AliasRecord $aliasRecord, $pathAlias)
    {
        // The drupal:directory command uses a path evaluator, which
        // calls this function, so we cannot use dd here, as that
        // would be recursive.
        $values = drush_invoke_process($aliasRecord, "core-status", [], ['project' => $pathAlias], ['integrate' => false, 'override-simulated' => true]);
        $statusValues = $values['object'];
        if (isset($statusValues[$pathAlias])) {
            return $statusValues[$pathAlias];
        }
        throw new \Exception(dt('Cannot evaluate path alias %{path} for site alias {site}', ['path' => $pathAlias, 'site' => $aliasRecord->name()]));
    }
}
