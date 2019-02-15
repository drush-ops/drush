<?php
namespace Unish\Utils;

// We want to avoid using symfony/filesystem in the isolation tests.
trait FSUtils
{
    public function removeDir($dir)
    {
        $files = array_diff(scandir($dir), ['.','..']);
        foreach ($files as $file) {
            if (is_dir("$dir/$file") && !is_link("$dir/$file")) {
                $this->removeDir("$dir/$file");
            } else {
                unlink("$dir/$file");
            }
        }
        return rmdir($dir);
    }
}
