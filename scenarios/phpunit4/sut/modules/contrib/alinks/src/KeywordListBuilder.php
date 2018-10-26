<?php

namespace Drupal\alinks;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Routing\LinkGeneratorTrait;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of Keyword entities.
 *
 * @ingroup alinks
 */
class KeywordListBuilder extends EntityListBuilder {

  use LinkGeneratorTrait;

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\alinks\Entity\Keyword */
    $row['name'] = $this->l(
      $entity->label(),
      new Url(
        'entity.alink_keyword.edit_form', array(
          'alink_keyword' => $entity->id(),
        )
      )
    );
    return $row + parent::buildRow($entity);
  }

}
