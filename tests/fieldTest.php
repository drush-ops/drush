<?php

/*
 * @file
 *   Tests for field.drush.inc
 */
class fieldCase extends Drush_TestCase {

  public function testField() {
    $this->setUpDrupal('dev', TRUE, '7.x', 'standard');
    $options = array(
      'yes' => NULL,
      'root' => $this->sites['dev']['root'],
      'uri' => 'dev',
    );
    // Create two field instances on article content type.
    $this->drush('field-create', array('article', 'city,text,text_textfield', 'subtitle,text,text_textfield'), $options);
    $output = $this->getOutput();
    list($city, $subtitle) = explode(' ', $output);
    $url = parse_url($subtitle);
    $this->assertEquals($url['path'], '/admin/structure/types/manage/article/fields/subtitle');

    // Assure that the second field instance was created correctly (subtitle).
    $this->verifyInstance('subtitle', $options);

    // Assure that field update URL looks correct.
    $this->drush('field-update', array('subtitle'), $options);
    $output = $this->getOutput();
    $url = parse_url($this->getOutput());
    $this->assertEquals($url['path'], '/admin/structure/types/manage/article/fields/subtitle');

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
        $this->assertEquals('article', $columns[2], $name . ' field was added to article bundle.');
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