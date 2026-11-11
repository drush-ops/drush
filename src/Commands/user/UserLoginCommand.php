<?php

declare(strict_types=1);

namespace Drush\Commands\user;

use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Config\DrushConfig;
use Drush\Doctor\DoctorTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs `dr user:login`, the one-time login link that ships with Drupal core 11.4+.
 *
 * As with cache:rebuild, the Bootstrap attribute keeps Drush from
 * bootstrapping Drupal fully before handing over to dr, which bootstraps on
 * its own. It stops at the SITE level rather than NONE so that Drush can tell
 * dr which site directory the uri selects (see DoctorTrait::siteEnvironment()).
 * Messages are plain strings for the same reason: t() needs Drupal's
 * container.
 *
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Display a one time login link for user ID 1, or another user, by running dr user:login. Requires Drupal core 11.4+.',
    aliases: ['uli', 'user-login'],
    help: 'To avoid the http://default domain in the link, set the [DRUSH_OPTIONS_URI environment variable](https://www.drush.org/latest/using-drush-configuration/#environment-variables).',
    usages: [
        'user:login --name=ryan node/add/blog',
        'user:login --uid=123',
        'user:login --mail=foo@bar.com',
    ],
)]
#[CLI\Bootstrap(level: DrupalBootLevels::SITE)]
class UserLoginCommand
{
    use AutowireTrait;
    use DoctorTrait;

    public const string NAME = 'user:login';

    public function __construct(
        protected DrushConfig $drushConfig,
        protected BootstrapManager $bootstrapManager,
        protected LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument('Optional path to redirect to after logging in.')]
        string $path = '',
        #[Option('A user name to log in as.')]
        ?string $name = null,
        #[Option('A user ID to log in as.')]
        ?string $uid = null,
        #[Option('A user email to log in as.')]
        ?string $mail = null,
    ): int {
        $this->logger->info('user:login is deprecated. Run dr user:login instead.');

        $arguments = ['user:login'];
        if ($path !== '') {
            $arguments[] = $path;
        }
        foreach (['name' => $name, 'uid' => $uid, 'mail' => $mail] as $option => $value) {
            if ($value !== null) {
                $arguments[] = "--$option=$value";
            }
        }

        $binary = $this->doctorBinary($this->drushConfig, self::NAME);
        return $this->runDoctor($binary, $arguments, $this->bootstrapManager, $output);
    }
}
