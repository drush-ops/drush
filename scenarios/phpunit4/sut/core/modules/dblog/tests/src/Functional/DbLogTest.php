<?php

namespace Drupal\Tests\dblog\Functional;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Url;
use Drupal\dblog\Controller\DbLogController;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\Traits\Core\CronRunTrait;

/**
 * Generate events and verify dblog entries; verify user access to log reports
 * based on permissions.
 *
 * @group dblog
 */
class DbLogTest extends BrowserTestBase {
  use CronRunTrait;
  use FakeLogEntries;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['dblog', 'node', 'forum', 'help', 'block'];

  /**
   * A user with some relevant administrative permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A user without any permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalPlaceBlock('system_breadcrumb_block');
    $this->drupalPlaceBlock('page_title_block');

    // Create users with specific permissions.
    $this->adminUser = $this->drupalCreateUser(['administer site configuration', 'access administration pages', 'access site reports', 'administer users']);
    $this->webUser = $this->drupalCreateUser([]);
  }

  /**
   * Tests Database Logging module functionality through interfaces.
   *
   * First logs in users, then creates database log events, and finally tests
   * Database Logging module functionality through both the admin and user
   * interfaces.
   */
  public function testDbLog() {
    // Log in the admin user.
    $this->drupalLogin($this->adminUser);

    $row_limit = 100;
    $this->verifyRowLimit($row_limit);
    $this->verifyCron($row_limit);
    $this->verifyEvents();
    $this->verifyReports();
    $this->verifyBreadcrumbs();
    $this->verifyLinkEscaping();
    // Verify the overview table sorting.
    $orders = ['Date', 'Type', 'User'];
    $sorts = ['asc', 'desc'];
    foreach ($orders as $order) {
      foreach ($sorts as $sort) {
        $this->verifySort($sort, $order);
      }
    }

    // Log in the regular user.
    $this->drupalLogin($this->webUser);
    $this->verifyReports(403);
  }

  /**
   * Test individual log event page.
   */
  public function testLogEventPage() {
    // Login the admin user.
    $this->drupalLogin($this->adminUser);

    // Since referrer and location links vary by how the tests are run, inject
    // fake log data to test these.
    $context = [
      'request_uri' => 'http://example.com?dblog=1',
      'referer' => 'http://example.org?dblog=2',
      'uid' => 0,
      'channel' => 'testing',
      'link' => 'foo/bar',
      'ip' => '0.0.1.0',
      'timestamp' => REQUEST_TIME,
    ];
    \Drupal::service('logger.dblog')->log(RfcLogLevel::NOTICE, 'Test message', $context);
    $wid = db_query('SELECT MAX(wid) FROM {watchdog}')->fetchField();

    // Verify the links appear correctly.
    $this->drupalGet('admin/reports/dblog/event/' . $wid);
    $this->assertLinkByHref($context['request_uri']);
    $this->assertLinkByHref($context['referer']);

    // Verify hostname.
    $this->assertRaw($context['ip'], 'Found hostname on the detail page.');

    // Verify severity.
    $this->assertText('Notice', 'The severity was properly displayed on the detail page.');
  }

  /**
   * Verifies setting of the database log row limit.
   *
   * @param int $row_limit
   *   The row limit.
   */
  private function verifyRowLimit($row_limit) {
    // Change the database log row limit.
    $edit = [];
    $edit['dblog_row_limit'] = $row_limit;
    $this->drupalPostForm('admin/config/development/logging', $edit, t('Save configuration'));
    $this->assertResponse(200);

    // Check row limit variable.
    $current_limit = $this->config('dblog.settings')->get('row_limit');
    $this->assertTrue($current_limit == $row_limit, format_string('[Cache] Row limit variable of @count equals row limit of @limit', ['@count' => $current_limit, '@limit' => $row_limit]));
  }

  /**
   * Verifies that cron correctly applies the database log row limit.
   *
   * @param int $row_limit
   *   The row limit.
   */
  private function verifyCron($row_limit) {
    // Generate additional log entries.
    $this->generateLogEntries($row_limit + 10);
    // Verify that the database log row count exceeds the row limit.
    $count = db_query('SELECT COUNT(wid) FROM {watchdog}')->fetchField();
    $this->assertTrue($count > $row_limit, format_string('Dblog row count of @count exceeds row limit of @limit', ['@count' => $count, '@limit' => $row_limit]));

    // Get the number of enabled modules. Cron adds a log entry for each module.
    $list = \Drupal::moduleHandler()->getImplementations('cron');
    $module_count = count($list);
    $cron_detailed_count = $this->runCron();
    $this->assertTrue($cron_detailed_count == $module_count + 2, format_string('Cron added @count of @expected new log entries', ['@count' => $cron_detailed_count, '@expected' => $module_count + 2]));

    // Test disabling of detailed cron logging.
    $this->config('system.cron')->set('logging', 0)->save();
    $cron_count = $this->runCron();
    $this->assertTrue($cron_count = 1, format_string('Cron added @count of @expected new log entries', ['@count' => $cron_count, '@expected' => 1]));
  }

  /**
   * Runs cron and returns number of new log entries.
   *
   * @return int
   *   Number of new watchdog entries.
   */
  private function runCron() {
    // Get last ID to compare against; log entries get deleted, so we can't
    // reliably add the number of newly created log entries to the current count
    // to measure number of log entries created by cron.
    $last_id = db_query('SELECT MAX(wid) FROM {watchdog}')->fetchField();

    // Run a cron job.
    $this->cronRun();

    // Get last ID after cron was run.
    $current_id = db_query('SELECT MAX(wid) FROM {watchdog}')->fetchField();

    return $current_id - $last_id;
  }

  /**
   * Clear the entry logs by clicking on 'Clear log messages' button.
   */
  protected function clearLogsEntries() {
    $this->drupalGet(Url::fromRoute('dblog.confirm'));
  }

  /**
   * Filters the logs according to the specific severity and log entry type.
   *
   * @param string $type
   *   (optional) The log entry type.
   * @param string $severity
   *   (optional) The log entry severity.
  */
  protected function filterLogsEntries($type = NULL, $severity = NULL) {
    $edit = [];
    if (isset($type)) {
      $edit['type[]'] = $type;
    }
    if (isset($severity)) {
      $edit['severity[]'] = $severity;
    }
    $this->drupalPostForm(NULL, $edit, t('Filter'));
  }

  /**
   * Confirms that database log reports are displayed at the correct paths.
   *
   * @param int $response
   *   (optional) HTTP response code. Defaults to 200.
   */
  private function verifyReports($response = 200) {
    // View the database log help page.
    $this->drupalGet('admin/help/dblog');
    $this->assertResponse($response);
    if ($response == 200) {
      $this->assertText(t('Database Logging'), 'DBLog help was displayed');
    }

    // View the database log report page.
    $this->drupalGet('admin/reports/dblog');
    $this->assertResponse($response);
    if ($response == 200) {
      $this->assertText(t('Recent log messages'), 'DBLog report was displayed');
    }

    $this->drupalGet('admin/reports/dblog/confirm');
    $this->assertResponse($response);
    if ($response == 200) {
      $this->assertText(t('Are you sure you want to delete the recent logs?'), 'DBLog clear logs form was displayed');
    }

    // View the database log page-not-found report page.
    $this->drupalGet('admin/reports/page-not-found');
    $this->assertResponse($response);
    if ($response == 200) {
      $this->assertText("Top 'page not found' errors", 'DBLog page-not-found report was displayed');
    }

    // View the database log access-denied report page.
    $this->drupalGet('admin/reports/access-denied');
    $this->assertResponse($response);
    if ($response == 200) {
      $this->assertText("Top 'access denied' errors", 'DBLog access-denied report was displayed');
    }

    // View the database log event page.
    $wid = db_query('SELECT MIN(wid) FROM {watchdog}')->fetchField();
    $this->drupalGet('admin/reports/dblog/event/' . $wid);
    $this->assertResponse($response);
    if ($response == 200) {
      $this->assertText(t('Details'), 'DBLog event node was displayed');
    }
  }

  /**
   * Generates and then verifies breadcrumbs.
   */
  private function verifyBreadcrumbs() {
    // View the database log event page.
    $wid = db_query('SELECT MIN(wid) FROM {watchdog}')->fetchField();
    $this->drupalGet('admin/reports/dblog/event/' . $wid);
    $xpath = '//nav[@class="breadcrumb"]/ol/li[last()]/a';
    $this->assertEqual(current($this->xpath($xpath))->getText(), 'Recent log messages', 'DBLogs link displayed at breadcrumb in event page.');
  }

  /**
   * Generates and then verifies various types of events.
   */
  private function verifyEvents() {
    // Invoke events.
    $this->doUser();
    $this->drupalCreateContentType(['type' => 'article', 'name' => t('Article')]);
    $this->drupalCreateContentType(['type' => 'page', 'name' => t('Basic page')]);
    $this->doNode('article');
    $this->doNode('page');
    $this->doNode('forum');

    // When a user account is canceled, any content they created remains but the
    // uid = 0. Records in the watchdog table related to that user have the uid
    // set to zero.
  }

  /**
   * Verifies the sorting functionality of the database logging reports table.
   *
   * @param string $sort
   *   The sort direction.
   * @param string $order
   *   The order by which the table should be sorted.
   */
  public function verifySort($sort = 'asc', $order = 'Date') {
    $this->drupalGet('admin/reports/dblog', ['query' => ['sort' => $sort, 'order' => $order]]);
    $this->assertResponse(200);
    $this->assertText(t('Recent log messages'), 'DBLog report was displayed correctly and sorting went fine.');
  }

  /**
   * Tests the escaping of links in the operation row of a database log detail
   * page.
   */
  private function verifyLinkEscaping() {
    $link = \Drupal::l('View', Url::fromRoute('entity.node.canonical', ['node' => 1]));
    $message = 'Log entry added to do the verifyLinkEscaping test.';
    $this->generateLogEntries(1, [
      'message' => $message,
      'link' => $link,
    ]);

    $result = db_query_range('SELECT wid FROM {watchdog} ORDER BY wid DESC', 0, 1);
    $this->drupalGet('admin/reports/dblog/event/' . $result->fetchField());

    // Check if the link exists (unescaped).
    $this->assertRaw($link);
  }

  /**
   * Generates and then verifies some user events.
   */
  private function doUser() {
    // Set user variables.
    $name = $this->randomMachineName();
    $pass = user_password();
    // Add a user using the form to generate an add user event (which is not
    // triggered by drupalCreateUser).
    $edit = [];
    $edit['name'] = $name;
    $edit['mail'] = $name . '@example.com';
    $edit['pass[pass1]'] = $pass;
    $edit['pass[pass2]'] = $pass;
    $edit['status'] = 1;
    $this->drupalPostForm('admin/people/create', $edit, t('Create new account'));
    $this->assertResponse(200);
    // Retrieve the user object.
    $user = user_load_by_name($name);
    $this->assertTrue($user != NULL, format_string('User @name was loaded', ['@name' => $name]));
    // pass_raw property is needed by drupalLogin.
    $user->passRaw = $pass;
    // Log in user.
    $this->drupalLogin($user);
    // Log out user.
    $this->drupalLogout();
    // Fetch the row IDs in watchdog that relate to the user.
    $result = db_query('SELECT wid FROM {watchdog} WHERE uid = :uid', [':uid' => $user->id()]);
    foreach ($result as $row) {
      $ids[] = $row->wid;
    }
    $count_before = (isset($ids)) ? count($ids) : 0;
    $this->assertTrue($count_before > 0, format_string('DBLog contains @count records for @name', ['@count' => $count_before, '@name' => $user->getUsername()]));

    // Log in the admin user.
    $this->drupalLogin($this->adminUser);
    // Delete the user created at the start of this test.
    // We need to POST here to invoke batch_process() in the internal browser.
    $this->drupalPostForm('user/' . $user->id() . '/cancel', ['user_cancel_method' => 'user_cancel_reassign'], t('Cancel account'));

    // View the database log report.
    $this->drupalGet('admin/reports/dblog');
    $this->assertResponse(200);

    // Verify that the expected events were recorded.
    // Add user.
    // Default display includes name and email address; if too long, the email
    // address is replaced by three periods.
    $this->assertLogMessage(t('New user: %name %email.', ['%name' => $name, '%email' => '<' . $user->getEmail() . '>']), 'DBLog event was recorded: [add user]');
    // Log in user.
    $this->assertLogMessage(t('Session opened for %name.', ['%name' => $name]), 'DBLog event was recorded: [login user]');
    // Log out user.
    $this->assertLogMessage(t('Session closed for %name.', ['%name' => $name]), 'DBLog event was recorded: [logout user]');
    // Delete user.
    $message = t('Deleted user: %name %email.', ['%name' => $name, '%email' => '<' . $user->getEmail() . '>']);
    $message_text = Unicode::truncate(Html::decodeEntities(strip_tags($message)), 56, TRUE, TRUE);
    // Verify that the full message displays on the details page.
    $link = FALSE;
    if ($links = $this->xpath('//a[text()="' . $message_text . '"]')) {
      // Found link with the message text.
      $links = array_shift($links);
      $value = $links->getAttribute('href');

      // Extract link to details page.
      $link = mb_substr($value, strpos($value, 'admin/reports/dblog/event/'));
      $this->drupalGet($link);
      // Check for full message text on the details page.
      $this->assertRaw($message, 'DBLog event details was found: [delete user]');
    }
    $this->assertTrue($link, 'DBLog event was recorded: [delete user]');
    // Visit random URL (to generate page not found event).
    $not_found_url = $this->randomMachineName(60);
    $this->drupalGet($not_found_url);
    $this->assertResponse(404);
    // View the database log page-not-found report page.
    $this->drupalGet('admin/reports/page-not-found');
    $this->assertResponse(200);
    // Check that full-length URL displayed.
    $this->assertText($not_found_url, 'DBLog event was recorded: [page not found]');
  }

  /**
   * Generates and then verifies some node events.
   *
   * @param string $type
   *   A node type (e.g., 'article', 'page' or 'forum').
   */
  private function doNode($type) {
    // Create user.
    $perm = ['create ' . $type . ' content', 'edit own ' . $type . ' content', 'delete own ' . $type . ' content'];
    $user = $this->drupalCreateUser($perm);
    // Log in user.
    $this->drupalLogin($user);

    // Create a node using the form in order to generate an add content event
    // (which is not triggered by drupalCreateNode).
    $edit = $this->getContent($type);
    $title = $edit['title[0][value]'];
    $this->drupalPostForm('node/add/' . $type, $edit, t('Save'));
    $this->assertResponse(200);
    // Retrieve the node object.
    $node = $this->drupalGetNodeByTitle($title);
    $this->assertTrue($node != NULL, format_string('Node @title was loaded', ['@title' => $title]));
    // Edit the node.
    $edit = $this->getContentUpdate($type);
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, t('Save'));
    $this->assertResponse(200);
    // Delete the node.
    $this->drupalPostForm('node/' . $node->id() . '/delete', [], t('Delete'));
    $this->assertResponse(200);
    // View the node (to generate page not found event).
    $this->drupalGet('node/' . $node->id());
    $this->assertResponse(404);
    // View the database log report (to generate access denied event).
    $this->drupalGet('admin/reports/dblog');
    $this->assertResponse(403);

    // Log in the admin user.
    $this->drupalLogin($this->adminUser);
    // View the database log report.
    $this->drupalGet('admin/reports/dblog');
    $this->assertResponse(200);

    // Verify that node events were recorded.
    // Was node content added?
    $this->assertLogMessage(t('@type: added %title.', ['@type' => $type, '%title' => $title]), 'DBLog event was recorded: [content added]');
    // Was node content updated?
    $this->assertLogMessage(t('@type: updated %title.', ['@type' => $type, '%title' => $title]), 'DBLog event was recorded: [content updated]');
    // Was node content deleted?
    $this->assertLogMessage(t('@type: deleted %title.', ['@type' => $type, '%title' => $title]), 'DBLog event was recorded: [content deleted]');

    // View the database log access-denied report page.
    $this->drupalGet('admin/reports/access-denied');
    $this->assertResponse(200);
    // Verify that the 'access denied' event was recorded.
    $this->assertText('admin/reports/dblog', 'DBLog event was recorded: [access denied]');

    // View the database log page-not-found report page.
    $this->drupalGet('admin/reports/page-not-found');
    $this->assertResponse(200);
    // Verify that the 'page not found' event was recorded.
    $this->assertText('node/' . $node->id(), 'DBLog event was recorded: [page not found]');
  }

  /**
   * Creates random content based on node content type.
   *
   * @param string $type
   *   Node content type (e.g., 'article').
   *
   * @return array
   *   Random content needed by various node types.
   */
  private function getContent($type) {
    switch ($type) {
      case 'forum':
        $content = [
          'title[0][value]' => $this->randomMachineName(8),
          'taxonomy_forums' => 1,
          'body[0][value]' => $this->randomMachineName(32),
        ];
        break;

      default:
        $content = [
          'title[0][value]' => $this->randomMachineName(8),
          'body[0][value]' => $this->randomMachineName(32),
        ];
        break;
    }
    return $content;
  }

  /**
   * Creates random content as an update based on node content type.
   *
   * @param string $type
   *   Node content type (e.g., 'article').
   *
   * @return array
   *   Random content needed by various node types.
   */
  private function getContentUpdate($type) {
    $content = [
      'body[0][value]' => $this->randomMachineName(32),
    ];
    return $content;
  }

  /**
   * Tests the addition and clearing of log events through the admin interface.
   *
   * Logs in the admin user, creates a database log event, and tests the
   * functionality of clearing the database log through the admin interface.
   */
  public function testDBLogAddAndClear() {
    global $base_root;
    // Get a count of how many watchdog entries already exist.
    $count = db_query('SELECT COUNT(*) FROM {watchdog}')->fetchField();
    $log = [
      'channel'     => 'system',
      'message'     => 'Log entry added to test the doClearTest clear down.',
      'variables'   => [],
      'severity'    => RfcLogLevel::NOTICE,
      'link'        => NULL,
      'user'        => $this->adminUser,
      'uid'         => $this->adminUser->id(),
      'request_uri' => $base_root . \Drupal::request()->getRequestUri(),
      'referer'     => \Drupal::request()->server->get('HTTP_REFERER'),
      'ip'          => '127.0.0.1',
      'timestamp'   => REQUEST_TIME,
    ];
    // Add a watchdog entry.
    $this->container->get('logger.dblog')->log($log['severity'], $log['message'], $log);
    // Make sure the table count has actually been incremented.
    $this->assertEqual($count + 1, db_query('SELECT COUNT(*) FROM {watchdog}')->fetchField(), format_string('\Drupal\dblog\Logger\DbLog->log() added an entry to the dblog :count', [':count' => $count]));
    // Log in the admin user.
    $this->drupalLogin($this->adminUser);
    // Post in order to clear the database table.
    $this->clearLogsEntries();
    // Confirm that the logs should be cleared.
    $this->drupalPostForm(NULL, [], 'Confirm');
    // Count the rows in watchdog that previously related to the deleted user.
    $count = db_query('SELECT COUNT(*) FROM {watchdog}')->fetchField();
    $this->assertEqual($count, 0, format_string('DBLog contains :count records after a clear.', [':count' => $count]));
  }

  /**
   * Tests the database log filter functionality at admin/reports/dblog.
   */
  public function testFilter() {
    $this->drupalLogin($this->adminUser);

    // Clear the log to ensure that only generated entries will be found.
    db_delete('watchdog')->execute();

    // Generate 9 random watchdog entries.
    $type_names = [];
    $types = [];
    for ($i = 0; $i < 3; $i++) {
      $type_names[] = $type_name = $this->randomMachineName();
      $severity = RfcLogLevel::EMERGENCY;
      for ($j = 0; $j < 3; $j++) {
        $types[] = $type = [
          'count' => $j + 1,
          'type' => $type_name,
          'severity' => $severity++,
        ];
        $this->generateLogEntries($type['count'], [
          'channel' => $type['type'],
          'severity' => $type['severity'],
        ]);
      }
    }

    // View the database log page.
    $this->drupalGet('admin/reports/dblog');

    // Confirm that all the entries are displayed.
    $count = $this->getTypeCount($types);
    foreach ($types as $key => $type) {
      $this->assertEqual($count[$key], $type['count'], 'Count matched');
    }

    // Filter by each type and confirm that entries with various severities are
    // displayed.
    foreach ($type_names as $type_name) {
      $this->filterLogsEntries($type_name);

      // Count the number of entries of this type.
      $type_count = 0;
      foreach ($types as $type) {
        if ($type['type'] == $type_name) {
          $type_count += $type['count'];
        }
      }

      $count = $this->getTypeCount($types);
      $this->assertEqual(array_sum($count), $type_count, 'Count matched');
    }

    // Set the filter to match each of the two filter-type attributes and
    // confirm the correct number of entries are displayed.
    foreach ($types as $type) {
      $this->filterLogsEntries($type['type'], $type['severity']);

      $count = $this->getTypeCount($types);
      $this->assertEqual(array_sum($count), $type['count'], 'Count matched');
    }

    $this->drupalGet('admin/reports/dblog', ['query' => ['order' => 'Type']]);
    $this->assertResponse(200);
    $this->assertText(t('Operations'), 'Operations text found');

    // Clear all logs and make sure the confirmation message is found.
    $this->clearLogsEntries();
    // Confirm that the logs should be cleared.
    $this->drupalPostForm(NULL, [], 'Confirm');
    $this->assertText(t('Database log cleared.'), 'Confirmation message found');
  }

  /**
   * Gets the database log event information from the browser page.
   *
   * @return array
   *   List of log events where each event is an array with following keys:
   *   - severity: (int) A database log severity constant.
   *   - type: (string) The type of database log event.
   *   - message: (string) The message for this database log event.
   *   - user: (string) The user associated with this database log event.
   */
  protected function getLogEntries() {
    $entries = [];
    if ($table = $this->getLogsEntriesTable()) {
      foreach ($table as $row) {
        $cells = $row->findAll('css', 'td');
        $entries[] = [
          'severity' => $this->getSeverityConstant($row->getAttribute('class')),
          'type' => $cells[1]->getText(),
          'message' => $cells[3]->getText(),
          'user' => $cells[4]->getText(),
        ];
      }
    }
    return $entries;
  }

  /**
   * Find the Logs table in the DOM.
   *
   * @return \SimpleXMLElement[]
   *   The return value of a xpath search.
   */
  protected function getLogsEntriesTable() {
    return $this->xpath('.//table[@id="admin-dblog"]/tbody/tr');
  }

  /**
   * Gets the count of database log entries by database log event type.
   *
   * @param array $types
   *   The type information to compare against.
   *
   * @return array
   *   The count of each type keyed by the key of the $types array.
   */
  protected function getTypeCount(array $types) {
    $entries = $this->getLogEntries();
    $count = array_fill(0, count($types), 0);
    foreach ($entries as $entry) {
      foreach ($types as $key => $type) {
        if ($entry['type'] == $type['type'] && $entry['severity'] == $type['severity']) {
          $count[$key]++;
          break;
        }
      }
    }
    return $count;
  }

  /**
   * Gets the watchdog severity constant corresponding to the CSS class.
   *
   * @param string $class
   *   CSS class attribute.
   *
   * @return int|null
   *   The watchdog severity constant or NULL if not found.
   */
  protected function getSeverityConstant($class) {
    $map = array_flip(DbLogController::getLogLevelClassMap());

    // Find the class that contains the severity.
    $classes = explode(' ', $class);
    foreach ($classes as $class) {
      if (isset($map[$class])) {
        return $map[$class];
      }
    }
    return NULL;
  }

  /**
   * Confirms that a log message appears on the database log overview screen.
   *
   * This function should only be used for the admin/reports/dblog page, because
   * it checks for the message link text truncated to 56 characters. Other log
   * pages have no detail links so they contain the full message text.
   *
   * @param string $log_message
   *   The database log message to check.
   * @param string $message
   *   The message to pass to simpletest.
   */
  protected function assertLogMessage($log_message, $message) {
    $message_text = Unicode::truncate(Html::decodeEntities(strip_tags($log_message)), 56, TRUE, TRUE);
    $this->assertLink($message_text, 0, $message);
  }

  /**
   * Tests that the details page displays correctly for a temporary user.
   */
  public function testTemporaryUser() {
    // Create a temporary user.
    $tempuser = $this->drupalCreateUser();
    $tempuser_uid = $tempuser->id();

    // Log in as the admin user.
    $this->drupalLogin($this->adminUser);

    // Generate a single watchdog entry.
    $this->generateLogEntries(1, ['user' => $tempuser, 'uid' => $tempuser_uid]);
    $wid = db_query('SELECT MAX(wid) FROM {watchdog}')->fetchField();

    // Check if the full message displays on the details page.
    $this->drupalGet('admin/reports/dblog/event/' . $wid);
    $this->assertText('Dblog test log message');

    // Delete the user.
    user_delete($tempuser->id());
    $this->drupalGet('user/' . $tempuser_uid);
    $this->assertResponse(404);

    // Check if the full message displays on the details page.
    $this->drupalGet('admin/reports/dblog/event/' . $wid);
    $this->assertText('Dblog test log message');
  }

  /**
   * Make sure HTML tags are filtered out in the log overview links.
   */
  public function testOverviewLinks() {
    $this->drupalLogin($this->adminUser);
    $this->generateLogEntries(1, ['message' => "&lt;script&gt;alert('foo');&lt;/script&gt;<strong>Lorem</strong> ipsum dolor sit amet, consectetur adipiscing & elit."]);
    $this->drupalGet('admin/reports/dblog');
    $this->assertResponse(200);
    // Make sure HTML tags are filtered out.
    $this->assertRaw('title="alert(&#039;foo&#039;);Lorem');
    $this->assertNoRaw("<script>alert('foo');</script>");

    // Make sure HTML tags are filtered out in admin/reports/dblog/event/ too.
    $this->generateLogEntries(1, ['message' => "<script>alert('foo');</script> <strong>Lorem ipsum</strong>"]);
    $wid = db_query('SELECT MAX(wid) FROM {watchdog}')->fetchField();
    $this->drupalGet('admin/reports/dblog/event/' . $wid);
    $this->assertNoRaw("<script>alert('foo');</script>");
    $this->assertRaw("alert('foo'); <strong>Lorem ipsum</strong>");
  }

  /**
   * Test sorting for entries with the same timestamp.
   */
  public function testSameTimestampEntries() {
    $this->drupalLogin($this->adminUser);

    $this->generateLogEntries(1, ['timestamp' => 1498062000, 'type' => 'same_time', 'message' => 'First']);
    $this->generateLogEntries(1, ['timestamp' => 1498062000, 'type' => 'same_time', 'message' => 'Second']);
    $this->generateLogEntries(1, ['timestamp' => 1498062000, 'type' => 'same_time', 'message' => 'Third']);

    $this->drupalGet('admin/reports/dblog');

    $entries = $this->getLogEntries();
    $this->assertEquals($entries[0]['message'], 'Third Entry #0');
    $this->assertEquals($entries[1]['message'], 'Second Entry #0');
    $this->assertEquals($entries[2]['message'], 'First Entry #0');
  }

}
