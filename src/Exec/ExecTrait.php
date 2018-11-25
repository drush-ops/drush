<?php
namespace Drush\Exec;

use Consolidation\SiteProcess\Util\Shell;
use Drush\Drush;
use Drush\Log\LogLevel;

trait ExecTrait
{
    /**
     * Starts a background browser/tab for the current site or a specified URL.
     *
     * Uses a non-blocking proc_open call, so Drush execution will continue.
     *
     * @param $uri
     *   Optional URI or site path to open in browser. If omitted, or if a site path
     *   is specified, the current site home page uri will be prepended if the sites
     *   hostname resolves.
     * @return
     *   TRUE if browser was opened, FALSE if browser was disabled by the user or a,
     *   default browser could not be found.
     */
    public function startBrowser($uri = null, $sleep = false, $port = false, $browser = true)
    {
        if ($browser) {
            // We can only open a browser if we have a DISPLAY environment variable on
            // POSIX or are running Windows or OS X.
            if (!Drush::simulate() && !getenv('DISPLAY') && !drush_is_windows() && !drush_is_osx()) {
                $this->logger()->info(dt('No graphical display appears to be available, not starting browser.'));
                return false;
            }
            $host = parse_url($uri, PHP_URL_HOST);
            if (!$host) {
                // Build a URI for the current site, if we were passed a path.
                $site = drush_get_context('DRUSH_URI');
                $host = parse_url($site, PHP_URL_HOST);
                $uri = $site . '/' . ltrim($uri, '/');
            }
            // Validate that the host part of the URL resolves, so we don't attempt to
            // open the browser for http://default or similar invalid hosts.
            $hosterror = (gethostbynamel($host) === false);
            $iperror = (ip2long($host) && gethostbyaddr($host) == $host);
            if (!Drush::simulate() && ($hosterror || $iperror)) {
                $this->logger()->warning(dt('!host does not appear to be a resolvable hostname or IP, not starting browser. You may need to use the --uri option in your command or site alias to indicate the correct URL of this site.', ['!host' => $host]));
                return false;
            }
            if ($port) {
                $uri = str_replace($host, "localhost:$port", $uri);
            }
            if ($browser === true) {
                // See if we can find an OS helper to open URLs in default browser.
                if (drush_which('xdg-open')) {
                    $browser = 'xdg-open';
                } else if (drush_which('open')) {
                    $browser = 'open';
                } else if (!drush_has_bash()) {
                    $browser = 'start';
                } else {
                    // Can't find a valid browser.
                    $browser = false;
                }
            }

            if ($browser) {
                drush_log(dt('Opening browser !browser at !uri', ['!browser' => $browser, '!uri' => $uri]), LogLevel::INFO);
                $args = [];
                if (!Drush::simulate()) {
                    if ($sleep) {
                        $args = ['sleep', $sleep, Shell::op('&&')];
                    }
                    // @todo We implode because quoting is messing up the sleep.
                    $process = Drush::process(implode(' ', array_merge($args, [$browser, $uri])));
                    $process->setTty(true);
                    $process->run();
                }
                return true;
            }
        }
        return false;
    }

    /*
     * Determine if program exists on user's PATH.
     *
     * @return bool
     */
    public static function programExists($program)
    {
        $process = Drush::process(['command', '-v', $program]);
        $process->run();
        return $process->isSuccessful();
    }
}
