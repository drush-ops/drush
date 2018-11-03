<?php

/**
 * @file
 * Fake an HTTPS request, for use during testing.
 *
 * @todo Fix this to use a new request rather than modifying server variables,
 *   see http.php.
 */

use Drupal\Core\Test\TestKernel;
use Symfony\Component\HttpFoundation\Request;

chdir('../../../..');

$autoloader = require_once 'autoload.php';

// Change to HTTPS.
$_SERVER['HTTPS'] = 'on';
foreach ($_SERVER as &$value) {
  $value = str_replace('core/modules/system/tests/https.php', 'index.php', $value);
  $value = str_replace('http://', 'https://', $value);
}

$kernel = new TestKernel('testing', $autoloader, TRUE);

$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();

$kernel->terminate($request, $response);
