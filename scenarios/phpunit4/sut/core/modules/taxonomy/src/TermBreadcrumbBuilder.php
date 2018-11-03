<?php

namespace Drupal\taxonomy;

use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a custom taxonomy breadcrumb builder that uses the term hierarchy.
 */
class TermBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The taxonomy storage.
   *
   * @var \Drupal\Taxonomy\TermStorageInterface
   */
  protected $termStorage;

  /**
   * Constructs the TermBreadcrumbBuilder.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entityManager
   *   The entity manager.
   */
  public function __construct(EntityManagerInterface $entityManager) {
    $this->entityManager = $entityManager;
    $this->termStorage = $entityManager->getStorage('taxonomy_term');
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    return $route_match->getRouteName() == 'entity.taxonomy_term.canonical'
      && $route_match->getParameter('taxonomy_term') instanceof TermInterface;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));
    $term = $route_match->getParameter('taxonomy_term');
    // Breadcrumb needs to have terms cacheable metadata as a cacheable
    // dependency even though it is not shown in the breadcrumb because e.g. its
    // parent might have changed.
    $breadcrumb->addCacheableDependency($term);
    // @todo This overrides any other possible breadcrumb and is a pure
    //   hard-coded presumption. Make this behavior configurable per
    //   vocabulary or term.
    $parents = $this->termStorage->loadAllParents($term->id());
    // Remove current term being accessed.
    array_shift($parents);
    foreach (array_reverse($parents) as $term) {
      $term = $this->entityManager->getTranslationFromContext($term);
      $breadcrumb->addCacheableDependency($term);
      $breadcrumb->addLink(Link::createFromRoute($term->getName(), 'entity.taxonomy_term.canonical', ['taxonomy_term' => $term->id()]));
    }

    // This breadcrumb builder is based on a route parameter, and hence it
    // depends on the 'route' cache context.
    $breadcrumb->addCacheContexts(['route']);

    return $breadcrumb;
  }

}
