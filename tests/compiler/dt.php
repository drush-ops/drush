<?php
$drupal_root="funky";
function dt($string, $args = array()) {
	 print string;
	 print $args;
	 return "OK";
}

function drush_bootstrap_error($code, $message = null) {
	 print $code;
	 print $message;
	  return FALSE;
}

print "The directory !drupal_root does not contain a valid Drupal installation";

 drush_bootstrap_error('DRUSH_INVALID_DRUPAL_ROOT', "The directory !drupal_root does not contain a valid Drupal installation");

 drush_bootstrap_error('DRUSH_INVALID_DRUPAL_ROOT', dt("The directory !drupal_root does not contain a valid Drupal installation", array('!drupal_root' => $drupal_root)));

print dt("The directory !drupal_root does not contain a valid Drupal installation");
print dt("The directory !drupal_root does not contain a valid Drupal installation", array('!drupal_root' => $drupal_root));
?>