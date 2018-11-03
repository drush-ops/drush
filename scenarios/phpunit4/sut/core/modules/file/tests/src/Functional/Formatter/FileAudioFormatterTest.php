<?php

namespace Drupal\Tests\file\Functional\Formatter;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\file\Entity\File;

/**
 * @coversDefaultClass \Drupal\file\Plugin\Field\FieldFormatter\FileAudioFormatter
 * @group file
 */
class FileAudioFormatterTest extends FileMediaFormatterTestBase {

  /**
   * @covers ::viewElements
   *
   * @dataProvider dataProvider
   */
  public function testRender($tag_count, $formatter_settings) {
    $field_config = $this->createMediaField('file_audio', 'mp3', $formatter_settings);

    file_put_contents('public://file.mp3', str_repeat('t', 10));
    $file1 = File::create([
      'uri' => 'public://file.mp3',
      'filename' => 'file.mp3',
    ]);
    $file1->save();

    $file2 = File::create([
      'uri' => 'public://file.mp3',
      'filename' => 'file.mp3',
    ]);
    $file2->save();

    $entity = EntityTest::create([
      $field_config->getName() => [
        [
          'target_id' => $file1->id(),
        ],
        [
          'target_id' => $file2->id(),
        ],
      ],
    ]);
    $entity->save();

    $this->drupalGet($entity->toUrl());

    $file1_url = file_url_transform_relative(file_create_url($file1->getFileUri()));
    $file2_url = file_url_transform_relative(file_create_url($file2->getFileUri()));

    $assert_session = $this->assertSession();
    $assert_session->elementsCount('css', 'audio[controls="controls"]', $tag_count);
    $assert_session->elementExists('css', "audio > source[src='$file1_url'][type='audio/mpeg']");
    $assert_session->elementExists('css', "audio > source[src='$file2_url'][type='audio/mpeg']");
  }

}
