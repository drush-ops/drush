<?php

declare(strict_types=1);

use Drupal\unish_article\Entity\UnishArticle;

/** @var \Drupal\Core\Entity\ContentEntityInterface $article */
$article = UnishArticle::create(['bundle' => 'alpha']);
$article->setOwnerId(2);
// $article->setTitle('Unish wins.');
$article->save();
