<?php

namespace Drush\Command;

interface CommandfilesInterface {
  function add($commandfile);
  function get();
  function deferred();
  function sort();
}
