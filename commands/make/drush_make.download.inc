<?php
// $Id$

class DrushMakeDownload {
  var $project;
  var $path;

  function __construct($project) {
    $this->project = $project;
    $this->path = !empty($this->project->directory_name) ? $this->project->directory_name : $this->project->name;
  }

  function factory($project) {
    $class = 'DrushMakeDownload_' . $project->download['type'];
    if (class_exists($class)) {
      return new $class($project);
    }
    else {
      return FALSE;
    }
  }

  function download() {
    return FALSE;
  }
}

class DrushMakeDownload_CVS extends DrushMakeDownload {
  function download() {
    $name = $this->project->name;
    $tmp_path = $this->project->tmp_path;
    $download = $this->project->download;
    $command = 'export';
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
      $flags = isset($download['revision']) ? "-r{$download['revision']}" : '';

      if (drush_get_option('working-copy')) {
        $command = 'checkout';
      }

      // Checkout or export the module. CVS can't use absolute paths for named
      // directories, so change into the tmp directory before checking out. 
      if (drush_make_cd($tmp_path, "cvs -d%s %s -d%s %s %s", $download['root'], $command, $name, $flags, $download['module'])) {
        drush_log(dt('%project @command from %module.', array('%project' => $name, '@command' => $command, '%module' => $download['module'])), 'ok');
        return $name;
      }
    }
    else {
      $download['module'] = dt("unspecified module");
    }
    drush_make_error('DOWNLOAD_ERROR', dt('Unable to @command %project from %root %module.', array('%project' => $name, '@command'=> $command, '%root' => $download['root'], '%module' => $download['module'])));
    return FALSE;
  }
}

class DrushMakeDownload_Get extends DrushMakeDownload {
  protected function unpack($filename) {
    if (drush_get_context('DRUSH_SIMULATE')) {
      // Return a fake directory name so our simulation makes more sense.
      return 'simulated_project';
    }

    $extension = array_pop(explode('.', $filename));
    switch ($extension) {
      case 'gz':
      case 'tgz':
        return $this->unpack_gz($filename);
      case 'tar':
        return $this->unpack_tar($filename);
      case 'zip':
        return $this->unpack_zip($filename);
      default:
        drush_make_error('DOWNLOAD_ERROR', dt('Unable to unpack %file', array('%file' => $filename)));
    }
  }

  protected function unpack_tar($filename) {
    // Untar it and then delete the tar file.
    drush_shell_exec('tar -tf %s', $filename);
    $info = drush_shell_exec_output();
    if ($info) {
      list($first_line) = drush_shell_exec_output();
      list($directory) = explode('/', $first_line);
      drush_make_cd($this->project->tmp_path, 'tar -xf %s', $filename);
      drush_op('unlink', $filename);

      return $directory;
    }

    drush_make_error('PACKAGE_ERROR', dt('Could not retrieve package information for %filename.', array('%filename' => $filename)));
    return;
  }

  protected function unpack_gz($filename) {
    // Find out where contents will end up. Retrieve last column of output using awk.
    drush_shell_exec("gzip --list %s | awk '{print $4;}'", $filename);
    $info = drush_shell_exec_output();
    if ($info) {
      $info = array_pop($info);
      $matches = array();
      preg_match('/[a-zA-Z0-9.\-_,]*.tar/', $info, $matches);
      if (isset($matches[0])) {
        $file = $this->project->tmp_path . '/' . $matches[0];
        // Unzip it and then delete the tar file.
        drush_shell_exec('gzip -d %s', $filename);
        return $this->unpack_tar($file);
      }
    }
    drush_make_error('PACKAGE_ERROR', dt('Could not retrieve package information for %filename.', array('%filename' => $filename)));
    return;
  }

  protected function unpack_zip($filename) {
    // Find out where contents will end up.
    drush_shell_exec("unzip -l %s", $filename);
    $info = drush_shell_exec_output();
    if ($info) {
      foreach ($info as $line) {
        $matches = array();
        preg_match('/^\s+[0-9]+\s+[0-9-]+\s+[0-9:]+\s+(.*)$/', $line, $matches);
        if (isset($matches[1])) {
          $directory = $matches[1];
          break;
        }
      }
      if (isset($directory)) {
        drush_shell_exec("unzip %s -d %s", $filename, $this->project->tmp_path);
        if (file_exists($filename)) {
          drush_shell_exec('rm %s', $filename);
        }
        if (is_dir($this->project->tmp_path . '/__MACOSX')) {
          drush_shell_exec('rm -r %s', $this->project->tmp_path . '/__MACOSX');
        }
        return $directory;
      }
    }
    drush_make_error('PACKAGE_ERROR', dt('Could not retrieve package information for %filename.', array('%filename' => $filename)));
    return;
  }

  function download() {
    if ($filename = $this->downloadFile()) {
      return $this->unpack($filename);
    }
    return FALSE;
  }

  protected function downloadFile() {
    $name = $this->project->name;
    $tmp_path = $this->project->tmp_path;
    $filename = dt('file');
    if (!empty($this->project->download['url'])) {
      $url = $this->project->download['url'];
      $filename = "$tmp_path/" . basename($url);
      // Download the project -- try wget, fall back to curl.
      if (drush_get_context('DRUSH_SIMULATE') || drush_shell_exec('wget %s -O %s', $url, $filename) || drush_shell_exec('curl -L -o %s %s', $filename, $url)) {
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

class DrushMakeDownload_Post extends DrushMakeDownload_Get {
  protected function downloadFile() {
    $name = $this->project->name;
    $tmp_path = $this->project->tmp_path;
    $filename = dt('file');
    if (!empty($this->project->download['url'])) {
      $url = $this->project->download['url'];
      $post_data = $this->project->download['post_data'];
      $file_type = $this->project->download['file_type'];
      $filename = (empty($file_type)) ? "$tmp_path/" . basename($url) : "$tmp_path/" . basename($url) . ".$file_type";
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
    $name     = $this->project->name;
    $tmp_path = $this->project->tmp_path;
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

      // clone the given repository
      if (drush_shell_exec("git clone %s %s/%s", $url, $tmp_path, $name)) {
        drush_log(dt('%project cloned from %url.', array('%project' => $name, '%url' => $url)), 'ok');

        // GIT Checkout only work on a ready cloned repo. So we switch to branch or to tag (only if we have no branch) after cloneing.
        if ($download['branch'] !== 'master' || $download['tag'] || $download['revision']) {

          // get current directory (for move back later)
          $cwd = getcwd();
          // change path to working copy of cloned repo
          chdir($tmp_path .'/'. $name);

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
        if (!$wc && file_exists($tmp_path .'/'. $name .'/.git')) {
          drush_shell_exec("rm -rf %s/%s/.git", $tmp_path, $name);
        }
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
    // @TODO implement revisions, tags
    $name = $this->project->name;
    $tmp_path = $this->project->tmp_path;
    $download = $this->project->download;
    $command = 'export';
    if (!empty($download['url'])) {
      if (drush_get_option('working-copy')) {
        $command = 'branch';
        if (drush_shell_exec("bzr %s %s %s/%s", $command, $download['url'], $tmp_path, $name)) {
          drush_log(dt('%project branched from %url.', array('%project' => $name, '%url' => $download['url'])), 'ok');
          return $name;
        }
      }
      // Export has reversed desintation & source syntax
      elseif (drush_shell_exec("bzr %s %s/%s %s", $command, $tmp_path, $name, $download['url'])) {
        drush_log(dt('%project branched from %url.', array('%project' => $name, '%url' => $download['url'])), 'ok');
        return $name;
      }
    }
    else {
      $download['url'] = dt("unspecified location");
    }
    drush_make_error('DOWNLOAD_ERROR', dt('Unable to branch %project from %url.', array('%project' => $name, '%url' => $download['url'])));
    return FALSE;
  }
}

class DrushMakeDownload_SVN extends DrushMakeDownload {
  function download() {
    $name = $this->project->name;
    $tmp_path = $this->project->tmp_path;
    $download = $this->project->download;
    $command = 'export';
    if (!empty($download['url'])) {
      if (drush_get_option('working-copy')) {
        $command = 'checkout';
      }

      $flags = isset($download['revision']) ? "-r{$download['revision']}" : '';

      if (!empty($download['username'])) {
        $result = drush_shell_exec("svn --non-interactive %s %s %s %s/%s --username %s --password %s", $command, $flags, $download['url'], $tmp_path, $name, $download['username'], $download['password']);
      }
      else {
        $result = drush_shell_exec("svn --non-interactive %s %s %s %s/%s", $command, $flags, $download['url'], $tmp_path, $name);
      }
      if ($result) {
        drush_log(dt('%project @command from %url.', array('%project' => $name, '@command' => $command, '%url' => $download['url'])), 'ok');
        return $this->project->name;
      }
    }
    else {
      $download['url'] = dt("unspecified location");
    }
    drush_make_error('DOWNLOAD_ERROR', dt('Unable to @command %project from %url.', array('%project' => $name, '@command' => $command, '%url' => $download['url'])));
    return FALSE;
  }
}

class DrushMakeDownload_Hg extends DrushMakeDownload {
  function download() {
    // @TODO implement revisions, tags
    $name = $this->project->name;
    $tmp_path = $this->project->tmp_path;
    $download = $this->project->download;
    if (!empty($download['url'])) {
      if (drush_shell_exec("hg clone %s %s/%s", $download['url'], $tmp_path, $name)) {
        if (!drush_get_option('working-copy')) {
          drush_shell_exec('rm -r %s/%s/.hg', $tmp_path, $name);
        }
        drush_log(dt('%project cloned from %url.', array('%project' => $name, '%url' => $download['url'])), 'ok');
        return $name;
      }
    }
    else {
      $download['url'] = dt("unspecified location");
    }
    drush_make_error('DOWNLOAD_ERROR', dt('Unable to clone %project from %url.', array('%project' => $name, '%url' => $download['url'])));
    return FALSE;
  }
}

