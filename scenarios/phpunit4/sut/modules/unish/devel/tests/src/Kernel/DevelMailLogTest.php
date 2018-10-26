<?php

namespace Drupal\Tests\devel\Kernel;

use Drupal\Core\Mail\Plugin\Mail\TestMailCollector;
use Drupal\devel\Plugin\Mail\DevelMailLog;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests sending mails with debug interface.
 *
 * @group devel
 */
class DevelMailLogTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['devel', 'devel_test', 'system'];

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('system', 'mail');
    $this->installConfig(['system', 'devel']);

    // Configure system.site mail settings.
    $this->config('system.site')->set('mail', 'devel-test@example.com')->save();

    $this->mailManager = $this->container->get('plugin.manager.mail');
  }

  /**
   * Tests devel_mail_log plugin as default mail backend.
   */
  public function testDevelMailLogDefaultBackend() {
    // Configure devel_mail_log as default mail backends.
    $this->setDevelMailLogAsDefaultBackend();

    // Ensures that devel_mail_log is the default mail plugin .
    $mail_backend = $this->mailManager->getInstance(['module' => 'default', 'key' => 'default']);
    $this->assertInstanceOf(DevelMailLog::class, $mail_backend);

    $mail_backend = $this->mailManager->getInstance(['module' => 'somemodule', 'key' => 'default']);
    $this->assertInstanceOf(DevelMailLog::class, $mail_backend);
  }

  /**
   * Tests devel_mail_log plugin with multiple mail backend.
   */
  public function testDevelMailLogMultipleBackend() {
    // Configure test_mail_collector as default mail backend.
    $this->config('system.mail')
      ->set('interface.default', 'test_mail_collector')
      ->save();

    // Configure devel_mail_log as a module-specific mail backend.
    $this->config('system.mail')
      ->set('interface.somemodule', 'devel_mail_log')
      ->save();

    // Ensures that devel_mail_log is not the default mail plugin.
    $mail_backend = $this->mailManager->getInstance(['module' => 'default', 'key' => 'default']);
    $this->assertInstanceOf(TestMailCollector::class, $mail_backend);

    // Ensures that devel_mail_log is used as mail backend only for the
    // specified module.
    $mail_backend = $this->mailManager->getInstance(['module' => 'somemodule', 'key' => 'default']);
    $this->assertInstanceOf(DevelMailLog::class, $mail_backend);
  }

  /**
   * Tests devel_mail_log default settings.
   */
  public function testDevelMailDefaultSettings() {
    $config = \Drupal::config('devel.settings');
    $this->assertEquals('temporary://devel-mails', $config->get('debug_mail_directory'));
    $this->assertEquals('%to-%subject-%datetime.mail.txt', $config->get('debug_mail_file_format'));
  }

  /**
   * Tests devel mail log output.
   */
  public function testDevelMailLogOutput() {
    $config = \Drupal::config('devel.settings');

    // Parameters used for send the email.
    $mail = [
      'module' => 'devel_test',
      'key' => 'devel_mail_log',
      'to' => 'drupal@example.com',
      'reply' => 'replyto@example.com',
      'lang' => \Drupal::languageManager()->getCurrentLanguage(),
    ];

    // Parameters used for compose the email in devel_test module.
    // @see devel_test_mail()
    $params = [
      'subject' => 'Devel mail log subject',
      'body' => 'Devel mail log body',
      'headers' => [
        'from' => 'postmaster@example.com',
        'additional' => [
          'X-stupid' => 'dumb',
        ],
      ],
    ];

    // Configure devel_mail_log as default mail backends.
    $this->setDevelMailLogAsDefaultBackend();

    // Changes the default filename pattern removing the dynamic date
    // placeholder for a more predictable filename output.
    $random = $this->randomMachineName();
    $filename_pattern = '%to-%subject-' . $random . '.mail.txt';
    $this->config('devel.settings')
      ->set('debug_mail_file_format', $filename_pattern)
      ->save();

    $expected_filename = 'drupal@example.com-Devel_mail_log_subject-' . $random . '.mail.txt';
    $expected_output = <<<EOF
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8; format=flowed; delsp=yes
Content-Transfer-Encoding: 8Bit
X-Mailer: Drupal
Return-Path: devel-test@example.com
Sender: devel-test@example.com
From: postmaster@example.com
Reply-to: replyto@example.com
X-stupid: dumb
To: drupal@example.com
Subject: Devel mail log subject

Devel mail log body

EOF;

    // Ensures that the mail is captured by devel_mail_log and the placeholders
    // in the filename are properly resolved.
    $default_output_directory = $config->get('debug_mail_directory');
    $expected_file_path = $default_output_directory . '/' . $expected_filename;

    $this->mailManager->mail($mail['module'], $mail['key'], $mail['to'], $mail['lang'], $params, $mail['reply']);
    $this->assertFileExists($expected_file_path);
    $this->assertStringEqualsFile($expected_file_path, $expected_output);

    // Ensures that even changing the default output directory devel_mail_log
    // works as expected.
    $changed_output_directory = 'temporary://my-folder';
    $expected_file_path = $changed_output_directory . '/' . $expected_filename;
    $this->config('devel.settings')
      ->set('debug_mail_directory', $changed_output_directory)
      ->save();

    $result = $this->mailManager->mail($mail['module'], $mail['key'], $mail['to'], $mail['lang'], $params, $mail['reply']);
    $this->assertSame(TRUE, $result['result']);
    $this->assertFileExists($expected_file_path);
    $this->assertStringEqualsFile($expected_file_path, $expected_output);

    // Ensures that if the default output directory is a public directory it
    // will be protected by adding an .htaccess.
    $public_output_directory = 'public://my-folder';
    $expected_file_path = $public_output_directory . '/' . $expected_filename;
    $this->config('devel.settings')
      ->set('debug_mail_directory', $public_output_directory)
      ->save();

    $this->mailManager->mail($mail['module'], $mail['key'], $mail['to'], $mail['lang'], $params, $mail['reply']);
    $this->assertFileExists($expected_file_path);
    $this->assertStringEqualsFile($expected_file_path, $expected_output);
    $this->assertFileExists($public_output_directory . '/.htaccess');
  }

  /**
   * Configure devel_mail_log as default mail backend.
   */
  private function setDevelMailLogAsDefaultBackend() {
    // TODO can this be avoided?
    // KernelTestBase enforce the usage of 'test_mail_collector' plugin for
    // collect the mails. Since we need to test devel mail plugin we manually
    // configure the mail implementation to use 'devel_mail_log'.
    $GLOBALS['config']['system.mail']['interface']['default'] = 'devel_mail_log';

    // Configure devel_mail_log as default mail backend.
    $this->config('system.mail')
      ->set('interface.default', 'devel_mail_log')
      ->save();
  }

}
