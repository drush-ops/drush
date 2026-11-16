<?php

/**
 * @file
 * Serves the DDEV site from sites/dev, where `composer sut:si` installs it.
 *
 * DDEV_HOSTNAME is set only inside DDEV, and may list several hostnames.
 */

declare(strict_types=1);

foreach (array_filter(explode(',', (string) getenv('DDEV_HOSTNAME'))) as $host) {
    $sites[$host] = 'dev';
}
