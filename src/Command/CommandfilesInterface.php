<?php

namespace Drush\Command;

interface CommandfilesInterface
{
    public function add($commandfile);
    public function get();
    public function deferred();
    public function sort();
}
