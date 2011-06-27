<?php

/**
 * @file
 *  The drush Issue Queue manager
 */



/**
 * Implementation of hook_drush_command().
 */
function iq_drush_command() {
  $items['iq-info'] = array(
    'description' => 'Show information about an issue from the queue on drupal.org.',
    'arguements' => array(
      'number' => 'The issue number.',
    ),
    'options' => array(
      'long' => 'Print the full issue info data structure.',
    ),
    'aliases' => array('iqi'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );
  $items['iq-create-commit-comment'] = array(
    'description' => 'Create a commit comment for the specified issue number.',
    'arguements' => array(
      'number' => 'The issue number.',
    ),
    'aliases' => array('iqccc', 'ccc'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );
  $items['iq-apply-patch'] = array(
    'description' => 'Look up the most recent patch attached to the specified issue, and apply it to its project.',
    'arguements' => array(
      'number' => 'The issue number.',
    ),
    'options' => array(
      'no-prefix' => 'Patch was created with --no-prefix, and therefore should be applied with -Np0 instead of -Np1.',
      'select' => 'Prompt for which patch to apply.  Optional; default is newest patch.',
    ),
    'aliases' => array('patch', 'ap'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );
  $items['iq-diff'] = array(
    'description' => 'Create a diff.',
    'arguements' => array(
      'number' => 'The issue number.',
    ),
    'options' => array(
      'no-prefix' => 'Create patch with no prefix.  Not recommended; patch will have to be applied with -Np0 instead of -Np1.',
    ),
    'aliases' => array('diff', 'iqd'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );
  $items['iq-reset'] = array(
    'description' => 'Stop working on a patch, and return to the branch point.',
    'options' => array(
      'delete' => 'Also delete the working branch.',
    ),
    'aliases' => array('reset', 'iqr'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );

  return $items;
}

function drush_iq_diff($number = NULL) {
  if (isset($number)) {
    $issue_info = drush_iq_get_info($number);
    $branch = _drush_iq_get_branch($issue_info);
  }
  else {
    $dir = drush_get_context('DRUSH_OLDCWD', drush_cwd());
    $branch = _drush_iq_get_branch_at_dir($dir);
  }

  // If the user did not provide an issue number, but we can find
  // that a git branch has been made at the current working directory,
  // then we will expect that the branch tag is in the format of
  // "arbitrary-prequel-ISSUENUMBER".  If it is in this form, drush_iq_get_info
  // will be able to find the issue number.
  if (isset($branch) && ($branch != "master") && !isset($issue_info)) {
    $issue_info = drush_iq_get_info($branch);
  }

  // Add on extra flags
  $extra = "";
  if (drush_get_option('no-prefix', FALSE)) {
    $extra .= ' --no-prefix';
  }

  // Not under git revision control is an error
  if (empty($branch)) {
    return drush_set_error('DRUSH_NO_VCS', dt("Error: drush can only produce diffs of projects under git version control"));
  }
  else {
    $patch_description = "";
    if (!empty($issue_info)) {
      $comment_number = 1 + count($issue_info['comments']);
      $patch_description = $issue_info['description'] . '-' . $issue_info['id'] . '-' . $comment_number . '.patch';
    }
    drush_log(dt("# Create patch: !patch_description", array('!patch_description' => $patch_description)), 'ok');
    // If we have modified the master branch (not recommended), just run git diff
    if ($branch == "master") {
      drush_op_system("git diff $extra");
    }
    // Changes are being made under a local branch.  Do the recommended procedure for creating the diff.
    else {
      $commit_comment = _drush_iq_create_commit_comment($issue_info, drush_get_option('committer', TRUE));
      drush_shell_exec_interactive("git commit -m %s", $commit_comment);

      // TODO: This works great the first time, but after the 'git rebase origin/master', we get
      // hung up if we try to run drush_iq_diff again (maybe hung up somewhere above).  'git branch -a'
      // shows that we are still on our local branch, and 'git format-patch origin/master' still
      // works; how do we detect this situation and fast-forward to the last step when running the
      // second time?
      drush_op_system("git fetch origin && git rebase origin/master && git format-patch origin/master $extra --stdout");
    }
  }

  return $branch;
}

/**
 * iq-info command callback
 */
function drush_iq_info($number = NULL) {
  if (isset($number)) {
    $issue_info = drush_iq_get_info($number);
  }
  else {
    $dir = drush_get_context('DRUSH_OLDCWD', drush_cwd());
    $branch = _drush_iq_get_branch_at_dir($dir);
    $issue_info = drush_iq_get_info($branch);
  }
  if ($issue_info === FALSE) {
    return FALSE;
  }

  $label_map = _drush_iq_label_map();

  if (drush_get_option('long', FALSE)) {
    drush_print_r($issue_info);
  }
  else {
    $rows = array();

    foreach($label_map as $label => $key) {
      $rows[] = array(substr($label, 0, -1), ':', $issue_info[$key]);
    }

    drush_print_table($rows);
  }
  return $issue_info;
}

/**
 * iq-create-commit-comment command callback
 */
function drush_iq_create_commit_comment($number = NULL) {
  if (isset($number)) {
    $issue_info = drush_iq_get_info($number);
  }
  else {
    $dir = drush_get_context('DRUSH_OLDCWD', drush_cwd());
    $branch = _drush_iq_get_branch_at_dir($dir);
    $issue_info = drush_iq_get_info($branch);
  }
  if ($issue_info === FALSE) {
    return FALSE;
  }

  $result = _drush_iq_create_commit_comment($issue_info, drush_get_option('committer', FALSE));
  drush_print($result);
  return $result;
}

/**
 * Abandon the current branch
 */
function drush_iq_reset($number = NULL) {
  if (isset($number)) {
    $issue_info = drush_iq_get_info($number);
    $dir = _drush_iq_project_dir($issue_info);
  }
  else {
    $dir = drush_get_context('DRUSH_OLDCWD', drush_cwd());
  }
  $branch = _drush_iq_get_branch_at_dir($dir);
  $branch_merges_with = _drush_iq_get_branch_merges_with($dir);
  if ($branch_merges_with != $branch) {
    $delete = drush_get_option('delete', FALSE);

    // TODO: Confirm message does not warn about uncommitted changes getting thrown away.  So, this message is a big dirty lie right now.  Maybe we should just commit?
    $action_message = $delete ? dt("Your working branch !branch will be deleted.") : dt("Your work will be preserve; you can return to it by typing:\n    git checkout !branch");

    drush_print(dt("Would you like to reset to the branch !merges_with? $action_message", array('!merges_with' => $branch_merges_with, '!branch' => $branch)));
    $confirm = drush_confirm(dt("Is it okay to continue?"));
    if (!$confirm) {
      return drush_user_abort();
    }

    $cwd = getcwd();
    drush_op('chdir', $dir);
    $result = drush_shell_exec_interactive("git checkout %s", $branch_merges_with);
    if ($delete) {
      $result = drush_shell_exec_interactive("git branch -d %s", $branch);
    }
  }
  // TODO:  this throws away uncommitted changes, so we shouldn't skip the prompt if we are already on the main branch.
  drush_shell_exec_interactive("git reset --hard HEAD");
  drush_op('chdir', $cwd);
}

/**
 * iq-apply-patch command callback
 *
 * Given an issue number, find the most recent patch file
 * attached to it
 */
function drush_iq_apply_patch($number) {
  $issue_info = drush_iq_get_info($number);
  $patches = _drush_iq_find_patches($issue_info);
  $result = FALSE;

  if (empty($patches)) {
    return drush_set_error('DRUSH_NO_PATCHES', dt("Could not find a patch in !issue", array('!issue' => _drush_iq_create_commit_comment($issue_info))));
  }

  // If --select, then prompt the user
  if (drush_get_option('select', FALSE)) {
    $choices = array();
    foreach ($patches as $index => $url) {
      $choices[$url] = array("#$index", ":", $url);
    }
    $patch = drush_choice($choices, dt("Select a patch to apply:"));
    if ($patch === FALSE) {
      return drush_user_abort();
    }
  }
  // If the issue specification included a comment number, e.g. #1078108-1, then select the patch attached to the specified comment
  elseif (array_key_exists('comment-number', $issue_info)) {
    $index = $issue_info['comment-number'];
    if (!array_key_exists($index, $patches)) {
      return drush_set_error('DRUSH_IQ_NO_PATCH', dt("Error: comment #!index does not exist or does not have a patch.", array('!index' => $index)));
    }
    $patch = $patches[$index];
  }
  else {
    $patch = array_pop($patches);
  }

  $project_dir = _drush_iq_project_dir($issue_info);
  drush_log(dt("Downloading patchfile !patch for project !project", array('!patch' => basename($patch),'!project' => $issue_info['project'])), 'ok');
  $filename = _drush_download_file($patch);
  $filename = realpath(getcwd() . '/' . $filename);
  drush_register_file_for_deletion($filename);

  $branch_merges_with = _drush_iq_get_branch_merges_with($project_dir);

  // Make a branch via 'git checkout -b [description]-[issue]'
  $description = $issue_info['description'];
  $branchlabel = 'drush-iq-' . $description . '-' . $issue_info['id'] . '-' . (1 + count($issue_info['comments']));
  drush_log(dt("Switching to branch !branchlabel", array('!branchlabel' => $branchlabel)), 'ok');
  $cwd = getcwd();
  drush_op('chdir', $project_dir);
  $result = drush_shell_exec_interactive("git checkout -b %s origin/%s", $branchlabel, $branch_merges_with);


  // Figure out if this is a -Np0 or -Np1 patch
  // like this:
  //   -Np0: diff --git commands/sql/sql.drush.inc commands/sql/sql.drush.inc
  //   -Np1: diff --git a/commands/sql/sql.drush.inc b/commands/sql/sql.drush.inc
  // So, find the first 'diff' line, explode(' ', $line), take the last two words,
  // explode them both by '/' and keep adding one to $strip_count until two elements
  // with the same value are found (e.g. 'command' in the above example).
  // However, different patterns may exist if 'diff' is used to create the patch.
  $strip_count = 1;
  if (drush_get_option('no-prefix', FALSE)) {
    $strip_count = 0;
  }

  // TODO: we should just try to run the patch once with -p0 and once with -p1 and see what works
  $result = drush_shell_exec_interactive('git apply -v --directory=%s %s', $project_dir, $filename);
  if ($result === FALSE) {
    drush_log(dt("git apply failed; falling back to 'patch' tool"), 'warning');
    $result = drush_shell_exec_interactive('patch -Np%s -d %s -i %s', $strip_count, $project_dir, $filename);
  }
  drush_op('chdir', $cwd);
  return $result;
}

/**
 * This lookup table is used to map items from
 * the issue queue html to elements in the
 * $issue_info associative array.  It is also used
 * to map back again from the $issue_info keys to the
 * labels in the output of iq-info.
 */
function _drush_iq_label_map() {
  return  array(
    'Title:' => 'title',
    'ID:' => 'id',
    'Project:' => 'project-title',
    'Version:' => 'version',
    'Component:' => 'component',
    'Category:' => 'category',
    'Priority:' => 'priority',
    'Assigned:' => 'assigned',
    'Status:' => 'status',
  );
}

/**
 * Create a commit comment
 *
 * @param $issue_info array describing an issue; @see drush_iq_get_info()
 *
 * @returns string "#id by contributor1, contributor2: issue title"
 */
function _drush_iq_create_commit_comment($issue_info, $committer = FALSE) {
  $contributors = array();

  // If this function is being called because a patch is being
  // created right now, then add the committer to the head of the
  // credits list.
  if ($committer !== FALSE) {
    // If 'TRUE' is passed for the committer, then look up
    // the current user name from git config --list
    if ($committer === TRUE) {
      drush_shell_exec("git config --list | grep '^user.name=' | sed -e 's|[^=]*=||'");
      $committer = array_pop(drush_shell_exec_output());
    }

    if (!empty($committer)) {
      $contributors[] = $committer;
    }
  }
  // Gather up commit credits, listing most recent contributors first.
  // Everyone who added an attachment gets credit.
  foreach (array_reverse($issue_info['attachments']) as $comment_number => $attachment) {
    $contributor = $issue_info['contributors'][$attachment['contributor']]['name'];
    if (!in_array($contributor, $contributors)) {
      $contributors[] = $contributor;
    }
  }

  $credits = "";
  if (!empty($contributors)) {
    $credits = " by " . implode(', ', $contributors);
  }

  $issue_number = $issue_info['id'];
  $issue_title = $issue_info['title'];
  return "#$issue_number$credits: $issue_title";
}

/**
 * Get information about an issue
 *
 * @param $number integer containing the issue number, or string beginning with a "#" and the issue number
 *
 * @returns array
 *  - title             Title of the issue
 *  - id                Issue number
 *  - url               URL to issue page on drupal.org
 *  - project-title     Title of the project issue belongs to (e.g. Drush)
 *  - project           Name of the project (e.g. drush)
 *  - version           Project version
 *  - component         Code, documentation, etc.
 *  - category          Bug, feature request, etc.
 *  - priority          minor, normal, major, critical
 *  - assigned          Name of the user the issue is assigned to
 *  - status            active, needs work, etc.
 *  - comments          A list (array with numeric keys) of URLs
 *  - attachments       A list (array with numeric keys) of attachments
 *      Array
 *        - contributor uid of user submitting the patch
 *        - urls        A list of strings pointing to the attachments
 *  - contributors      An associative array keyed by uid of contributors
 *      Array
 *        - name        Name of contributor
 *        - uid         uid of contributor (same as key for this item)
 *        - profile     url to user profile on drupal.org
 */
function drush_iq_get_info($issue_spec) {
  $comment_number = FALSE;
  // #1234
  if (substr($issue_spec, 0, 1) == '#') {
    $number = substr($issue_spec, 1);
  }
  // http://drupal.org/node/1234
  elseif (substr($issue_spec, 0, 23) == "http://drupal.org/node/") {
    $number = substr($issue_spec, 23);
  }
  // 1234
  elseif (is_numeric($issue_spec)) {
    $number = $issue_spec;
  }
  // description-of-issue-1234 or description-of-issue-1234-8 (description-issue or description-issue-comment)
  elseif (strpos($issue_spec, ' ') === FALSE) {
    $issue_spec_parts = explode('-', $issue_spec);
    $after_last_dash = array_pop($issue_spec_parts);
    $after_second_to_last_dash = array_pop($issue_spec_parts);
    if (is_numeric($after_last_dash)) {
      if (is_numeric($after_second_to_last_dash)) {
        $comment_number = $after_last_dash;
        $number = $after_second_to_last_dash;
        $description = substr($issue_spec, 0, strlen($after_last_dash) + strlen($after_second_to_last_dash));
      }
      else {
        $number = $after_last_dash;
        $description = substr($issue_spec, 0, strlen($after_last_dash) - 1);
      }
    }
  }

  if (!isset($number)) {
    return drush_set_error('DRUSH_ISSUE_NOT_FOUND', dt("Could not find the issue !issue", array('!issue' => $issue_spec)));
  }

  $url = "http://drupal.org/node/$number";
  $filename = _drush_download_file($url);
  $data = file_get_contents($filename);

  // Get rid of the forms; they confuse simplexml.
  $data = preg_replace('|\<form.*\</form>|is', '', $data);
  // simplexml doesn't like the &raquo; or &nbsp; (are there more?)
  $data = preg_replace('|&raquo;|is', '$gt;&gt;', $data);
  $data = preg_replace('|&nbsp;|is', ' ', $data);
  // Hack: d.o has a buggy filter that may generate <a href="http://somewhere.org/somthing<br />xyzzy"> if there is a URL at the end of a line inside of a <code> blocks
  $data = preg_replace('|([a-z]+)="[^"]*<[^"]*"|is', '\1=""', $data);

  $xml = simplexml_load_string($data);
  @unlink($filename);

  // Bail if d.o is not responding
  if (!$xml) {
    return drush_set_error('DRUSH_PM_ISSUE_FAILED', dt('Could not issue data from !url', array('!url' => $url)));
  }

  $issue_info = array();
  $title = $xml->xpath('//*[@id="page-subtitle"]');
  if ($title) {
    $issue_info['title'] = (string)$title[0];
    if (empty($description)) {
      $description = _drush_iq_make_description($issue_info['title']);
    }
    $issue_info['description'] = $description;
  }
  else {
    drush_log('could not find title', 'warning');
  }
  $issue_info['id'] = $number;
  $issue_info['url'] = 'http://drupal.org/node/' . $number;
  $issue_info['attachments'] = array();
  $issue_info['comments'] = array();
  // If the issue spec included an issue number, then put that into the $issue_info structure too
  if ($comment_number !== FALSE) {
    $issue_info['comment-number'] = $comment_number;
  }

  $project = $xml->xpath('//*[@id="project-issue-summary-table"]');
  if ($project) {
    $label_map = _drush_iq_label_map();
    foreach ($project[0]->table->tbody->tr as $tr => $row) {
      if (array_key_exists((string)$row->td[0], $label_map)) {
        $key = $label_map[(string)$row->td[0]];
        $issue_info[$key] = (string)$row[0]->td[1];
      }
    }
  }

  $project_url = $xml->xpath('//*[@class="breadcrumb"]');
  if ($project_url) {
    $project_url_attributes = $project_url[0]->a->attributes();
    $issue_info['project-url'] = 'http://drupal.org' . (string)$project_url_attributes['href'];
    $issue_info['project'] = array_pop(explode('/', (string)$project_url_attributes['href']));
  }

  $op = $xml->xpath('//*[@id="content-inner"]');
  $attachments = $xml->xpath('//*[@id="attachments"]');
  if (empty($attachments)) {
    $pift_results = $xml->xpath('//*[@id="pift-results-' . $issue_info['id'] . '"]');
    if (!empty($pift_results)) {
      $attachments = $pift_results[0]->table;
    }
  }
  $op_xml = simplexml_load_string($op[0]->asXML());
  $contributor_info = _drush_pm_parse_contributor($issue_info, $op_xml->div[0]->div[0]->a);
  _drush_pm_parse_attachments($issue_info, $contributor_info, $attachments);

  $comments = $xml->xpath('//*[@class="comment-inner"]');
  foreach ($comments as $comment) {
    $commentxml = simplexml_load_string($comment->asXML());

    $comment_info = _drush_pm_parse_comment($issue_info, $commentxml->div[0]->h3[0]->a, $contributor_info);

    $comment_attachments = $commentxml->xpath('//*[@class="comment-upload-attachments"]');
    if (empty($comment_attachments)) {
      $pift_results = $commentxml->xpath('//*[@id="pift-results-' . $issue_info['id'] . '-' . $comment_info['id'] . '"]');
      if (!empty($pift_results)) {
        $comment_attachments = $pift_results[0]->table;
      }
    }
    $contributor_info = _drush_pm_parse_contributor($issue_info, $commentxml->div[0]->div[0]->a);
    _drush_pm_parse_attachments($issue_info, $contributor_info, $comment_attachments, $comment_info['number']);
  }

  return $issue_info;
}

/*
 * Pull out information about the contributor of an issue / comment / patch
 * given a link from the html from the issue page.
 */
function _drush_pm_parse_contributor(&$issue_info, $link_xml) {
  $attributes = $link_xml->attributes();
  $contributor = array(
    'name' => (string)$link_xml,
    'uid' => array_pop(explode('/', (string)$attributes['href'])),
    'profile' => 'http://drupal.org' . (string)$attributes['href'],
  );
  $issue_info['contributors'][$contributor['uid']] = $contributor;
  
  return $contributor;
}

/*
 * Pull out information about a comment from a chunk of
 * html from the issue page.
 */
function _drush_pm_parse_comment(&$issue_info, $comment_number_xml, $contributor_info) {
  $comment_info = array();
  
  if ($comment_number_xml) {
    $comment_id_attributes = $comment_number_xml->attributes();
    $comment_info['url'] = 'http://drupal.org' . (string)$comment_id_attributes['href'];
    $comment_info['number'] = substr((string)$comment_number_xml, 1);
    $comment_info['id'] = array_pop(explode('-', $comment_info['url']));
    $comment_info['contributor'] = $contributor_info['uid'];
    
    $issue_info['comments'][$comment_info['number']] = $comment_info;
  }
  
  return $comment_info;
}

/*
 * Pull out information about the attachments from a chunk of
 * html from the issue page.
 */
function _drush_pm_parse_attachments(&$issue_info, $contributor_info, $attachments, $comment_number = 0) {
  if ($attachments) {
    $attachment_urls = array();
    foreach ($attachments[0]->tbody->tr as $tr => $row) {
      $attachment_attributes = $row->td[0]->a->attributes();
      $attachment_urls[] = (string)$attachment_attributes['href'];
    }

    $issue_info['attachments'][$comment_number] = array(
      'contributor' => $contributor_info['uid'],
      'urls' => $attachment_urls,
    );
  }
}

/**
 * Create an ordered list of patches for a given issue.
 *
 * @param $issue_info array describing an issue; @see drush_iq_get_info()
 *
 * @returns Array list of urls pointing to patch files, ordered oldest to newest.
 */
function _drush_iq_find_patches($issue_info) {
  $patches = array();

  foreach ($issue_info['attachments'] as $comment_number => $attachment) {
    foreach ($attachment['urls'] as $url) {
      if (substr($url, -6) == ".patch") {
        // TODO: detect more than one patch per comment?
        // Probably cannot handle multiple patches (why would that ever happen?),
        // but perhaps we could log a warning.
        $patches[$comment_number] = $url;
      }
    }
  }

  return $patches;
}

/**
 * Find the project directory associated with the project
 * the specified issue is associated with.
 */
function _drush_iq_project_dir(&$issue_info) {
  $project_name = $issue_info['project'];

  if (array_key_exists('project-dir', $issue_info)) {
    $result = $issue_info['project-dir'];
  }
  else {
    // If our cwd is at the root of the project, then prefer
    // that project over one in some other location.
    $dir = drush_get_context('DRUSH_OLDCWD', drush_cwd());
    if (basename($dir) == $project_name) {
      $result = $dir;
    }
    // TODO: Find drush extensions such as drush_extras, drush_make, drubuntu, etc.
    elseif ($project_name == 'drush') {
      $result = DRUSH_BASE_PATH;
    }
    else {
      $phase = drush_bootstrap_max();
      // TODO:  exit w/ error if $phase is not high enough
      $extension_info = drush_pm_get_extensions();
      // TODO:  offer to download the project if it is not found?
      if (array_key_exists($project_name, $extension_info)) {
        $result = drush_get_context('DRUSH_DRUPAL_ROOT', '') . '/' . dirname($extension_info[$project_name]->filename);
      }
    }
    $issue_info['project-dir'] = $result;
  }

  return $result;
}

function _drush_iq_get_branch(&$issue_info) {
  if (array_key_exists('branch', $issue_info)) {
    $branch = $issue_info['branch'];
  }
  else {
    $project_dir = _drush_iq_project_dir($issue_info);
    $branch = _drush_iq_get_branch_at_dir($project_dir);
    $issue_info['branch'] = $branch;
  }
  return $branch;
}

function _drush_iq_get_branch_at_dir($dir) {
  $result = drush_shell_cd_and_exec($dir, "git branch");
  $branch_output = drush_shell_exec_output();

  // Return the last non-empty line
  $branch = FALSE;
  while (($branch === FALSE) && !empty($branch_output)) {
    $line = array_shift($branch_output);
    if (!empty($line) && ($line[0] == '*')) {
      $branch_components = explode(' ', $line);
      $branch = $branch_components[1];
    }
  }
  return $branch;
}

function _drush_iq_get_branch_merges_with($dir, $branch_label = FALSE) {
  $merges_with = FALSE;
  $default_merges_with = FALSE;

  $result = drush_shell_cd_and_exec($dir, "git remote show origin -n");
  $show_orgin_output = drush_shell_exec_output();

  if ($branch_label === FALSE) {
    $branch_label = _drush_iq_get_branch_at_dir($dir);
  }
  $match = $branch_label . ' merges with remote ';

  while (($merges_with === FALSE) && !empty($show_orgin_output)) {
    $line = trim(array_shift($show_orgin_output));
    if (($pos = strpos($line, 'merges with remote ')) !== FALSE) {
      $default_merges_with = substr($line, $pos + 19);
    }
    if (substr($line, 0, strlen($match)) == $match) {
      $merges_with = substr($line, strlen($match));
    }
  }

  return $merges_with ? $merges_with : $default_merges_with;
}

function _drush_iq_make_description($title) {
  $description = preg_replace('/[^a-z._-]/', '', str_replace(' ', '-', strtolower($title)));

  // Clip the description off at the next dash after the 30th position
  if (strlen($description) > 30) {
    $find_dash = strpos($description, '-', 30);
    if ($find_dash !== FALSE) {
      $description = substr($description, 0, $find_dash);
    }
  }

  return $description;
}
