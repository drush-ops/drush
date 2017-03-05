#!/usr/bin/env php
<?php

/**
 * This script:
 *   - Builds the site-Under-Test
 *   - Runs phpunit.
 *
 * Supported arguments and options are the same as `phpunit`.
 */

passthru(__DIR__. '/unish.sut.php', $return);
if ($return) exit($return);
passthru(__DIR__. '/unish.phpunit.php', $return);
if ($return) exit($return);