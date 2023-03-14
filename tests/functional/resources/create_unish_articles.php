<?php

declare(strict_types=1);

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\unish_article\Entity\UnishArticle;

/** @var ContentEntityInterface $article */
$article = UnishArticle::create(['bundle' => 'alpha']);
$article->setOwnerId(2);
// $article->setTitle('Unish wins.');
$article->save();
