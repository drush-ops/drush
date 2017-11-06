<?php
namespace Drush\DrushConfig;

use Drush\Drush;

/**
 * Accessors for common Drush config keys.
 */
class DrushConfig
{
    public static function cwd()
    {
        return Drush::config()->get('env.cwd');
    }

    public static function home()
    {
        return Drush::config()->get('env.home');
    }

    public static function user()
    {
        return Drush::config()->get('env.user');
    }

    public static function isWindows()
    {
        return Drush::config()->get('env.is-windows');
    }

    public static function tmp()
    {
        return Drush::config()->get('env.tmp');
    }
}
