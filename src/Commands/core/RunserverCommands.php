<?php
namespace Drush\Commands\core;

use Drush\Drush;
use Drupal\Core\Url;
use Drush\Commands\DrushCommands;
use Drush\Exec\ExecTrait;
use Webmozart\PathUtil\Path;

class RunserverCommands extends DrushCommands
{

    use ExecTrait;

    /**
     * Runs PHP's built-in http server for development.
     *
     * - Don't use this for production, it is neither scalable nor secure for this use.
     * - If you run multiple servers simultaneously, you will need to assign each a unique port.
     * - Use Ctrl-C or equivalent to stop the server when complete.
     *
     * @command runserver
     * @param $uri Host IP address and port number to bind to and path to open in web browser. Format is addr:port/path. Only opens a browser if a path is specified.
     * @option default-server A default addr:port/path to use for any values not specified as an argument.
     * @option browser If opening a web browser, which browser to use (defaults to operating system default). Use --no-browser to avoid opening a browser.
     * @option dns Resolve hostnames/IPs using DNS/rDNS (if possible) to determine binding IPs and/or human friendly hostnames for URLs and browser.
     * @bootstrap full
     * @aliases rs,serve
     * @usage drush rs 8080
     *   Start a web server on 127.0.0.1, port 8080.
     * @usage drush rs 10.0.0.28:80
     *   Start runserver on 10.0.0.28, port 80.
     * @usage drush rs [::1]:80
     *   Start runserver on IPv6 localhost ::1, port 80.
     * @usage drush rs --dns localhost:8888/user
     *   Start runserver on localhost (using rDNS to determine binding IP), port 8888, and open /user in browser.
     * @usage drush rs /
     *  Start runserver on default IP/port (127.0.0.1, port 8888), and open / in browser.
     * @usage drush rs --default-server=127.0.0.1:8080/ -
     *   Use a default (would be specified in your drushrc) that starts runserver on port 8080, and opens a browser to the front page. Set path to a single hyphen path in argument to prevent opening browser for this session.
     * @usage drush rs :9000/admin
     *   Start runserver on 127.0.0.1, port 9000, and open /admin in browser. Note that you need a colon when you specify port and path, but no IP.
     */
    public function runserver($uri = null, $options = ['default-server' => self::REQ, 'browser' => true, 'dns' => false])
    {
        global $base_url;

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

        drush_set_context('DRUSH_URI', 'http://' . $hostname . ':' . $uri['port']);

        // We delete any registered files here, since they are not caught by Ctrl-C.
        _drush_delete_registered_files();

        // We set the effective base_url, since we have now detected the current site,
        // and need to ensure generated URLs point to our runserver host.
        // We also pass in the effective base_url to our auto_prepend_script via the
        // CGI environment. This allows Drupal to generate working URLs to this http
        // server, whilst finding the correct multisite from the HTTP_HOST header.
        $base_url = 'http://' . $addr . ':' . $uri['port'];
        $env['RUNSERVER_BASE_URL'] = $base_url;


        $link = Url::fromUserInput('/' . $path, ['absolute' => true])->toString();
        $this->logger()->notice(dt('HTTP server listening on !addr, port !port (see http://!hostname:!port/!path), serving site !site', ['!addr' => $addr, '!hostname' => $hostname, '!port' => $uri['port'], '!path' => $path, '!site' => drush_get_context('DRUSH_DRUPAL_SITE', 'default')]));
        // Start php built-in server.
        if (!empty($path)) {
            // Start a browser if desired. Include a 2 second delay to allow the server to come up.
            $this->startBrowser($link, 2);
        }
        // Start the server using 'php -S'.
        $router = Path::join(DRUSH_BASE_PATH, '/misc/d8-rs-router.php');
        $php = Drush::config()->get('php', 'php');
        $process = Drush::process([$php, '-S', $addr . ':' . $uri['port'], $router]);
        $process->setWorkingDirectory(Drush::bootstrapManager()->getRoot());
        $process->setTty(true);
        $process->mustRun();
    }

    /**
     * Determine the URI to use for this server.
     */
    public function uri($uri, $options)
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
     * @param string $uri
     *   String that can contain partial URI.
     *
     * @return array
     *   URI array as returned by parse_url.
     */
    public function parseUri($uri)
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
        $to_path = trim(substr($uri, 0, strpos($uri, '/')), '[]');
        $to_port = trim(substr($uri, 0, strrpos($uri, ':')), '[]');
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
