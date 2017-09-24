<?php
namespace Drush\Backend;

use Drush\SiteAlias\HostPath;

class BackendPathEvaluator
{
    public function evaluate(HostPath $path)
    {
        $resolvedPath = $this->resolve($path);
        if (!$resolvedPath) {
            return;
        }

        $path->replacePathAlias($resolvedPath);
    }

    public function resolve(HostPath $path)
    {
        if (!$path->hasPathAlias()) {
            return false;
        }

        // If HostPath is `@site:%files`, then the path alias is `files`.
        $pathAlias = $path->getPathAlias();
        if ($path->getAliasRecord()->has("paths.$pathAlias")) {
            return $path->getAliasRecord()->get("paths.$pathAlias");
        }

        // TODO: call backend invoke to look up the $pathAlias

        return false;
    }
}
