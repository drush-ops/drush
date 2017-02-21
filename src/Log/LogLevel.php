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

    // Highly verbose messages that are not always interesting.
    // Displayed only when --debug and --verbose specified together.
    const DEBUG_NOTIFY = 'debugnotify';

    // Means the command was successful. Should appear at most once
    // per command (perhaps more if subcommands are executed, though).
    // Like 'notice'.
    const SUCCESS = 'success';

    // Batch processes. Like 'notice'
    const BATCH = 'batch';
}
