<?php

namespace Drupal\taxonomy\Tests;

/**
 * Ensure that the term indentation works properly.
 *
 * @group taxonomy
 */
class TaxonomyTermIndentationTest extends TaxonomyTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['taxonomy'];

  /**
   * Vocabulary for testing.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  protected function setUp() {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser(['administer taxonomy', 'bypass node access']));
    $this->vocabulary = $this->createVocabulary();
  }

  /**
   * Tests term indentation.
   */
  public function testTermIndentation() {
    // Create three taxonomy terms.
    $term1 = $this->createTerm($this->vocabulary);
    $term2 = $this->createTerm($this->vocabulary);
    $term3 = $this->createTerm($this->vocabulary);

    // Get the taxonomy storage.
    $taxonomy_storage = $this->container->get('entity.manager')->getStorage('taxonomy_term');

    // Indent the second term under the first one.
    $edit = [
      'terms[tid:' . $term2->id() . ':0][term][tid]' => 2,
      'terms[tid:' . $term2->id() . ':0][term][parent]' => 1,
      'terms[tid:' . $term2->id() . ':0][term][depth]' => 1,
      'terms[tid:' . $term2->id() . ':0][weight]' => 1,
    ];

    // Submit the edited form and check for HTML indentation element presence.
    $this->drupalPostForm('admin/structure/taxonomy/manage/' . $this->vocabulary->get('vid') . '/overview', $edit, t('Save'));
    $this->assertPattern('|<div class="js-indentation indentation">&nbsp;</div>|');

    // Check explicitly that term 2's parent is term 1.
    $parents = $taxonomy_storage->loadParents($term2->id());
    $this->assertEqual(key($parents), 1, 'Term 1 is the term 2\'s parent');

    // Move the second term back out to the root level.
    $edit = [
      'terms[tid:' . $term2->id() . ':0][term][tid]' => 2,
      'terms[tid:' . $term2->id() . ':0][term][parent]' => 0,
      'terms[tid:' . $term2->id() . ':0][term][depth]' => 0,
      'terms[tid:' . $term2->id() . ':0][weight]' => 1,
    ];

    $this->drupalPostForm('admin/structure/taxonomy/manage/' . $this->vocabulary->get('vid') . '/overview', $edit, t('Save'));
    // All terms back at the root level, no indentation should be present.
    $this->assertNoPattern('|<div class="js-indentation indentation">&nbsp;</div>|');

    // Check explicitly that term 2 has no parents.
    \Drupal::entityManager()->getStorage('taxonomy_term')->resetCache();
    $parents = $taxonomy_storage->loadParents($term2->id());
    $this->assertTrue(empty($parents), 'Term 2 has no parents now');
  }

}
