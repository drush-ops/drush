<?php

namespace Drush\Log;

/**
 * Additional log levels that Drush uses for historical reasons.
 * Standard log levels should be preferred.
 */
class LogLevel extends \Psr\Log\LogLevel
{
    // Things that happen early on.  Like 'notice'
    const BOOTSTRAP = 'bootstrap';
    const PREFLIGHT = 'preflight';

    // Notice that the user is cancelling an operation. Like 'warning'
    const CANCEL = 'cancel';

    // Various 'success' messages.  Like 'notice'
    const OK = 'ok';
    const COMPLETED = 'completed'; // Deprecated: use 'SUCCESS' or drush_set_error

    // Means the command was successful. Should appear at most once
    // per command (perhaps more if subcommands are executed, though).
    // Like 'notice'.
    const SUCCESS = 'success';

    // synonyms for 'notice'
    const STATUS = 'status'; // Deprecated. Use 'notice'
    const MESSAGE = 'message'; // Deprecated. Use 'notice'
}
