<?php

/**
 * @file
 * make-lock command implementation.
 */

/**
 * Command callback for make-lock.
 */
function drush_make_lock($makefile) {
  make_generate_from_makefile(drush_get_option('result-file'), $makefile);
}
