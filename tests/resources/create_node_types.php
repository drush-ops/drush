<?php

$types = array(
    array(
      'type' => 'page',
      'name' => 'Basic page',
      'base' => 'node_content',
      'description' => 'Use <em>basic pages</em> for your static content, such as an \'About us\' page.',
      'custom' => 1,
      'modified' => 1,
      'locked' => 0,
    ),
    array(
      'type' => 'article',
      'name' => 'Article',
      'base' => 'node_content',
      'description' => 'Use <em>articles</em> for time-sensitive content like news, press releases or blog posts.',
      'custom' => 1,
      'modified' => 1,
      'locked' => 0,
    ),
);

foreach ($types as $type) {
  $type = node_type_set_defaults($type);
  node_type_save($type);
  node_add_body_field($type);
}
