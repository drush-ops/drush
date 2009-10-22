<?php

/*
 * @file
 * Use this file as a php scratchpad for your Drupal site. You might want to
 * load a node, change it, and call node_save($node), for example. If you have
 * used the Execute PHP feature of devel.module, this is the drush equivalent.
 * 
 * You may edit this file with whatever php you choose. Then execute the file
 * using `drush script scratch.php`. That command will bootstrap your drupal
 * site and then run the php below.
 * 
 * The script command enables to store your script files wherever you wish and
 * will help you list all of them should you collection grow. See its help.
 *
 */

// Just some ideas to get the juices flowing.
drush_print_r(menu_get_item());
drush_print_r(user_roles());
drush_print_r($GLOBALS['user']);