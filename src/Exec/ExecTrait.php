<?php
namespace Drush\Exec;

use Drush\Drush;

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
                $this->logger()->warning(dt('!host does not appear to be a resolvable hostname or IP, not starting browser. You may need to use the --uri option in your command or site alias to indicate the correct URL of this site.', array('!host' => $host)));
                return false;
            }
            if ($port) {
                $uri = str_replace($host, "localhost:$port", $uri);
            }
            if ($browser === true) {
                // See if we can find an OS helper to open URLs in default browser.
                if (drush_shell_exec('which xdg-open')) {
                    $browser = 'xdg-open';
                } else if (drush_shell_exec('which open')) {
                    $browser = 'open';
                } else if (!drush_has_bash()) {
                    $browser = 'start';
                } else {
                    // Can't find a valid browser.
                    $browser = false;
                }
            }
            $prefix = '';
            if ($sleep) {
                $prefix = 'sleep ' . $sleep . ' && ';
            }
            if ($browser) {
                $this->logger()->success(dt('Opening browser !browser at !uri', array('!browser' => $browser, '!uri' => $uri)));
                if (!Drush::simulate()) {
                    $pipes = array();
                    proc_close(proc_open($prefix . $browser . ' ' . drush_escapeshellarg($uri) . ' 2> ' . drush_bit_bucket() . ' &', array(), $pipes));
                }
                return true;
            }
        }
        return false;
    }
}
