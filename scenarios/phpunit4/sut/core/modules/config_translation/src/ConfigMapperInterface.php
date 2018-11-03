<?php

namespace Drupal\config_translation;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines an interface for configuration mapper.
 */
interface ConfigMapperInterface {

  /**
   * Returns title of this translation page.
   *
   * @return string
   *   The page title.
   */
  public function getTitle();

  /**
   * Sets the route collection.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The route collection.
   */
  public function setRouteCollection(RouteCollection $collection);

  /**
   * Returns the name of the base route the mapper is attached to.
   *
   * @return string
   *   The name of the base route the mapper is attached to.
   */
  public function getBaseRouteName();

  /**
   * Returns the route parameters for the base route the mapper is attached to.
   *
   * @return array
   */
  public function getBaseRouteParameters();

  /**
   * Returns the base route object the mapper is attached to.
   *
   * @return \Symfony\Component\Routing\Route
   *   The base route object the mapper is attached to.
   */
  public function getBaseRoute();

  /**
   * Returns a processed path for the base route the mapper is attached to.
   *
   * @return string
   *   Processed path with placeholders replaced.
   */
  public function getBasePath();

  /**
   * Returns route name for the translation overview route.
   *
   * @return string
   *   Route name for the mapper.
   */
  public function getOverviewRouteName();

  /**
   * Returns the route parameters for the translation overview route.
   *
   * @return array
   */
  public function getOverviewRouteParameters();

  /**
   * Returns the route object for a translation overview route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route object for the translation page.
   */
  public function getOverviewRoute();

  /**
   * Returns a processed path for the translation overview route.
   *
   * @return string
   *   Processed path with placeholders replaced.
   */
  public function getOverviewPath();

  /**
   * Returns route name for the translation add form route.
   *
   * @return string
   *   Route name for the mapper.
   */
  public function getAddRouteName();

  /**
   * Returns the route parameters for the translation add form route.
   *
   * @return array
   */
  public function getAddRouteParameters();

  /**
   * Returns the route object for a translation add form route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route object for the translation page.
   */
  public function getAddRoute();

  /**
   * Returns route name for the translation edit form route.
   *
   * @return string
   *   Route name for the mapper.
   */
  public function getEditRouteName();

  /**
   * Returns the route parameters for the translation edit form route.
   *
   * @return array
   */
  public function getEditRouteParameters();

  /**
   * Returns the route object for a translation edit form route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route object for the translation page.
   */
  public function getEditRoute();

  /**
   * Returns route name for the translation deletion route.
   *
   * @return string
   *   Route name for the mapper.
   */
  public function getDeleteRouteName();

  /**
   * Returns the route parameters for the translation deletion route.
   *
   * @return array
   */
  public function getDeleteRouteParameters();

  /**
   * Returns the route object for the translation deletion route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route object for the translation page.
   */
  public function getDeleteRoute();

  /**
   * Returns an array of configuration names for the mapper.
   *
   * @return array
   *   An array of configuration names for the mapper.
   */
  public function getConfigNames();

  /**
   * Adds the given configuration name to the list of names.
   *
   * Note that it is the responsibility of the calling code to ensure that the
   * configuration exists.
   *
   * @param string $name
   *   Configuration name.
   */
  public function addConfigName($name);

  /**
   * Returns the weight of the mapper.
   *
   * @return int
   *   The weight of the mapper.
   */
  public function getWeight();

  /**
   * Returns an array with all configuration data.
   *
   * @return array
   *   Configuration data keyed by configuration names.
   */
  public function getConfigData();

  /**
   * Returns the original language code of the configuration.
   *
   * @throws \RuntimeException
   *   Throws an exception if the language codes in the config files don't
   *   match.
   */
  public function getLangcode();

  /**
   * Returns the language code of a configuration object given its name.
   *
   * @param string $config_name
   *   The name of the configuration object.
   *
   * @return string
   *   The language code of the configuration object.
   */
  public function getLangcodeFromConfig($config_name);

  /**
   * Sets the original language code.
   *
   * @param string $langcode
   *   The langcode.
   *
   * @return $this
   */
  public function setLangcode($langcode);

  /**
   * Returns the name of the type of data the mapper encapsulates.
   *
   * @return string
   *   The name of the type of data the mapper encapsulates.
   */
  public function getTypeName();

  /**
   * Provides an array of information to build a list of operation links.
   *
   * @return array
   *   An associative array of operation link data for this list, keyed by
   *   operation name, containing the following key-value pairs:
   *   - title: The localized title of the operation.
   *   - href: The path for the operation.
   *   - options: An array of URL options for the path.
   *   - weight: The weight of this operation.
   */
  public function getOperations();

  /**
   * Returns the label of the type of data the mapper encapsulates.
   *
   * @return string
   *   The label of the type of data the mapper encapsulates.
   */
  public function getTypeLabel();

  /**
   * Checks that all pieces of this configuration mapper have a schema.
   *
   * @return bool
   *   TRUE if all of the elements have schema, FALSE otherwise.
   */
  public function hasSchema();

  /**
   * Checks if pieces of this configuration mapper have translatables.
   *
   * @return bool
   *   TRUE if at least one of the configuration elements has translatables,
   *   FALSE otherwise.
   */
  public function hasTranslatable();

  /**
   * Checks whether there is already a translation for this mapper.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   A language object.
   *
   * @return bool
   *   TRUE if any of the configuration elements have a translation in the
   *   given language, FALSE otherwise.
   */
  public function hasTranslation(LanguageInterface $language);

  /**
   * Populate the config mapper with route match data.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @see \Drupal\config_translation\Event\ConfigTranslationEvents::POPULATE_MAPPER
   */
  public function populateFromRouteMatch(RouteMatchInterface $route_match);

  /**
   * Returns the name of the contextual link group to add contextual links to.
   *
   * @return string|null
   *   A contextual link group name or null if no link should be added.
   */
  public function getContextualLinkGroup();

}
