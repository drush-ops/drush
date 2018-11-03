<?php

namespace Drupal\Tests\book\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides common functionality for Book test classes.
 */
trait BookTestTrait {

  /**
   * A book node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $book;

  /**
   * A user with permission to create and edit books.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $bookAuthor;

  /**
   * Creates a new book with a page hierarchy.
   *
   * @param array $edit
   *   (optional) Field data in an associative array. Changes the current input
   *   fields (where possible) to the values indicated. Defaults to an empty
   *   array.
   *
   * @return \Drupal\node\NodeInterface[]
   */
  public function createBook($edit = []) {
    // Create new book.
    $this->drupalLogin($this->bookAuthor);

    $this->book = $this->createBookNode('new', NULL, $edit);
    $book = $this->book;

    /*
     * Add page hierarchy to book.
     * Book
     *  |- Node 0
     *   |- Node 1
     *   |- Node 2
     *  |- Node 3
     *  |- Node 4
     */
    $nodes = [];
    // Node 0.
    $nodes[] = $this->createBookNode($book->id(), NULL, $edit);
    // Node 1.
    $nodes[] = $this->createBookNode($book->id(), $nodes[0]->book['nid'], $edit);
    // Node 2.
    $nodes[] = $this->createBookNode($book->id(), $nodes[0]->book['nid'], $edit);
    // Node 3.
    $nodes[] = $this->createBookNode($book->id(), NULL, $edit);
    // Node 4.
    $nodes[] = $this->createBookNode($book->id(), NULL, $edit);

    $this->drupalLogout();

    return $nodes;
  }

  /**
   * Checks the outline of sub-pages; previous, up, and next.
   *
   * Also checks the printer friendly version of the outline.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   Node to check.
   * @param $nodes
   *   Nodes that should be in outline.
   * @param $previous
   *   Previous link node.
   * @param $up
   *   Up link node.
   * @param $next
   *   Next link node.
   * @param array $breadcrumb
   *   The nodes that should be displayed in the breadcrumb.
   */
  public function checkBookNode(EntityInterface $node, $nodes, $previous, $up, $next, array $breadcrumb) {
    // $number does not use drupal_static as it should not be reset
    // since it uniquely identifies each call to checkBookNode().
    static $number = 0;
    $this->drupalGet('node/' . $node->id());

    // Check outline structure.
    if ($nodes !== NULL) {
      $this->assertPattern($this->generateOutlinePattern($nodes), format_string('Node @number outline confirmed.', ['@number' => $number]));
    }
    else {
      $this->pass(format_string('Node %number does not have outline.', ['%number' => $number]));
    }

    // Check previous, up, and next links.
    if ($previous) {
      /** @var \Drupal\Core\Url $url */
      $url = $previous->urlInfo();
      $url->setOptions(['attributes' => ['rel' => ['prev'], 'title' => t('Go to previous page')]]);
      $text = new FormattableMarkup('<b>‹</b> @label', ['@label' => $previous->label()]);
      $this->assertRaw(\Drupal::l($text, $url), 'Previous page link found.');
    }

    if ($up) {
      /** @var \Drupal\Core\Url $url */
      $url = $up->urlInfo();
      $url->setOptions(['attributes' => ['title' => t('Go to parent page')]]);
      $this->assertRaw(\Drupal::l('Up', $url), 'Up page link found.');
    }

    if ($next) {
      /** @var \Drupal\Core\Url $url */
      $url = $next->urlInfo();
      $url->setOptions(['attributes' => ['rel' => ['next'], 'title' => t('Go to next page')]]);
      $text = new FormattableMarkup('@label <b>›</b>', ['@label' => $next->label()]);
      $this->assertRaw(\Drupal::l($text, $url), 'Next page link found.');
    }

    // Compute the expected breadcrumb.
    $expected_breadcrumb = [];
    $expected_breadcrumb[] = \Drupal::url('<front>');
    foreach ($breadcrumb as $a_node) {
      $expected_breadcrumb[] = $a_node->url();
    }

    // Fetch links in the current breadcrumb.
    $links = $this->xpath('//nav[@class="breadcrumb"]/ol/li/a');
    $got_breadcrumb = [];
    foreach ($links as $link) {
      $got_breadcrumb[] = $link->getAttribute('href');
    }

    // Compare expected and got breadcrumbs.
    $this->assertIdentical($expected_breadcrumb, $got_breadcrumb, 'The breadcrumb is correctly displayed on the page.');

    // Check printer friendly version.
    $this->drupalGet('book/export/html/' . $node->id());
    $this->assertText($node->label(), 'Printer friendly title found.');
    $this->assertRaw($node->body->processed, 'Printer friendly body found.');

    $number++;
  }

  /**
   * Creates a regular expression to check for the sub-nodes in the outline.
   *
   * @param array $nodes
   *   An array of nodes to check in outline.
   *
   * @return string
   *   A regular expression that locates sub-nodes of the outline.
   */
  public function generateOutlinePattern($nodes) {
    $outline = '';
    foreach ($nodes as $node) {
      $outline .= '(node\/' . $node->id() . ')(.*?)(' . $node->label() . ')(.*?)';
    }

    return '/<nav id="book-navigation-' . $this->book->id() . '"(.*?)<ul(.*?)' . $outline . '<\/ul>/s';
  }

  /**
   * Creates a book node.
   *
   * @param int|string $book_nid
   *   A book node ID or set to 'new' to create a new book.
   * @param int|null $parent
   *   (optional) Parent book reference ID. Defaults to NULL.
   * @param array $edit
   *   (optional) Field data in an associative array. Changes the current input
   *   fields (where possible) to the values indicated. Defaults to an empty
   *   array.
   *
   * @return \Drupal\node\NodeInterface
   *   The created node.
   */
  public function createBookNode($book_nid, $parent = NULL, $edit = []) {
    // $number does not use drupal_static as it should not be reset
    // since it uniquely identifies each call to createBookNode().
    // Used to ensure that when sorted nodes stay in same order.
    static $number = 0;

    $edit['title[0][value]'] = str_pad($number, 2, '0', STR_PAD_LEFT) . ' - SimpleTest test node ' . $this->randomMachineName(10);
    $edit['body[0][value]'] = 'SimpleTest test body ' . $this->randomMachineName(32) . ' ' . $this->randomMachineName(32);
    $edit['book[bid]'] = $book_nid;

    if ($parent !== NULL) {
      $this->drupalPostForm('node/add/book', $edit, t('Change book (update list of parents)'));

      $edit['book[pid]'] = $parent;
      $this->drupalPostForm(NULL, $edit, t('Save'));
      // Make sure the parent was flagged as having children.
      $parent_node = \Drupal::entityManager()->getStorage('node')->loadUnchanged($parent);
      $this->assertFalse(empty($parent_node->book['has_children']), 'Parent node is marked as having children');
    }
    else {
      $this->drupalPostForm('node/add/book', $edit, t('Save'));
    }

    // Check to make sure the book node was created.
    $node = $this->drupalGetNodeByTitle($edit['title[0][value]']);
    $this->assertNotNull(($node === FALSE ? NULL : $node), 'Book node found in database.');
    $number++;

    return $node;
  }

}
