<?php

namespace Drupal\webprofiler;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\webprofiler\Compiler\DecoratorPass;
use Drupal\webprofiler\Compiler\EventPass;
use Drupal\webprofiler\Compiler\ProfilerPass;
use Drupal\webprofiler\Compiler\ServicePass;
use Drupal\webprofiler\Compiler\StoragePass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Defines a service profiler for the webprofiler module.
 */
class WebprofilerServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    // Add a compiler pass to discover all data collector services.
    $container->addCompilerPass(new ProfilerPass());

    // Add a compiler pass to discover all available storage backend.
    $container->addCompilerPass(new StoragePass());

    $container->addCompilerPass(new ServicePass(), PassConfig::TYPE_AFTER_REMOVING);
    $container->addCompilerPass(new DecoratorPass(), PassConfig::TYPE_AFTER_REMOVING);

    $modules = $container->getParameter('container.modules');

    // Add ViewsDataCollector only if Views module is enabled.
    if (isset($modules['views'])) {
      $container->register('webprofiler.views', 'Drupal\webprofiler\DataCollector\ViewsDataCollector')
        ->addArgument(new Reference(('views.executable')))
        ->addArgument(new Reference(('entity.manager')))
        ->addTag('data_collector', [
          'template' => '@webprofiler/Collector/views.html.twig',
          'id' => 'views',
          'title' => 'Views',
          'priority' => 75,
        ]);
    }

    // Add BlockDataCollector only if Block module is enabled.
    if (isset($modules['block'])) {
      $container->register('webprofiler.blocks', 'Drupal\webprofiler\DataCollector\BlocksDataCollector')
        ->addArgument(new Reference(('entity_type.manager')))
        ->addTag('data_collector', [
          'template' => '@webprofiler/Collector/blocks.html.twig',
          'id' => 'blocks',
          'title' => 'Blocks',
          'priority' => 78,
        ]);
    }

    // Add TranslationsDataCollector only if Locale module is enabled.
    if (isset($modules['locale'])) {
      $container->register('webprofiler.translations', 'Drupal\webprofiler\DataCollector\TranslationsDataCollector')
        ->addArgument(new Reference(('string_translation')))
        ->addArgument(new Reference(('url_generator')))
        ->addTag('data_collector', [
          'template' => '@webprofiler/Collector/translations.html.twig',
          'id' => 'translations',
          'title' => 'Translations',
          'priority' => 210,
        ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');

    // Alter the views.executable service only if Views module is enabled.
    if (isset($modules['views'])) {
      $container->getDefinition('views.executable')
        ->setClass('Drupal\webprofiler\Views\ViewExecutableFactoryWrapper');
    }

    // Replace the regular form_builder service with a traceable one.
    $container->getDefinition('form_builder')
      ->setClass('Drupal\webprofiler\Form\FormBuilderWrapper');

    // Replace the regular access_manager service with a traceable one.
    $container->getDefinition('access_manager')
      ->setClass('Drupal\webprofiler\Access\AccessManagerWrapper')
      ->addMethodCall('setDataCollector', [new Reference('webprofiler.request')]);

    // Replace the regular theme.negotiator service with a traceable one.
    $container->getDefinition('theme.negotiator')
      ->setClass('Drupal\webprofiler\Theme\ThemeNegotiatorWrapper');

    // Replace the regular config.factory service with a traceable one.
    $container->getDefinition('config.factory')
      ->setClass('Drupal\webprofiler\Config\ConfigFactoryWrapper')
      ->addMethodCall('setDataCollector', [new Reference('webprofiler.config')]);

    // Replace the regular string_translation service with a traceable one.
    $container->getDefinition('string_translation')
      ->setClass('Drupal\webprofiler\StringTranslation\TranslationManagerWrapper');

    // Replace the regular event_dispatcher service with a traceable one.
    $container->getDefinition('event_dispatcher')
      ->setClass('Drupal\webprofiler\EventDispatcher\TraceableEventDispatcher')
      ->addMethodCall('setStopwatch', [new Reference('stopwatch')]);

    $container->getDefinition('http_kernel.basic')
      ->replaceArgument(1, new Reference('webprofiler.debug.controller_resolver'));
  }

}
