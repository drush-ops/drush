<?php

/**
 * @file
 * The last remnant of Drush backend API. Removed in Drush 10.
 *
 * When a drush command is called with the --backend option,
 * it will buffer all output, and instead return a JSON encoded
 * string containing all relevant information on the command that
 * was just executed.
 *
 * Through this mechanism, it is possible for Drush commands to
 * invoke each other.
 *
 * There are many cases where a command might wish to call another
 * command in its own process, to allow the calling command to
 * intercept and act on any errors that may occur in the script that
 * was called.
 *
 * A simple example is if there exists an 'update' command for running
 * update.php on a specific site. The original command might download
 * a newer version of a module for installation on a site, and then
 * run the update script in a separate process, so that in the case
 * of an error running a hook_update_n function, the module can revert
 * to a previously made database backup, and the previously installed code.
 *
 * By calling the script in a separate process, the calling script is insulated
 * from any error that occurs in the called script, to the level that if a
 * php code error occurs (ie: misformed file, missing parenthesis, whatever),
 * it is still able to reliably handle any problems that occur.
 *
 * This is nearly a RESTful API. @see http://en.wikipedia.org/wiki/REST
 *
 * Instead of :
 *   http://[server]/[apipath]/[command]?[arg1]=[value1],[arg2]=[value2]
 *
 * It will call :
 *  [apipath] [command] --[arg1]=[value1] --[arg2]=[value2] --backend
 *
 * [apipath] in this case will be the path to the drush.php file.
 * [command] is the command you would call, for instance 'status'.
 *
 * GET parameters will be passed as options to the script.
 * POST parameters will be passed to the script as a JSON encoded associative array over STDIN.
 *
 * Because of this standard interface, Drush commands can also be executed on
 * external servers through SSH pipes, simply by prepending, 'ssh username@server.com'
 * in front of the command.
 *
 * If the key-based ssh authentication has been set up between the servers,
 * this will just work.  By default, drush is configured to disallow password
 * authentication; if you would like to enter a password for every connection,
 * then in your drushrc.php file, set $options['ssh-options'] so that it does NOT
 * include '-o PasswordAuthentication=no'.  See examples/example.drushrc.php.
 *
 * The results from backend API calls can be fetched via a call to
 * drush_backend_get_result().
 */

use Drush\Drush;
use Drush\Log\LogLevel;
use Drush\Preflight\PreflightArgs;
use Drush\Runtime\Runtime;

/**
 * Identify the JSON encoded output from a command.
 *
 * Note that Drush now outputs a null ("\0") before the DRUSH_BACKEND_OUTPUT_DELIMITER,
 * but this null occurs where this constant is output rather than being
 * included in the define.  This is done to maintain compatibility with
 * older versions of Drush, so that Drush-7.x can correctly parse backend messages
 * from calls made to Drush-5.x and earlier.  The null is removed via trim().
 */
define('DRUSH_BACKEND_OUTPUT_START', 'DRUSH_BACKEND_OUTPUT_START>>>');
define('DRUSH_BACKEND_OUTPUT_DELIMITER', DRUSH_BACKEND_OUTPUT_START . '%s<<<DRUSH_BACKEND_OUTPUT_END');

/**
 * Parse output returned from a Drush command.
 *
 * @param string
 *    The output of a drush command
 * @param integrate
 *    Integrate the errors and log messages from the command into the current process.
 * @param outputted
 *    Whether output has already been handled.
 *
 * @return
 *   An associative array containing the data from the external command, or the string parameter if it
 *   could not be parsed successfully.
 */
function drush_backend_parse_output($string, $backend_options = [], $outputted = FALSE) {
    $regex = sprintf(DRUSH_BACKEND_OUTPUT_DELIMITER, '(.*)');

    preg_match("/$regex/s", $string, $match);

    if (!empty($match) && $match[1]) {
        // we have our JSON encoded string
        $output = $match[1];
        // remove the match we just made and any non printing characters
        $string = trim(str_replace(sprintf(DRUSH_BACKEND_OUTPUT_DELIMITER, $match[1]), '', $string));
    }

    if (!empty($output)) {
        $data = json_decode($output, TRUE);
        if (is_array($data)) {
            return $data;
        }
    }
    return $string;
}

/**
 * The backend result is the original PHP data structure (usually an array)
 * used to generate the output for the current command.
 */
function drush_backend_set_result($value) {
    // Do nothing. Kept for command backward compat.
}
