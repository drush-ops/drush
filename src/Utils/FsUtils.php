<?php

namespace Drush\Utils;

use Drush\Drush;
use Drush\Sql\SqlBase;
use finfo;
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
    public static function getBackupDir($subdir = null): string
    {
        $parent = self::getBackupDirParent();

        // Try to use db name as subdir if none was provided.
        if (empty($subdir)) {
            if ($sql = SqlBase::create()) {
                $db_spec = $sql->getDbSpec();
                $subdir = $db_spec['database'];
            }
        }

        // Add in the subdirectory if it was provided or inferred.
        if (!empty($subdir)) {
            $parent = Path::join($parent, $subdir);
        }

        // Save the date to be used in the backup directory's path name.
        $date = gmdate('YmdHis', $_SERVER['REQUEST_TIME']);
        return Path::join(
            $parent,
            $date
        );
    }

    /**
     * Get the base dir where our backup directories will be stored. Also stores CLI history file.
     *
     * @return
     *   A path to the backup directory parent
     * @throws \Exception
     */
    public static function getBackupDirParent()
    {
        // Try in order:
        //  1. The user-specified backup directory from drush.yml config file
        //  2. The 'drush-backups' directory in $HOME
        //  3. The 'drush-backups' directory in tmp
        $candidates = [
            Drush::config()->get('drush.paths.backup-dir'),
            Path::join(
                Drush::config()->home(),
                'drush-backups'
            ),
            Path::join(
                Drush::config()->tmp(),
                'drush-backups'
            ),
        ];

        // Return the first usable candidate
        foreach ($candidates as $dir) {
            if (self::isUsableDirectory($dir)) {
                return $dir;
            }
        }

        throw new \Exception('No viable backup directory found.');
    }

    /**
     * Determine if the specified location is writable, or if a writable
     *   directory could be created at that path.
     *
     * @param $dir
     *   Path to directory that we are considering using
     *
     * @return bool|string
     */
    public static function isUsableDirectory(?string $dir)
    {
        // This directory is not usable if it is empty or if it is the root.
        if (empty($dir) || (dirname($dir) === $dir)) {
            return false;
        }

        // If the directory already exists and is writable, then it is usable.
        if (is_writable($dir)) {
            return $dir;
        }

        // If the directory exists (and is not writable), then it is not usable.
        if (file_exists($dir)) {
            return false;
        }

        // Otherwise, this directory is usable (could be created) if its
        // parent directory is usable.
        return self::isUsableDirectory(dirname($dir));
    }

    /**
     * Prepare a backup directory.
     *
     * @param string $subdir
     *   A string naming the subdirectory of the backup directory.
     *
     *   Path to the specified backup directory.
     * @throws \Exception
     */
    public static function prepareBackupDir($subdir = null): string
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
     *   The canonicalized absolute pathname.
     */
    public static function realpath(string $path): string
    {
        $realpath = realpath($path);
        return $realpath ?: $path;
    }

    /**
     * Check whether a file is a supported tarball.
     *
     * @param string $path
     *
     * @return string|bool
     *   The file content type if it's a tarball. FALSE otherwise.
     */
    public static function isTarball(string $path)
    {
        $content_type = self::getMimeContentType($path);
        $supported = [
            'application/x-bzip2',
            'application/x-gzip',
            'application/gzip',
            'application/x-tar',
            'application/x-zip',
            'application/zip',
        ];
        if (in_array($content_type, $supported)) {
            return $content_type;
        }
        return false;
    }

    /**
     * Determines the MIME content type of the specified file.
     *
     * The power of this function depends on whether the PHP installation
     * has either mime_content_type() or finfo installed -- if not, only tar,
     * gz, zip and bzip2 types can be detected.
     *
     * @param string $path
     *
     * @return string|bool|null
     *   The MIME content type of the file.
     */
    public static function getMimeContentType(string $path)
    {
        $content_type = false;
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $content_type = $finfo->file($path);
            if ($content_type == 'application/octet-stream') {
                Drush::logger()->debug(dt('Mime type for !file is application/octet-stream.', ['!file' => $path]));
                $content_type = false;
            }
        }
        // If apache is configured in such a way that all files are considered
        // octet-stream (e.g with mod_mime_magic and an http conf that's serving all
        // archives as octet-stream for other reasons) we'll detect mime types on our
        //  own by examining the file's magic header bytes.
        if (!$content_type) {
            Drush::logger()->debug(dt('Examining !file headers.', ['!file' => $path]));
            if ($file = fopen($path, 'rb')) {
                $first = fread($file, 2);
                fclose($file);

                if ($first !== false) {
                    // Interpret the two bytes as a little endian 16-bit unsigned int.
                    $data = unpack('v', $first);
                    switch ($data[1]) {
                        case 0x8b1f:
                            // First two bytes of gzip files are 0x1f, 0x8b (little-endian).
                            // See http://www.gzip.org/zlib/rfc-gzip.html#header-trailer
                            $content_type = 'application/x-gzip';
                            break;

                        case 0x4b50:
                            // First two bytes of zip files are 0x50, 0x4b ('PK') (little-endian).
                            // See http://en.wikipedia.org/wiki/Zip_(file_format)#File_headers
                            $content_type = 'application/zip';
                            break;

                        case 0x5a42:
                            // First two bytes of bzip2 files are 0x5a, 0x42 ('BZ') (big-endian).
                            // See http://en.wikipedia.org/wiki/Bzip2#File_format
                            $content_type = 'application/x-bzip2';
                            break;

                        default:
                            Drush::logger()->debug(dt('Unable to determine mime type from header bytes 0x!hex of !file.', ['!hex' => dechex($data[1]), '!file' => $path,]));
                    }
                } else {
                    Drush::logger()->warning(dt('Unable to read !file.', ['!file' => $path]));
                }
            } else {
                Drush::logger()->warning(dt('Unable to open !file.', ['!file' => $path]));
            }
        }

        // 3. Lastly if above methods didn't work, try to guess the mime type from
        // the file extension. This is useful if the file has no identifiable magic
        // header bytes (for example tarballs).
        if (!$content_type) {
            Drush::logger()->debug(dt('Examining !file extension.', ['!file' => $path]));

            // Remove querystring from the filename, if present.
            $path = basename(current(explode('?', $path, 2)));
            $extension_mimetype = [
                '.tar'     => 'application/x-tar',
                '.sql'     => 'application/octet-stream',
            ];
            foreach ($extension_mimetype as $extension => $ct) {
                if (substr($path, -strlen($extension)) === $extension) {
                    $content_type = $ct;
                    break;
                }
            }
        }
        return $content_type;
    }
}
