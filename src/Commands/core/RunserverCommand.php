<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\SiteProcess\Util\Tty;
use Drupal\Core\Url;
use Drush\Boot\BootstrapManager;
use Drush\Commands\AutowireTrait;
use Drush\Config\DrushConfig;
use Drush\Exec\ExecTrait;
use Drush\SiteAlias\ProcessManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: self::NAME,
    description: 'Runs PHP\'s built-in http server for development.',
    # @todo Console is handing off requests for 'rs' to rsync! See \Symfony\Component\Console\Application::find
    aliases: ['rs', 'serve']
)]
final class RunserverCommand extends Command
{
    use AutowireTrait;
    use ExecTrait;

    const string NAME = 'runserver';

    protected $uri;

    public function __construct(
        protected readonly BootstrapManager $bootstrapManager,
        protected readonly DrushConfig $drushConfig,
        protected readonly LoggerInterface $logger,
        private readonly ProcessManager $processManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('uri', InputArgument::OPTIONAL, 'IP address and port number to bind to and path to open in web browser. Format is addr:port/path. Only opens a browser if a path is specified.')
            ->addOption('default-server', null, InputOption::VALUE_REQUIRED, 'A default addr:port/path to use for any values not specified as an argument.')
            ->addOption('browser', null, InputOption::VALUE_NEGATABLE, 'Open the URL in the default browser. Use --no-browser to avoid opening a browser.', true)
            ->addOption('dns', null, InputOption::VALUE_NONE, 'Resolve hostnames/IPs using DNS/rDNS (if possible) to determine binding IPs and/or human friendly hostnames for URLs and browser.')
            ->addUsage('runserver 8080')
            ->addUsage('runserver 10.0.0.28:80')
            ->addUsage('runserver [::1]:80')
            ->addUsage('runserver --dns localhost:8888/user')
            ->addUsage('runserver /')
            ->addUsage('runserver :9000/admin')
            ->addUsage('--quiet runserver');

        $this->setHelp('Runs PHP\'s built-in http server for development. Don\'t use this for production, it is neither scalable nor secure for this use. If you run multiple servers simultaneously, you will need to assign each a unique port. Use Ctrl-C or equivalent to stop the server when complete.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $uriArg = $input->getArgument('uri');

        $uri = $this->uri($uriArg, $input->getoptions());
        if (!$uri) {
            throw new \RuntimeException('Unable to determine URI');
        }

        // Remove any leading slashes from the path, since that is what url() expects.
        $path = ltrim($uri['path'], '/');

        // $uri['addr'] is a special field set by runserver_uri()
        $hostname = $uri['host'];
        $addr = $uri['addr'];

        $this->uri = 'http://' . $hostname . ':' . $uri['port'];

        // We delete any registered files here, since they are not caught by Ctrl-C.
        _drush_delete_registered_files();

        $link = Url::fromUserInput('/' . $path, ['absolute' => true])->toString();
        $context = [
            'addr' => $addr,
            'port' => $uri['port'],
            'hostname' => $hostname,
            'path' => $path,
            'sitepath' => \Drupal::service('kernel')->getSitePath(),
        ];
        $this->logger->notice('HTTP server listening on {addr}, port {port} (see http://{hostname}:{port}/{path}), serving site {sitepath})', $context);

        // Start php built-in server.
        if (!empty($path)) {
            // Start a browser if desired. Include a 2 second delay to allow the server to come up.
            $this->startBrowser($link, 2);
        }

        // Start the server using 'php -S'.
        $router = Path::join($this->drushConfig->get('drush.base-dir'), '/misc/d8-rs-router.php');
        $php = $this->drushConfig->get('php', 'php');
        $process = $this->processManager->process([$php, '-S', $addr . ':' . $uri['port'], $router]);
        $process->setTimeout(null);
        $process->setWorkingDirectory($this->bootstrapManager->getRoot());
        $process->setTty(Tty::isTtySupported());
        if ($input->getOption('quiet')) {
            $process->disableOutput();
        }
        $process->mustRun();

        return self::SUCCESS;
    }

    /**
     * Determine the URI to use for this server.
     */
    public function uri($uri, $options): array
    {
        $drush_default = [
            'host' => '127.0.0.1',
            'port' => '8888',
            'path' => '',
        ];
        $user_default = $this->parseUri($options['default-server']);
        $site_default = $this->parseUri($uri);
        $uri = $this->parseUri($uri);

        // Populate defaults.
        $uri = $uri + $user_default + $site_default + $drush_default;
        if (ltrim($uri['path'], '/') === '-') {
            // Allow a path of a single hyphen to clear a default path.
            $uri['path'] = '';
        }
        // Determine and set the new URI.
        $uri['addr'] = $uri['host'];
        if ($options['dns']) {
            if (ip2long($uri['host'])) {
                $uri['host'] = gethostbyaddr($uri['host']);
            } else {
                $uri['addr'] = gethostbyname($uri['host']);
            }
        }
        return $uri;
    }

    /**
     * Parse a URI or partial URI (including just a port, host IP or path).
     *
     * @param $uri
     *   String that can contain partial URI.
     *
     *   URI array as returned by parse_url.
     */
    public function parseUri(?string $uri): array
    {
        if (empty($uri)) {
            return [];
        }
        if ($uri[0] === ':') {
            // ':port/path' shorthand, insert a placeholder hostname to allow parsing.
            $uri = 'placeholder-hostname' . $uri;
        }
        // FILTER_VALIDATE_IP expects '[' and ']' to be removed from IPv6 addresses.
        // We check for colon from the right, since IPv6 addresses contain colons.
        $to_path = trim(substr($uri, 0, (int)strpos($uri, '/')), '[]');
        $to_port = trim(substr($uri, 0, (int)strrpos($uri, ':')), '[]');
        if (filter_var(trim($uri, '[]'), FILTER_VALIDATE_IP) || filter_var($to_path, FILTER_VALIDATE_IP) || filter_var($to_port, FILTER_VALIDATE_IP)) {
            // 'IP', 'IP/path' or 'IP:port' shorthand, insert a schema to allow parsing.
            $uri = 'http://' . $uri;
        }
        $uri = parse_url($uri);
        if (empty($uri)) {
            throw new \Exception('Invalid argument - should be in the "host:port/path" format, numeric (port only) or non-numeric (path only).');
        }
        if (count($uri) === 1 && isset($uri['path'])) {
            if (is_numeric($uri['path'])) {
                // Port only shorthand.
                $uri['port'] = $uri['path'];
                unset($uri['path']);
            }
        }
        if (isset($uri['host']) && $uri['host'] === 'placeholder-hostname') {
            unset($uri['host']);
        }
        return $uri;
    }
}
