<?php

namespace Drupal\Core\Ajax;

use Drupal\Core\Asset\AssetCollectionRendererInterface;
use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\AttachmentsInterface;
use Drupal\Core\Render\AttachmentsResponseProcessorInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Processes attachments of AJAX responses.
 *
 * @see \Drupal\Core\Ajax\AjaxResponse
 * @see \Drupal\Core\Render\MainContent\AjaxRenderer
 */
class AjaxResponseAttachmentsProcessor implements AttachmentsResponseProcessorInterface {

  /**
   * The asset resolver service.
   *
   * @var \Drupal\Core\Asset\AssetResolverInterface
   */
  protected $assetResolver;

  /**
   * A config object for the system performance configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The CSS asset collection renderer service.
   *
   * @var \Drupal\Core\Asset\AssetCollectionRendererInterface
   */
  protected $cssCollectionRenderer;

  /**
   * The JS asset collection renderer service.
   *
   * @var \Drupal\Core\Asset\AssetCollectionRendererInterface
   */
  protected $jsCollectionRenderer;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a AjaxResponseAttachmentsProcessor object.
   *
   * @param \Drupal\Core\Asset\AssetResolverInterface $asset_resolver
   *   An asset resolver.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Core\Asset\AssetCollectionRendererInterface $css_collection_renderer
   *   The CSS asset collection renderer.
   * @param \Drupal\Core\Asset\AssetCollectionRendererInterface $js_collection_renderer
   *   The JS asset collection renderer.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(AssetResolverInterface $asset_resolver, ConfigFactoryInterface $config_factory, AssetCollectionRendererInterface $css_collection_renderer, AssetCollectionRendererInterface $js_collection_renderer, RequestStack $request_stack, RendererInterface $renderer, ModuleHandlerInterface $module_handler) {
    $this->assetResolver = $asset_resolver;
    $this->config = $config_factory->get('system.performance');
    $this->cssCollectionRenderer = $css_collection_renderer;
    $this->jsCollectionRenderer = $js_collection_renderer;
    $this->requestStack = $request_stack;
    $this->renderer = $renderer;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function processAttachments(AttachmentsInterface $response) {
    // @todo Convert to assertion once https://www.drupal.org/node/2408013 lands
    if (!$response instanceof AjaxResponse) {
      throw new \InvalidArgumentException('\Drupal\Core\Ajax\AjaxResponse instance expected.');
    }

    $request = $this->requestStack->getCurrentRequest();

    if ($response->getContent() == '{}') {
      $response->setData($this->buildAttachmentsCommands($response, $request));
    }

    return $response;
  }

  /**
   * Prepares the AJAX commands to attach assets.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The AJAX response to update.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object that the AJAX is responding to.
   *
   * @return array
   *   An array of commands ready to be returned as JSON.
   */
  protected function buildAttachmentsCommands(AjaxResponse $response, Request $request) {
    $ajax_page_state = $request->request->get('ajax_page_state');

    // Aggregate CSS/JS if necessary, but only during normal site operation.
    $optimize_css = !defined('MAINTENANCE_MODE') && $this->config->get('css.preprocess');
    $optimize_js = !defined('MAINTENANCE_MODE') && $this->config->get('js.preprocess');

    $attachments = $response->getAttachments();

    // Resolve the attached libraries into asset collections.
    $assets = new AttachedAssets();
    $assets->setLibraries(isset($attachments['library']) ? $attachments['library'] : [])
      ->setAlreadyLoadedLibraries(isset($ajax_page_state['libraries']) ? explode(',', $ajax_page_state['libraries']) : [])
      ->setSettings(isset($attachments['drupalSettings']) ? $attachments['drupalSettings'] : []);
    $css_assets = $this->assetResolver->getCssAssets($assets, $optimize_css);
    list($js_assets_header, $js_assets_footer) = $this->assetResolver->getJsAssets($assets, $optimize_js);

    // First, AttachedAssets::setLibraries() ensures duplicate libraries are
    // removed: it converts it to a set of libraries if necessary. Second,
    // AssetResolver::getJsSettings() ensures $assets contains the final set of
    // JavaScript settings. AttachmentsResponseProcessorInterface also mandates
    // that the response it processes contains the final attachment values, so
    // update both the 'library' and 'drupalSettings' attachments accordingly.
    $attachments['library'] = $assets->getLibraries();
    $attachments['drupalSettings'] = $assets->getSettings();
    $response->setAttachments($attachments);

    // Render the HTML to load these files, and add AJAX commands to insert this
    // HTML in the page. Settings are handled separately, afterwards.
    $settings = [];
    if (isset($js_assets_header['drupalSettings'])) {
      $settings = $js_assets_header['drupalSettings']['data'];
      unset($js_assets_header['drupalSettings']);
    }
    if (isset($js_assets_footer['drupalSettings'])) {
      $settings = $js_assets_footer['drupalSettings']['data'];
      unset($js_assets_footer['drupalSettings']);
    }

    // Prepend commands to add the assets, preserving their relative order.
    $resource_commands = [];
    if ($css_assets) {
      $css_render_array = $this->cssCollectionRenderer->render($css_assets);
      $resource_commands[] = new AddCssCommand($this->renderer->renderPlain($css_render_array));
    }
    if ($js_assets_header) {
      $js_header_render_array = $this->jsCollectionRenderer->render($js_assets_header);
      $resource_commands[] = new PrependCommand('head', $this->renderer->renderPlain($js_header_render_array));
    }
    if ($js_assets_footer) {
      $js_footer_render_array = $this->jsCollectionRenderer->render($js_assets_footer);
      $resource_commands[] = new AppendCommand('body', $this->renderer->renderPlain($js_footer_render_array));
    }
    foreach (array_reverse($resource_commands) as $resource_command) {
      $response->addCommand($resource_command, TRUE);
    }

    // Prepend a command to merge changes and additions to drupalSettings.
    if (!empty($settings)) {
      // During Ajax requests basic path-specific settings are excluded from
      // new drupalSettings values. The original page where this request comes
      // from already has the right values. An Ajax request would update them
      // with values for the Ajax request and incorrectly override the page's
      // values.
      // @see system_js_settings_alter()
      unset($settings['path']);
      $response->addCommand(new SettingsCommand($settings, TRUE), TRUE);
    }

    $commands = $response->getCommands();
    $this->moduleHandler->alter('ajax_render', $commands);

    return $commands;
  }

}
