<?php

namespace Drush\Preflight;

use Drush\Config\Environment;
use Drush\Preflight\PreflightArgsInterface;
use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\SiteAliasManager;
use Consolidation\SiteAlias\SiteAliasName;
use Consolidation\SiteAlias\SiteSpecParser;

class PreflightSiteLocator
{
    /**
     * @var SiteAliasManager
     */
    protected $siteAliasManager;

    public function __construct(SiteAliasManager $siteAliasManager)
    {
        $this->siteAliasManager = $siteAliasManager;
    }

    /**
     * During bootstrap, finds the currently selected site from the parameters
     * provided on the commandline.
     *
     * If 'false' is returned, that indicates that there was an alias name
     * provided on the commandline that is either missing or invalid.
     *
     * @param PreflightArgsInterface $preflightArgs An alias name or site specification
     * @param \Drush\Config\Environment $environment
     * @param string $root The default Drupal root (from site:set, --root or cwd)
     *
     * @return \Consolidation\SiteAlias\SiteAlias|false
     */
    public function findSite(PreflightArgsInterface $preflightArgs, Environment $environment, $root)
    {
        $aliasName = $preflightArgs->alias();
        $self = $this->determineSelf($preflightArgs, $environment, $root);

        // If the user provided a uri on the commandline, inject it
        // into the alias that we found.
        if ($preflightArgs->hasUri()) {
            $self->setUri($preflightArgs->uri());
        }

        return $self;
    }

    /**
     * Either look up the specified alias name / site spec,
     * or, if those are invalid, then generate one from
     * the provided root and URI.
     *
     * @param \Drush\Preflight\PreflightArgsInterface $preflightArgs
     * @param \Drush\Config\Environment $environment
     * @param $root
     *
     * @return \Consolidation\SiteAlias\SiteAlias
     */
    protected function determineSelf(PreflightArgsInterface $preflightArgs, Environment $environment, $root)
    {
        $aliasName = $preflightArgs->alias();

        // If the user specified an @alias, that takes precidence.
        if (SiteAliasName::isAliasName($aliasName)) {
            // TODO: Should we do something about `@self` here? At the moment that will cause getAlias to
            // call getSelf(), but we haven't built @self yet.
            return $this->siteAliasManager->getAlias($aliasName);
        }

        // Ditto for a site spec (/path/to/drupal#uri)
        $specParser = new SiteSpecParser();
        if ($specParser->validSiteSpec($aliasName)) {
            return new SiteAlias($specParser->parse($aliasName, $root), $aliasName);
        }

        // If the user provides the --root parameter then we don't want to use
        // the site-set alias.
        $selectedRoot = $preflightArgs->selectedSite();
        if (!$selectedRoot) {
            $aliasName = $environment->getSiteSetAliasName();
            if (!empty($aliasName)) {
                $alias = $this->siteAliasManager->getAlias($aliasName);
                if ($alias) {
                    return $alias;
                }
            }
        }

        return $this->buildSelf($preflightArgs, $root);
    }

    /**
     * Generate @self from the provided root and URI.
     *
     * @param \Drush\Preflight\PreflightArgsInterface $preflightArgs
     * @param $root
     *
     * @return \Consolidation\SiteAlias\SiteAlias
     */
    protected function buildSelf(PreflightArgsInterface $preflightArgs, $root)
    {
        // If there is no root, then return '@none'
        if (!$root) {
            return new SiteAlias([], '@none');
        }

        // If there is no URI specified, we will allow it to
        // remain empty for now. We will refine it later via
        // Application::refineUriSelection(), which is called
        // in Preflight::doRun(). This method will set it to
        // 'default' if no better directory can be devined.

        // Create the 'self' alias record. Note that the self
        // record will be named '@self' if it is manually constructed
        // here, and will otherwise have the name of the
        // alias or site specification used by the user. Also note that if we
        // pass in a falsy uri the drush config (i.e drush.yml) can not override
        // it.
        $uri = $preflightArgs->uri();
        $data = [
            'root' => $root,
        ];
        if ($uri) {
            $data['uri'] = $uri;
        }

        return new SiteAlias($data, '@self');
    }
}
