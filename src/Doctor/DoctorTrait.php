<?php

declare(strict_types=1);

namespace Drush\Doctor;

use Drush\Boot\BootstrapManager;

/**
 * Helpers for commands that delegate to dr, Drupal core's CLI.
 */
trait DoctorTrait
{
    /**
     * Environment variables that make dr select the same site as Drush.
     *
     * dr reads DRUPAL_URI for the commands it boots Drupal for. Its
     * cache:rebuild builds the request from $_SERVER instead, which in CLI
     * holds the environment, so the host is passed as HTTP_HOST as well.
     */
    protected function siteEnvironment(BootstrapManager $bootstrapManager): array
    {
        $uri = $bootstrapManager->getUri();
        if (!is_string($uri) || $uri === '') {
            return [];
        }
        if (!str_contains($uri, '://')) {
            $uri = 'http://' . $uri;
        }
        $env = ['DRUPAL_URI' => $uri];
        if ($host = parse_url($uri, PHP_URL_HOST)) {
            $port = parse_url($uri, PHP_URL_PORT);
            $env['HTTP_HOST'] = $host . ($port ? ':' . $port : '');
        }
        return $env;
    }
}
