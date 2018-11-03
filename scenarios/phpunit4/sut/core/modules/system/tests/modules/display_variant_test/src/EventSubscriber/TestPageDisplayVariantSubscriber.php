<?php

namespace Drupal\display_variant_test\EventSubscriber;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Render\PageDisplayVariantSelectionEvent;
use Drupal\Core\Render\RenderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Selects the test page display variant.
 */
class TestPageDisplayVariantSubscriber implements EventSubscriberInterface {

  /**
   * Selects the page display variant.
   *
   * @param \Drupal\Core\Render\PageDisplayVariantSelectionEvent $event
   *   The event to process.
   */
  public function onSelectPageDisplayVariant(PageDisplayVariantSelectionEvent $event) {
    $event->setPluginId('display_variant_test');
    $event->setPluginConfiguration(['required_configuration' => 'A very important, required value.']);
    $event->addCacheTags(['custom_cache_tag']);

    $context = new Context(new ContextDefinition('string', NULL, TRUE), 'Explicitly passed in context.');
    $event->setContexts(['context' => $context]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[RenderEvents::SELECT_PAGE_DISPLAY_VARIANT][] = ['onSelectPageDisplayVariant'];
    return $events;
  }

}
