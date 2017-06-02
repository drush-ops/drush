<?php
namespace Drush\Commands;

use Drush\Drush;

use Robo\LoadAllTasks;
use Robo\Contract\IOAwareInterface;
use Robo\Contract\BuilderAwareInterface;

class LegacyCommands extends DrushCommands implements BuilderAwareInterface, IOAwareInterface
{
    use LoadAllTasks;

    /**
     * @command pm-disable
     * @aliases dis
     * @hidden
     */
    public function disable()
    {
        $msg = 'Drupal 8 does not support disabling modules. See pm-uninstall command.';
        $this->logger()->notice($msg);
    }

    /**
     * @command pm-info
     * @aliases pmi
     * @hidden
     */
    public function info()
    {
        $msg = 'The pm-info command was deprecated. Please see `drush pm-list` and `composer show`';
        $this->logger()->notice($msg);
    }

    /**
     * @command pm-projectinfo
     * @hidden
     */
    public function projectInfo()
    {
        $msg = 'The pm-projectinfo command was deprecated. Please see `drush pm-list` and `composer show`';
        $this->logger()->notice($msg);
    }

    /**
     * @command pm-refresh
     * @aliases rf
     * @hidden
     */
    public function refresh()
    {
        $msg = 'The pm-refresh command was deprecated. It is no longer useful.';
        $this->logger()->notice($msg);
    }

    /**
     * @command pm-updatestatus
     * @aliases ups
     * @hidden
     */
    public function updatestatus()
    {
        $msg = 'The pm-updatestatus command was deprecated. Please see `composer show` and `composer outdated`. For security release notification, your project should depend on https://github.com/drupal-composer/drupal-security-advisories.';
        $this->logger()->notice($msg);
    }

    /**
     * @command pm-updatecode
     * @aliases pm-update,upc
     * @hidden
     */
    public function updatecode()
    {
        $msg = 'The pm-updatecode command was deprecated. Please see `composer outdated` and `composer update`. For security release notification, your project should depend on https://github.com/drupal-composer/drupal-security-advisories.';
        $this->logger()->notice($msg);
    }

    /**
     * @command pm-releasenotes
     * @aliases rln
     * @hidden
     */
    public function releaseNotes()
    {
        $msg = 'The pm-releasenotes command was deprecated. No replacement available.';
        $this->logger()->notice($msg);
    }

    /**
     * @command pm-releases
     * @aliases rl
     * @hidden
     */
    public function releases()
    {
        $msg = 'The pm-releases command was deprecated. Please see `composer show <packagename>`';
        $this->logger()->notice($msg);
    }

    /**
     * @command make
     * @aliases make-convert,make-generate,make-lock,make-update
     * @hidden
     */
    public function make()
    {
        $msg = 'Make has been removed, in favor of Composer. Use the make-convert command in Drush 8 to quickly upgrade your build to Composer.';
        $this->logger()->notice($msg);
    }

    /**
     * @command pm-download
     * @aliases dl
     * @hidden
     */
    public function download(
        array $args,
        $options = [
            'stability' => false,
            'dev' => false,
            'keep-vcs' => false,
            'no-install' => false,
            'repository' => 'https://packages.drupal.org/8'
        ]
    ) {
    
        $composerRoot = Drush::bootstrapManager()->getComposerRoot();

        $args = $this->fixProjectArgs($args);
        if ($options['dev']) {
            $options['stability'] = 'dev';
        }

        if (!$composerRoot) {
            return $this->downloadViaCreateProject($args, $options);
        }
        return $this->downloadViaRequire($composerRoot, $args, $options);
    }

    public function fixProjectArgs($args)
    {
        return array_map(
            function ($item) {
                list($project, $version) = explode(':', $item, 2) + ['', ''];

                if (strpos($project, "/") === false) {
                    $project = "drupal/$project";
                }

                if (!empty($version)) {
                    $project = "$project:$version";
                }

                return $project;
            },
            $args
        );
    }

    protected function downloadViaCreateProject($args, $options)
    {
        $builder = $this->collectionBuilder();
        foreach ($args as $arg) {
            $builder = $builder->taskComposerCreateProject()
                ->source($arg)
                ->repository($options['repository'])
                ->keepVcs($options['keep-vcs'])
                ->noInstall($options['no-install'])
                ->stability($options['stability']);
        }
        return $builder;
    }

    protected function downloadViaRequire($composerRoot, $args, $options)
    {
        return $this->taskComposerRequire()
            ->workingDir($composerRoot)
            ->noInstall($options['no-install'])
            ->dependency($args);
    }
}
