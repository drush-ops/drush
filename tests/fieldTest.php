<?php

namespace Unish;

/**
 * Tests for field.drush.inc
 *
 * @group commands
 */
class fieldCase extends CommandUnishTestCase {

  public function testField() {
    if (UNISH_DRUPAL_MAJOR_VERSION == 6) {
      $this->markTestSkipped("Field API not available in Drupal 6.");
    }

    if (UNISH_DRUPAL_MAJOR_VERSION >= 8) {
      $this->markTestSkipped("Field commands are not yet ported to D8.");
    }

    $sites = $this->setUpDrupal(1, TRUE);
    $options = array(
      'yes' => NULL,
      'root' => $this->webroot(),
      'uri' => key($sites),
    );

    $expected_url = '/admin/config/people/accounts/fields/subtitle';
    if (UNISH_DRUPAL_MAJOR_VERSION >= 8) {
      // Prepend for D8. We might want to change setUpDrupal() to add clean url.
      $expected_url = '/index.php' . $expected_url;
    }
    // Create two field instances on article content type.
    $this->drush('field-create', array('user', 'city,text,text_textfield', 'subtitle,text,text_textfield'), $options + array('entity_type' => 'user'));
    $output = $this->getOutput();
    list($city, $subtitle) = explode(' ', $output);
    $url = parse_url($subtitle);
    $this->assertEquals($expected_url, $url['path']);

    // Assure that the second field instance was created correctly (subtitle).
    $this->verifyInstance('subtitle', $options);

    // Assure that field update URL looks correct.
    $this->drush('field-update', array('subtitle'), $options);
    $output = $this->getOutput();
    $url = parse_url($this->getOutput());
    $this->assertEquals($expected_url, $url['path']);

    // Assure that field-clone actually clones.
    $this->drush('field-clone', array('subtitle', 'subtitlecloned'), $options);
    $this->verifyInstance('subtitlecloned', $options);

    // Assure that delete works.
    $this->drush('field-delete', array('subtitlecloned'), $options);
    $this->verifyInstance('subtitlecloned', $options, FALSE);
  }

  function verifyInstance($name, $options, $expected = TRUE) {
    $this->drush('field-info', array('fields'), $options + array('format' => 'json'));
    $output = $this->getOutputFromJSON();
    $found = FALSE;
    foreach($output as $key => $field) {
      if ($key == $name) {
        $this->assertEquals('text', $field->type, $name . ' field is of type=text.');
        $this->assertEquals('user', current($field->bundle), $name . ' field was added to user bundle.');
        $found = TRUE;
        break;
      }
    }
    if ($expected) {
      $this->assertTrue($found, $name . ' field was created.');
    }
    else {
      $this->assertFalse($found, $name . ' field was not present.');
    }
  }
}
