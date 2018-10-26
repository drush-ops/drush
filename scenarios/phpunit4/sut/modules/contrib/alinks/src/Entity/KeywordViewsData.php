<?php

namespace Drupal\alinks\Entity;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides Views data for keyword entities.
 */
class KeywordViewsData extends EntityViewsData implements EntityViewsDataInterface {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['alink_keyword']['table']['base'] = [
      'field' => 'id',
      'title' => $this->t('Keyword entity'),
      'help' => $this->t('Keyword entity ID.'),
    ];

    $data['alink_keyword_field_data']['table']['join'] = array(
      'alink_keyword' => array(
        'field' => 'id',
        'left_field' => 'id',
      ),
    );

    return $data;
  }

}
