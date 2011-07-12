<?php

/*
 * @file
 *   Tests for field.drush.inc
 */
class fieldCase extends Drush_CommandTestCase {

  public function testField() {
    $sites = $this->setUpDrupal(1, TRUE);
    $options = array(
      'yes' => NULL,
      'root' => $this->webroot(),
      'uri' => key($sites),
    );
    // Create two field instances on article content type.
    $this->drush('field-create', array('user', 'city,text,text_textfield', 'subtitle,text,text_textfield'), $options + array('entity_type' => 'user'));
    $output = $this->getOutput();
    list($city, $subtitle) = explode(' ', $output);
    $url = parse_url($subtitle);
    $this->assertEquals('/admin/config/people/accounts/fields/subtitle', $url['path']);

    // Assure that the second field instance was created correctly (subtitle).
    $this->verifyInstance('subtitle', $options);

    // Assure that field update URL looks correct.
    $this->drush('field-update', array('subtitle'), $options);
    $output = $this->getOutput();
    $url = parse_url($this->getOutput());
    $this->assertEquals('/admin/config/people/accounts/fields/subtitle', $url['path']);

    // Assure that field-clone actually clones.
    $this->drush('field-clone', array('subtitle', 'subtitlecloned'), $options);
    $this->verifyInstance('subtitlecloned', $options);

    // Assure that delete works.
    $this->drush('field-delete', array('subtitlecloned'), $options);
    $this->verifyInstance('subtitlecloned', $options, FALSE);
  }

  function verifyInstance($name, $options, $expected = TRUE) {
    $this->drush('field-info', array('fields'), $options + array('pipe' => NULL));
    $output = $this->getOutputAsList();
    $found = FALSE;
    foreach($output as $row) {
      $columns = explode(',', $row);
      if ($columns[0] == $name) {
        $this->assertEquals('text', $columns[1], $name . ' field is of type=text.');
        $this->assertEquals('user', $columns[2], $name . ' field was added to user bundle.');
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