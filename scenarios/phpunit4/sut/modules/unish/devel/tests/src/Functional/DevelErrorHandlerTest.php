<?php

namespace Drupal\Tests\devel\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests devel error handler.
 *
 * @group devel
 */
class DevelErrorHandlerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['devel'];

  /**
   * Tests devel error handler.
   */
  public function testErrorHandler() {
    $messages_selector = 'div.messages--warning';

    $expected_notice = new FormattableMarkup('%type: @message in %function (line ', [
      '%type' => 'Notice',
      '@message' => 'Undefined variable: undefined',
      '%function' => 'Drupal\devel\Form\SettingsForm->demonstrateErrorHandlers()',
    ]);

    $expected_warning = new FormattableMarkup('%type: @message in %function (line ', [
      '%type' => 'Warning',
      '@message' => 'Division by zero',
      '%function' => 'Drupal\devel\Form\SettingsForm->demonstrateErrorHandlers()',
    ]);

    $config = $this->config('system.logging');
    $config->set('error_level', ERROR_REPORTING_DISPLAY_VERBOSE)->save();

    $admin_user = $this->drupalCreateUser(['administer site configuration', 'access devel information']);
    $this->drupalLogin($admin_user);

    // Ensures that the error handler config is present on the config page and
    // by default the standard error handler is selected.
    $error_handlers = \Drupal::config('devel.settings')->get('error_handlers');
    $this->assertEquals($error_handlers, [DEVEL_ERROR_HANDLER_STANDARD => DEVEL_ERROR_HANDLER_STANDARD]);
    $this->drupalGet('admin/config/development/devel');
    $this->assertOptionSelected('edit-error-handlers', DEVEL_ERROR_HANDLER_STANDARD);

    // Ensures that selecting the DEVEL_ERROR_HANDLER_NONE option no error
    // (raw or message) is shown on the site in case of php errors.
    $edit = [
      'error_handlers[]' => DEVEL_ERROR_HANDLER_NONE,
    ];
    $this->drupalPostForm('admin/config/development/devel', $edit, t('Save configuration'));
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $error_handlers = \Drupal::config('devel.settings')->get('error_handlers');
    $this->assertEquals($error_handlers, [DEVEL_ERROR_HANDLER_NONE => DEVEL_ERROR_HANDLER_NONE]);
    $this->assertOptionSelected('edit-error-handlers', DEVEL_ERROR_HANDLER_NONE);

    $this->clickLink('notice+warning');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseNotContains($expected_notice);
    $this->assertSession()->responseNotContains($expected_warning);
    $this->assertSession()->elementNotExists('css', $messages_selector);

    // Ensures that selecting the DEVEL_ERROR_HANDLER_BACKTRACE_KINT option a
    // backtrace above the rendered page is shown on the site in case of php
    // errors.
    $edit = [
      'error_handlers[]' => DEVEL_ERROR_HANDLER_BACKTRACE_KINT,
    ];
    $this->drupalPostForm('admin/config/development/devel', $edit, t('Save configuration'));
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $error_handlers = \Drupal::config('devel.settings')->get('error_handlers');
    $this->assertEquals($error_handlers, [DEVEL_ERROR_HANDLER_BACKTRACE_KINT => DEVEL_ERROR_HANDLER_BACKTRACE_KINT]);
    $this->assertOptionSelected('edit-error-handlers', DEVEL_ERROR_HANDLER_BACKTRACE_KINT);

    $this->clickLink('notice+warning');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementNotExists('css', $messages_selector);

    // Ensures that selecting the DEVEL_ERROR_HANDLER_BACKTRACE_DPM option a
    // backtrace in the message area is shown on the site in case of php errors.
    $edit = [
      'error_handlers[]' => DEVEL_ERROR_HANDLER_BACKTRACE_DPM,
    ];
    $this->drupalPostForm('admin/config/development/devel', $edit, t('Save configuration'));
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $error_handlers = \Drupal::config('devel.settings')->get('error_handlers');
    $this->assertEquals($error_handlers, [DEVEL_ERROR_HANDLER_BACKTRACE_DPM => DEVEL_ERROR_HANDLER_BACKTRACE_DPM]);
    $this->assertOptionSelected('edit-error-handlers', DEVEL_ERROR_HANDLER_BACKTRACE_DPM);

    $this->clickLink('notice+warning');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains($expected_notice);
    $this->assertSession()->responseContains($expected_warning);
    $this->assertSession()->elementContains('css', $messages_selector, $expected_notice);
    $this->assertSession()->elementContains('css', $messages_selector, $expected_warning);

    // Ensures that when multiple handlers are selected, the output produced by
    // every handler is shown on the site in case of php errors.
    $edit = [
      'error_handlers[]' => [
        DEVEL_ERROR_HANDLER_BACKTRACE_KINT => DEVEL_ERROR_HANDLER_BACKTRACE_KINT,
        DEVEL_ERROR_HANDLER_BACKTRACE_DPM => DEVEL_ERROR_HANDLER_BACKTRACE_DPM,
      ],
    ];
    $this->drupalPostForm('admin/config/development/devel', $edit, t('Save configuration'));
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $error_handlers = \Drupal::config('devel.settings')->get('error_handlers');
    $this->assertEquals($error_handlers, [
      DEVEL_ERROR_HANDLER_BACKTRACE_KINT => DEVEL_ERROR_HANDLER_BACKTRACE_KINT,
      DEVEL_ERROR_HANDLER_BACKTRACE_DPM => DEVEL_ERROR_HANDLER_BACKTRACE_DPM,
    ]);
    $this->assertOptionSelected('edit-error-handlers', DEVEL_ERROR_HANDLER_BACKTRACE_KINT);
    $this->assertOptionSelected('edit-error-handlers', DEVEL_ERROR_HANDLER_BACKTRACE_DPM);

    $this->clickLink('notice+warning');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains($expected_notice);
    $this->assertSession()->responseContains($expected_warning);
    $this->assertSession()->elementContains('css', $messages_selector, $expected_notice);
    $this->assertSession()->elementContains('css', $messages_selector, $expected_warning);

    // Ensures that setting the error reporting to all the output produced by
    // handlers is shown on the site in case of php errors.
    $config->set('error_level', ERROR_REPORTING_DISPLAY_ALL)->save();
    $this->clickLink('notice+warning');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains($expected_notice);
    $this->assertSession()->responseContains($expected_warning);
    $this->assertSession()->elementContains('css', $messages_selector, $expected_notice);
    $this->assertSession()->elementContains('css', $messages_selector, $expected_warning);

    // Ensures that setting the error reporting to some the output produced by
    // handlers is shown on the site in case of php errors.
    $config->set('error_level', ERROR_REPORTING_DISPLAY_SOME)->save();
    $this->clickLink('notice+warning');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains($expected_notice);
    $this->assertSession()->responseContains($expected_warning);
    $this->assertSession()->elementContains('css', $messages_selector, $expected_notice);
    $this->assertSession()->elementContains('css', $messages_selector, $expected_warning);

    // Ensures that setting the error reporting to none the output produced by
    // handlers is not shown on the site in case of php errors.
    $config->set('error_level', ERROR_REPORTING_HIDE)->save();
    $this->clickLink('notice+warning');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseNotContains($expected_notice);
    $this->assertSession()->responseNotContains($expected_warning);
    $this->assertSession()->elementNotExists('css', $messages_selector);
  }

}
