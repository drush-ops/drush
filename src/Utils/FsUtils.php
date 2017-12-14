<?php

namespace Drush\Utils;

use Drush\Drush;
use Drush\Sql\SqlBase;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

class FsUtils
{

    /**
     * Decide where our backup directory should go
     *
     * @param string $subdir
     *   The name of the desired subdirectory(s) under drush-backups.
     *   Usually a database name.
     *
     * @return
     *   A path to the backup directory.
     * @throws \Exception
     */
    public static function getBackupDir($subdir = null)
    {
        // Try to use db name as subdir if none was provided.
        if (empty($subdir)) {
            $subdir = 'unknown';
            if ($sql = SqlBase::create()) {
                $db_spec = $sql->getDbSpec();
                $subdir = $db_spec['database'];
            }
        }

        // Save the date to be used in the backup directory's path name.
        $date = gmdate('YmdHis', $_SERVER['REQUEST_TIME']);
        return Path::join(
            Drush::config()->home(),
            'drush-backups',
            $subdir,
            $date
        );
    }

    /**
     * Prepare a backup directory.
     *
     * @param string $subdir
     *   A string naming the subdirectory of the backup directory.
     *
     * @return string
     *   Path to the specified backup directory.
     * @throws \Exception
     */
    public static function prepareBackupDir($subdir = null)
    {
        $fs = new Filesystem();
        $backup_dir = self::getBackupDir($subdir);
        $fs->mkdir($backup_dir);
        return $backup_dir;
    }

    /**
     * Returns canonicalized absolute pathname.
     *
     * The difference between this and PHP's realpath() is that this will
     * return the original path even if it doesn't exist.
     *
     * @param string $path
     *   The path being checked.
     *
     * @return string
     *   The canonicalized absolute pathname.
     */
    public static function realpath($path)
    {
        $realpath = realpath($path);
        return $realpath ?: $path;
    }
}
