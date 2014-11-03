<?php
/**
* Implements hook_drush_command().
*/

function memcache_drush_command(){
  $items['memcache-server'] = array(
    'description' => 'Memcache Server',
    'outputformat' => array(
      'default' => 'table',
      'pipe-format' => 'var-export',
      'field-labels' => array('memcache' => 'Memcache', 'port' => 'Port'),
      'column-widths' => array('memcache' => 16, 'port' => 16),
      'output-data-type' => 'format-table',
    ),
    'examples' => array(
      'drush mems' => 'List memcache servers and ports.',
    ),
    'aliases' => array('mems','mem-s'),
  );
  $items['memcache-clear'] = array(
    'description' => 'Memcache Cache Clear',
    'examples' => array(
      'drush memcc' => 'Clear memcache.',
    ),
    'aliases' => array('memcc'),
  );
  $items['memcache-command'] = array(
    'description' => 'Memcache Command',
    'examples' => array(
      'drush mem' => 'List all avaiable commands.',
      'drush mem st' => 'List memcache status.',
      'drush mem ss or statusslabs' => 'List memcache status slabs.',
      'drush mem si or statusitems' => 'List memcache status items.',
    ),
    'aliases' => array('mem'),
  );
 
  return $items;
}
/**
* Memcache Server information
*/
function memcache_server(){
  global $conf;
  $servers = array(); 
  if (empty($conf['memcache_servers'])) {
    die('Memcache server not configured.');
  } else {
    $keys = array_keys($conf['memcache_servers']);
    $i = 0;
    foreach($keys as $key){
      $ky = explode(":",$key);
      $servers[$i]['memcache'] = $ky[0];
      $servers[$i]['port'] = $ky[1];
      $i++;
    }
    return $servers;
  }

}

/**
* Callback for Memcache Server information command
*/
function drush_memcache_server(){
  $servers = memcache_server();
  return $servers;
}
/**
* Callback for memcache cache clear 
*/
function drush_memcache_clear(){
  $mems = memcache_server();
  foreach($mems as $mem){
    $flush = 'echo "flush_all" | nc '.$mem['memcache'].' '.$mem['port'];
    $result = shell_exec($flush);
    print $result;
  }

}
/**
* Callback for memcache command output 
*/
function drush_memcache_command(){
  $mems = memcache_server();
  if ($args = func_get_args()) {
    $args[0] = drush_variable_name_adjust($args[0]);
    $cmd = trim($args[0]); 
  } else {
     print "Enter a number to choose command to run.\n";    
     print "[0]: Cancel\n";
     print "[1]: Stats\n";
     print "[2]: Stats Slabs\n";
     print "[3]: Stats Items\n";
     $command =  (int)fgets(STDIN);
  }
  switch($command){
    case 0:
      break;
    case 1:
      $cmds = "stats";
      break;
    case 2:
      $cmds = "stats slabs";
      break;
    case 3:
      $cmds = "stats items";
      break;
    default:
      break;
  }
  switch($cmd){
    case "stats":
    case "st":
      $cmds = "stats";
      break;
    case "statsslabs":
    case "ss":
      $cmds = "stats slabs";
      break;
    case "statusitems":
    case "si":
      $cmds = "stats items";
      break;
    default:
      break;
  }
  if(!empty($cmds)) {
    foreach($mems as $mem){
      $cstring = 'echo '.$cmds.' | nc '.$mem['memcache'].' '.$mem['port'];
      $result = shell_exec($cstring);
    }

    print $result;
  }

}
