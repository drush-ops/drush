<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\SiteProcess\Util\Tty;
use Drush\Boot\DrupalBootLevels;
use Drush\Config\ConfigAwareTrait;
use Drush\Drush;
use Drupal\Core\Url;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Exec\ExecTrait;
use Robo\Contract\ConfigAwareInterface;
use Symfony\Component\Filesystem\Path;

final class RunserverCommands extends DrushCommands implements ConfigAwareInterface
{
    use ExecTrait;
    use ConfigAwareTrait;

    const RUNSERVER = 'runserver';
    protected $uri;

    /**
     * Runs PHP's built-in http server for development.
     *
     * - Don't use this for production, it is neither scalable nor secure for this use.
     * - If you run multiple servers simultaneously, you will need to assign each a unique port.
     * - Use Ctrl-C or equivalent to stop the server when complete.
     */
    #[CLI\Command(name: self::RUNSERVER, aliases: ['rs', 'serve'])]
    #[CLI\Argument(name: 'uri', description: 'IP address and port number to bind to and path to open in web browser. Format is addr:port/path. Only opens a browser if a path is specified.')]
    #[CLI\Option(name: 'default-server', description: 'A default addr:port/path to use for any values not specified as an argument.')]
    #[CLI\Option(name: 'browser', description: 'Open the URL in the default browser. Use --no-browser to avoid opening a browser.')]
    #[CLI\Option(name: 'dns', description: 'Resolve hostnames/IPs using DNS/rDNS (if possible) to determine binding IPs and/or human friendly hostnames for URLs and browser.')]
    #[CLI\Usage(name: 'drush rs 8080', description: 'Start a web server on 127.0.0.1, port 8080.')]
    #[CLI\Usage(name: 'drush rs 10.0.0.28:80', description: 'Start runserver on 10.0.0.28, port 80.')]
    #[CLI\Usage(name: 'drush rs [::1]:80', description: 'Start runserver on IPv6 localhost ::1, port 80.')]
    #[CLI\Usage(name: 'drush rs --dns localhost:8888/user', description: 'Start runserver on localhost (using rDNS to determine binding IP), port 8888, and open /user in browser.')]
    #[CLI\Usage(name: 'drush rs /', description: 'Start runserver on default IP/port (127.0.0.1, port 8888), and open / in browser.')]
    #[CLI\Usage(name: 'drush rs :9000/admin', description: 'Start runserver on 127.0.0.1, port 9000, and open /admin in browser. Note that you need a colon when you specify port and path, but no IP.')]
    #[CLI\Usage(name: 'drush --quiet rs', description: 'Silence logging the printing of web requests to the console.')]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function runserver($uri = null, $options = ['default-server' => self::REQ, 'browser' => true, 'dns' => false])
    {
        // Determine active configuration.
        $uri = $this->uri($uri, $options);
        if (!$uri) {
            return false;
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
        $this->logger()->notice(dt('HTTP server listening on !addr, port !port (see http://!hostname:!port/!path), serving site, !site', ['!addr' => $addr, '!hostname' => $hostname, '!port' => $uri['port'], '!path' => $path, '!site' => \Drupal::service('kernel')->getSitePath()]));
        // Start php built-in server.
        if (!empty($path)) {
            // Start a browser if desired. Include a 2 second delay to allow the server to come up.
            $this->startBrowser($link, 2);
        }
        // Start the server using 'php -S'.
        $router = Path::join($this->config->get('drush.base-dir'), '/misc/d8-rs-router.php');
        $php = $this->getConfig()->get('php', 'php');
        $process = $this->processManager()->process([$php, '-S', $addr . ':' . $uri['port'], $router]);
        $process->setTimeout(null);
        $process->setWorkingDirectory(Drush::bootstrapManager()->getRoot());
        $process->setTty(Tty::isTtySupported());
        if ($options['quiet']) {
            $process->disableOutput();
        }
        $process->mustRun();
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
        if (is_array($uri)) {
            // Populate defaults.
            $uri = $uri + $user_default + $site_default + $drush_default;
            if (ltrim($uri['path'], '/') == '-') {
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
        if ($uri[0] == ':') {
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
            throw new \Exception(dt('Invalid argument - should be in the "host:port/path" format, numeric (port only) or non-numeric (path only).'));
        }
        if (count($uri) == 1 && isset($uri['path'])) {
            if (is_numeric($uri['path'])) {
                // Port only shorthand.
                $uri['port'] = $uri['path'];
                unset($uri['path']);
            }
        }
        if (isset($uri['host']) && $uri['host'] == 'placeholder-hostname') {
            unset($uri['host']);
        }
        return $uri;
    }
}
