<?php

/**
 * This file is included very early. See autoload.files in composer.json and
 * https://getcomposer.org/doc/04-schema.md#files
 */

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;

/**
 * Load any .env file. See /.env.example.
 */
$dotenv = new Dotenv(dirname(__DIR__));
try {
    $dotenv->load();
} catch (InvalidPathException $e) {
    // Do nothing. Production environments rarely use .env files.
}
