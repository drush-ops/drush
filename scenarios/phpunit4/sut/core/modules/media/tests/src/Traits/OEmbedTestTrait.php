<?php

namespace Drupal\Tests\media\Traits;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\media\OEmbed\Provider;

/**
 * Contains helper functions for testing oEmbed functionality in isolation.
 */
trait OEmbedTestTrait {

  /**
   * Returns the relative path to the oEmbed fixtures directory.
   *
   * @return string
   */
  protected function getFixturesDirectory() {
    return drupal_get_path('module', 'media') . '/tests/fixtures/oembed';
  }

  /**
   * Returns the absolute URL of the oEmbed fixtures directory.
   *
   * @return string
   */
  protected function getFixturesUrl() {
    return $this->baseUrl . '/' . $this->getFixturesDirectory();
  }

  /**
   * Forces Media to use the provider database in the fixtures directory.
   */
  protected function useFixtureProviders() {
    $this->config('media.settings')
      ->set('oembed_providers_url', $this->getFixturesUrl() . '/providers.json')
      ->save();
  }

  /**
   * Configures the http_client service so that all requests are carried out
   * relative to the URL of the fixtures directory. For example, after calling
   * this method, a request for foobar.html will actually request
   * http://test-site/path/to/fuxtures/foobar.html.
   */
  protected function lockHttpClientToFixtures() {
    $this->writeSettings([
      'settings' => [
        'http_client_config' => [
          'base_uri' => (object) [
            'value' => $this->getFixturesUrl() . '/',
            'required' => TRUE,
          ],
        ],
      ],
    ]);
  }

  /**
   * Ensures that all oEmbed provider endpoints defined in the fixture
   * providers.json will use the media_test_oembed.resource.get route as their
   * URL.
   *
   * This requires the media_test_oembed module in order to work.
   */
  protected function hijackProviderEndpoints() {
    $providers = $this->getFixturesDirectory() . '/providers.json';
    $providers = file_get_contents($providers);
    $providers = Json::decode($providers);

    $endpoint_url = Url::fromRoute('media_test_oembed.resource.get')
      ->setAbsolute()
      ->toString();

    /** @var \Drupal\media_test_oembed\ProviderRepository $provider_repository */
    $provider_repository = $this->container->get('media.oembed.provider_repository');

    foreach ($providers as &$provider) {
      foreach ($provider['endpoints'] as &$endpoint) {
        $endpoint['url'] = $endpoint_url;
      }
      $provider_repository->setProvider(
        new Provider($provider['provider_name'], $provider['provider_url'], $provider['endpoints'])
      );
    }
  }

}
