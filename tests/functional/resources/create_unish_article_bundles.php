<?php

declare(strict_types=1);

$storage = \Drupal::service('entity_type.manager')->getStorage('unish_article_type');
$storage->create([
    'id' => 'alpha',
    'label' => 'Alpha',
])->save();
$storage->create([
    'id' => 'beta',
    'label' => 'Beta',
])->save();
