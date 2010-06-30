<?php
// $Id$

function drush_make_download_factory($project) {
  $class = 'DrushMakeDownload_' . $project->download['type'];
  if (class_exists($class)) {
    return new $class($project);
  }
  else {
    return FALSE;
  }
}

abstract class DrushMakeDownload {
  var $project;
  var $path;

  function __construct($project) {
    $this->project = $project;
    $this->tmp_path = drush_make_tmp();
  }

  abstract function download();
}

class DrushMakeDownload_CVS extends DrushMakeDownload {
  function download() {
    $name = $this->project->name;
    $download = $this->project->download;
    if (!empty($download['module'])) {
      if (drush_get_option('working-copy')) {
        if (isset($_ENV['CVSROOT'])) {
          $this->project->download['root'] = $download['root'] = trim($_ENV['CVSROOT']);
        }
        else {
          drush_log(dt('Please set the CVSROOT variable in your shell environment when using the --working-copy option.'), 'ok');
        }
      }
      // Fallback to anonymous @ cvs.drupal.org
      if (!isset($download['root'])) {
        $this->project->download['root'] = $download['root'] = ":pserver:anonymous:anonymous@cvs.drupal.org:/cvs/drupal-contrib";
      }

      // Checkout or export the module. CVS can't use absolute paths for named
      // directories, so change into the directory just above the final
      // destination for the checkout. 
      $cd_to_directory = dirname($this->project->download_location);
      $destination_directory = basename($this->project->download_location);

      $command = 'cvs -d%s ' . (drush_get_option('working-copy') ? 'checkout' : 'export') . ' -d%s';
      $args = array($download['root'], $destination_directory);
      if (isset($download['revision'])) {
        $command .= ' -r %s';
        $args[] = $download['revision'];
      }
      if (isset($download['date'])) {
        $command .= ' -D %s';
        $args[] = $download['date'];
      }
      $args[] = $download['module'];
      $command .= ' %s';

      array_unshift($args, $command);
      array_unshift($args, dirname($this->project->download_location));
      if (call_user_func_array('drush_make_cd', $args)) {
         drush_log(dt('%project downloaded from %module.', array('%project' => $name, '%module' => $download['module'])), 'ok');
        return $this->project->download_location;
      }
    }
    else {
      $download['module'] = dt("unspecified module");
    }
    drush_make_error('DOWNLOAD_ERROR', dt('Unable to download %project from %root %module.', array('%project' => $name, '%root' => $download['root'], '%module' => $download['module'])));
    return FALSE;
  }
}


class DrushMakeDownload_Get extends DrushMakeDownload {
  function download() {
    if ($filename = $this->downloadFile()) {
      $this->filename = $filename;
      return $this->unpack();
    }
    return FALSE;
  }

  protected function downloadFile() {
    $name = $this->project->name;
    $filename = FALSE;
    if (!empty($this->project->download['url'])) {
      $url = $this->project->download['url'];
      // Download the project -- try wget, fall back to curl.
      if (drush_get_context('DRUSH_SIMULATE')) {
        $filename = t('file');
      }
      else {
        drush_shell_exec("ls %s", $this->tmp_path);
        $files = drush_shell_exec_output();
        if (drush_make_cd($this->tmp_path, 'wget %s', $url)) {
          drush_shell_exec("ls %s", $this->tmp_path);
          $files_new = drush_shell_exec_output();
          $diff = array_diff($files_new, $files);
          $filename = array_shift($diff);
        }
        elseif (drush_shell_exec('curl -I -L %s', $url)) {
          $headers = drush_shell_exec_output();
          foreach ($headers as $header) {
            if (preg_match('/Location:(.*)/', $header, $matches)) {
              $filename = basename($matches[1]);
            }
          }
          if (!$filename) {
            $filename = basename($url);
          }
          if (!drush_shell_exec('curl -L -o %s %s', $this->tmp_path . '/' . $filename, $url)) {
            $filename = FALSE;
          }
        }
      }
      if ($filename) {
        drush_log(dt('%project downloaded from %url.', array('%project' => $name, '%url' => $url)), 'ok');
        return $this->tmp_path . '/' . $filename;
      }
      $filename = dt('file');
    }
    else {
      $url = dt("unspecified location");
    }
    drush_make_error('DOWNLOAD_ERROR', dt('Unable to download %filename from %url.', array('%filename' => $filename, '%url' => $url)));
    return FALSE;
  }

  function unpack() {
    $extension = array_pop(explode('.', $this->filename));
    $gzip = FALSE;
    switch ($extension) {
      case 'gz':
      case 'tgz':
        // I'd like to just use tar -z, but apparently it breaks on windoze. Why do they always have to ruin everything?
        $this->unpackGzip();
        break;
      case 'tar':
        $this->unpackTar();
        break;
      case 'zip':
        $this->unpackZip();
        break;
      default:
        drush_make_error('DOWNLOAD_ERROR', dt('Unable to unpack %file', array('%file' => $this->filename)));
    }
  }

  protected function unpackTar() {
    // Tarbomb detection.
    drush_shell_exec('tar -tf %s', $this->filename);
    $lines = drush_shell_exec_output();
    if ($lines && !$this->protectBomb($lines)) {
      $strip = '';
    }
    else {
      // Detect whether the user uses --strip-path or --strip-components
      $strip_option = drush_get_option('strip-option', FALSE);
      if (!$strip_option) {
        drush_shell_exec("man tar | grep strip-component");
        $info = drush_shell_exec_output();
        if ($info) {
          $strip_option = 'component';
        }
        else {
          drush_shell_exec("tar --help | grep strip-path");
          $info = drush_shell_exec_output();
          if ($info) {
            $strip_option = 'path';
          }
          else {
            drush_make_error('CONFIGURATION_ERROR', dt('Unable to find a strip option in your version of tar'));
          }
        }
        drush_set_option('strip-option', $strip_option);
      }
      $strip = '--strip-' . $strip_option . ' 1';
    }


    // Untar directly to final resting place -- allows tarballs to be
    // unpacked directly over an existing structure.
    drush_shell_exec('tar -x ' . $strip . ' -C %s -f %s', $this->project->download_location, $this->filename);

    // Remove the tarball.
    if (file_exists($this->filename)) {
      drush_shell_exec('rm %s', $this->filename);
    }
  }

  protected function unpackGzip() {
    // Find out where contents will end up. Retrieve last column of output using awk.
    drush_shell_exec("gzip --list %s | awk '{print $4;}'", $this->filename);
    $info = drush_shell_exec_output();
    if ($info) {
      $info = array_pop($info);
      $matches = array();
      preg_match('/[a-zA-Z0-9.\-_,]*.tar/', $info, $matches);
      if (isset($matches[0])) {
        $file = dirname($this->filename) . '/' . $matches[0];
        // Unzip it and then delete the tar file.
        drush_shell_exec('gzip -d %s', $this->filename);
        $this->filename = $file;
        return $this->unpackTar();
      }
    }
    drush_make_error('PACKAGE_ERROR', dt('Could not retrieve package information for %filename.', array('%filename' => $this->filename)));
    return;
  }

  protected function unpackZip() {
    // Find the main directory in the zip file.
    drush_shell_exec("unzip -l %s", $this->filename);
    $info = drush_shell_exec_output();
    if ($info) {
      foreach ($info as $line) {
        $matches = array();
        preg_match('/^\s+[0-9]+\s+[0-9-]+\s+[0-9:]+\s+(.*)$/', $line, $matches);
        if (isset($matches[1])) {
          $lines[] = $matches[1];
        }
      }
      $directory = $this->protectBomb($lines);
      if ($directory) {
        list($main_directory) = array_reverse(explode('/', $this->project->download_location));
        if ($main_directory == $directory) {
          // Directory names match up.
          drush_shell_exec("unzip %s -d %s", $this->filename, dirname($this->project->download_location));
        }
        else {
          // Directories don't match up. Use cp.
          drush_shell_exec('mkdir -p %s', $this->tmp_path . '/__unzip__');
          drush_shell_exec('unzip %s -d %s', $this->filename, $this->tmp_path . '/__unzip__');
          if (file_exists($this->filename)) {
            drush_shell_exec('rm %s', $this->filename);
          }
          if (is_dir($this->project->tmp_path . '/__unzip__' . $directory . '/__MACOSX')) {
            drush_shell_exec('rm -rf %s', $this->tmp_path . '/__unzip__' . $directory . '/__MACOSX');
          }
          drush_shell_exec('mv %s %s', $this->tmp_path . '/__unzip__/' . $directory, $this->project->tmp_path . '/__unzip__/' . $main_directory);
          drush_shell_exec('cp -Rf %s %s', $this->tmp_path . '/__unzip__/' . $main_directory, dirname($this->project->download_location));
          drush_shell_exec('rm -rf %s', $this->tmp_path . '/__unzip__');
        }
      }
      else {
        // It's a bomb, so we can just unpack it right at its destination
        // (warning: don't ever do this in real life).
        drush_shell_exec("unzip %s -d %s", $this->filename, $this->project->download_location);
      }
      if (file_exists($this->filename)) {
        drush_shell_exec('rm %s', $this->filename);
      }
      if (is_dir($this->project->download_location . '/__MACOSX')) {
        drush_shell_exec('rm -rf %s', $this->project->download_location . '/__MACOSX');
      }
    }
    else {
      drush_make_error('PACKAGE_ERROR', dt('Could not retrieve package information for %filename.', array('%filename' => $this->filename)));
    }
    return;
  }

  protected function protectBomb($lines) {
    $directory = FALSE;
    foreach ($lines as $line) {
      if (strpos($line, '/') !== FALSE) {
        list($dir) = explode('/', $line);
        if (!$directory) {
          $directory = $dir;
        }
        elseif ($dir != $directory) {
          // More than one root-level item detected. We're not safe - BOMB!
          return FALSE;
        }
      }
    }
    if (!$directory) {
      // If we didn't come upon anything, that is to say, they were only
      // root-level files, it's a bomb.
      return FALSE;
    }
    return $directory;
  }
}

class DrushMakeDownload_Post extends DrushMakeDownload_Get {
  protected function downloadFile() {
    $name = $this->project->name;
    $filename = dt('file');
    if (!empty($this->project->download['url'])) {
      $url = $this->project->download['url'];
      $post_data = $this->project->download['post_data'];
      $file_type = $this->project->download['file_type'];
      $filename = $this->tmp_path . "/" . basename($url);
      // Download the project -- try wget, fall back to curl.
      $filename = (empty($file_type)) ? $this->path . "/" . basename($url) : $this->path . basename($url) . '.' . $file_type;
      // Download the project -- try wget, fall back to curl.
      if (drush_get_context('DRUSH_SIMULATE') || drush_shell_exec('wget --post-data=%s %s -O %s', $post_data, $url, $filename) || drush_shell_exec('curl -d %s -o %s %s', $post_data, $filename, $url)) {
        drush_log(dt('%project downloaded from %url.', array('%project' => $name, '%url' => $url)), 'ok');
        return $filename;
      }
    }
    else {
      $url = dt("unspecified location");
    }
    drush_make_error('DOWNLOAD_ERROR', dt('Unable to download %filename from %url.', array('%filename' => $filename, '%url' => $url)));
    return FALSE;
  }
}

class DrushMakeDownload_Git extends DrushMakeDownload {
  function download() {
    $download = $this->project->download;
    $wc       = drush_get_option('working-copy');

    // check if branch option is set in info file, otherwise set default to master branch
    $this->project->download['branch'] = $download['branch'] = isset($download['branch']) ? $download['branch'] : 'master';
    // check if tag option is set in info file, otherwise we set it to false
    $this->project->download['tag'] = $download['tag'] = isset($download['tag']) ? $download['tag'] : FALSE;
    // check if specific revision is set in info file
    $this->project->download['revision'] = $download['revision'] = isset($download['revision']) ? $download['revision'] : FALSE;

    // only progress if we have a download url...
    if (!empty($download['url'])) {
      // split the given download url into pices
      $url_array = $this->getUrlParts($download['url']);

      // build url for git clone to support different protocols
      // currently all protocols seems to use the same url schema
      switch ($url_array['protocol']) {
        case 'git':
          // github uses two different urls, working copy urls using scp format
          // git@domain:repo export url format are git://domain/repo
          if ($wc) {
            // working copy is set
            $url = 'git@'. $url_array['host'] .':'. $url_array['resource'];
            break;
          }
        case 'ssh':
        case 'http':
        case 'https':
        case 'ftp':
        case 'ftps':
        case 'rsync':
        case 'file':
          // @TODO: implement port & user options
          $url = $url_array['protocol'] .'://'. $url_array['host'] .'/'. $url_array['resource'];
          break;

        default:
          drush_make_error('DOWNLOAD_ERROR', dt('unknown protocol @protocol in %project', array('@protocol' => $url_array['protocol'], '%project' => $name)));
          return false;
      }

      $tmp_location = $this->tmp_path . '/__git__/' . basename($this->project->download_location);

      drush_shell_exec('mkdir -p %s', $this->tmp_path . '/__git__/');

      // clone the given repository
      if (drush_shell_exec("git clone %s %s", $url, $tmp_location)) {
        drush_log(dt('%project cloned from %url.', array('%project' => $this->project->name, '%url' => $url)), 'ok');

        // GIT Checkout only work on a ready cloned repo. So we switch to branch or to tag (only if we have no branch) after cloneing.
        if ($download['branch'] !== 'master' || $download['tag'] || $download['revision']) {

          // get current directory (for move back later)
          $cwd = getcwd();
          // change path to working copy of cloned repo
          chdir($tmp_location);

          // Progress branch / tag / revision download. Ensure that only one option ist set (branch OR tag OR revision)
          // check if branch a other than master
          if ($download['branch'] !== 'master' && !$download['tag'] && !$download['revision']) {
            if (drush_shell_exec("git checkout -b %s origin/%s", $download['branch'], $download['branch'])) {
              drush_log(dt("Checked out branch %branch.", array('%branch' => $download['branch'])), 'ok');
            }
            else {
              drush_make_error('DOWNLOAD_ERROR', dt("Unable to check out branch %branch.", array('%branch' => $download['branch'])));
            }
          }
          // progress if: tag is set but not the others
          elseif ($download['branch'] == 'master' && $download['tag'] && !$download['revision']) {
            // @TODO: change checkout to refs path
            if (drush_shell_exec("git checkout refs/tags/%s", $download['tag'])) {
              drush_log(dt("Checked out tag %tag.", array('%tag' => $download['tag'])), 'ok');
            }
            else {
              drush_make_error('DOWNLOAD_ERROR', dt("Unable to check out tag %tag.", array('%tag' => $download['tag'])));
            }
          }
          // progress if: revision is set but not the others
          elseif ($download['branch'] == 'master' && !$download['tag'] && $download['revision']) {
            if (drush_shell_exec("git checkout %s", $download['revision'])) {
              drush_log(dt("Checked out revision %revision.", array('%revision' => $download['revision'])), 'ok');
            }
            else {
              drush_make_error('DOWNLOAD_ERROR', dt("Unable to checkout revision %revision", array('%revision' => $download['revision'])));
            }
          }
          // more than one option is set so we throw a error message
          else {
            drush_make_error('DOWNLOAD_ERROR', dt("You can only specific branch or tag or revision but not combined in make file."));
            return false;
          }
          // move back to last current directory (first line)
          chdir($cwd);
        }

        // Remove .git/ directory if working-copy flag was not specified.
        if (!$wc && file_exists($tmp_location . '/.git')) {
          drush_shell_exec("rm -rf %s", $tmp_location . '/.git');
        }
        drush_shell_exec('cp -Rf %s %s', $tmp_location, dirname($this->project->download_location));
        drush_shell_exec("rm -rf %s", dirname($tmp_location));
        return $name;
      }
      else {
        drush_make_error('DOWNLOAD_ERROR', dt('Unable to clone %project from %url.', array('%project' => $name, '%url' => $url)));
      }
    }
    else {
      $download['url'] = dt("unspecified location");
    }
    return FALSE;
  }

  /**
   * private helper function to split the given url into parts
   * protocol / port / site / resource
   */
  protected function getUrlParts($url) {
    $result = array();

    // Get the protocol, site and resource parts of the URL
    // original url = http://example.com/blog/index?name=foo
    // protocol = http://
    // site = example.com/
    // resource = blog/index?name=foo
    $regex = '#^(.*?//)*([\w\.\d]*)(:(\d+))*(/*)(.*)$#';
    $matches = array();
    preg_match($regex, $url, $matches);

    // Assign the matched parts of url to the result array
    $result['protocol'] = $matches[1];
    $result['port']     = $matches[4];
    $result['host']     = $matches[2];
    $result['resource'] = $matches[6];

    // clean up the site portion by removing the trailing /
    $result['host'] = preg_replace('#/$#', '', $result['host']);

    // clean up the protocol portion by removing the trailing ://
    $result['protocol'] = preg_replace('#://$#', '', $result['protocol']);

    return $result;
  }
}

class DrushMakeDownload_Bzr extends DrushMakeDownload {
  function download() {
    $name = $this->project->name;
    $download = $this->project->download;
    $tmp_location = $this->tmp_path . '/__bzr__/' . basename($this->project->download_location);
    drush_shell_exec('mkdir -p %s', dirname($tmp_location));
    if (!empty($download['url'])) {
      $args = array();
      $command = 'bzr';
      if (drush_get_option('working-copy')) {
        $command .= ' branch  --use-existing-dir';
      }
      else {
        $command .= ' export';
      }
      if (isset($download['revision'])) {
        $command .= ' -r %s';
        $args[] = $download['revision'];
      }
      $command .= ' %s %s';
      if (drush_get_option('working-copy')) {
        $args[] = $download['url'];
        $args[] = $tmp_location;
      }
      else {
        $args[] = $tmp_location;
        $args[] = $download['url'];
      }
      array_unshift($args, $command);
      if (call_user_func_array('drush_shell_exec', $args)) {
        drush_log(dt('%project downloaded from %url.', array('%project' => $name, '%url' => $download['url'])), 'ok');
        drush_shell_exec('cp -Rf %s %s', $tmp_location, dirname($this->project->download_location));
        drush_shell_exec('rm -rf %s', dirname($tmp_location));
        return $name;
      }
    }
    else {
      $download['url'] = dt("unspecified location");
    }
    drush_make_error('DOWNLOAD_ERROR', dt('Unable to download %project from %url.', array('%project' => $name, '%url' => $download['url'])));
    drush_shell_exec('rm -rf %s', dirname($tmp_location));
    return FALSE;
  }
}

class DrushMakeDownload_SVN extends DrushMakeDownload {
  function download() {
    $name = $this->project->name;
    $download = $this->project->download;
    $command = 'export';
    if (!empty($download['url'])) {
      if (drush_get_option('working-copy')) {
        $command = 'checkout';
      }

      $flags = isset($download['revision']) ? "-r{$download['revision']}" : '';

      if (!empty($download['username'])) {
        $result = drush_shell_exec("svn --force --non-interactive %s %s %s %s --username %s --password %s", $command, $flags, $download['url'], $this->project->download_location, $download['username'], $download['password']);
      }
      else {
        $result = drush_shell_exec("svn --force --non-interactive %s %s %s %s", $command, $flags, $download['url'], $this->project->download_location);
      }
      if ($result) {
        drush_log(dt('%project @command from %url.', array('%project' => $name, '@command' => $command, '%url' => $download['url'])), 'ok');
        return $this->project->download_location;
      }
      else {
        $download['url'] = dt("unspecified location");
      }
    }
    else {
      drush_make_error('DOWNLOAD_ERROR', dt('Unable to @command %project from %url.', array('%project' => $name, '@command' => $command, '%url' => $download['url'])));
      return FALSE;
    }
  }
}
