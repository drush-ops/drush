<?php

use Drupal\unish_article\Entity\UnishArticle;

$article = UnishArticle::create();
$article->setOwnerId(2);
$article->setTitle('Unish wins.');
$article->save();
