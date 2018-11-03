<?php

namespace Drupal\Tests\block_content\Functional;

use Drupal\block_content\Entity\BlockContent;

/**
 * Tests the Views-powered listing of custom blocks.
 *
 * @group block_content
 * @see \Drupal\block\BlockContentListBuilder
 * @see \Drupal\block_content\Tests\BlockContentListTest
 */
class BlockContentListViewsTest extends BlockContentTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['block', 'block_content', 'config_translation', 'views'];

  /**
   * Tests the custom block listing page.
   */
  public function testListing() {
    $this->drupalLogin($this->drupalCreateUser(['administer blocks', 'translate configuration']));
    $this->drupalGet('admin/structure/block/block-content');

    // Test for the page title.
    $this->assertTitle(t('Custom block library') . ' | Drupal');

    // Test for the exposed filters.
    $this->assertFieldByName('info');
    $this->assertFieldByName('type');

    // Test for the table.
    $element = $this->xpath('//div[@class="layout-content"]//table');
    $this->assertTrue($element, 'Views table found.');

    // Test the table header.
    $elements = $this->xpath('//div[@class="layout-content"]//table/thead/tr/th');
    $this->assertEqual(count($elements), 4, 'Correct number of table header cells found.');

    // Test the contents of each th cell.
    $expected_items = ['Block description', 'Block type', 'Updated Sort ascending', 'Operations'];
    foreach ($elements as $key => $element) {
      if ($element->find('xpath', 'a')) {
        $this->assertIdentical(trim($element->find('xpath', 'a')->getText()), $expected_items[$key]);
      }
      else {
        $this->assertIdentical(trim($element->getText()), $expected_items[$key]);
      }
    }

    $label = 'Antelope';
    $new_label = 'Albatross';
    // Add a new entity using the operations link.
    $link_text = t('Add custom block');
    $this->assertLink($link_text);
    $this->clickLink($link_text);
    $this->assertResponse(200);
    $edit = [];
    $edit['info[0][value]'] = $label;
    $edit['body[0][value]'] = $this->randomMachineName(16);
    $this->drupalPostForm(NULL, $edit, t('Save'));

    // Confirm that once the user returns to the listing, the text of the label
    // (versus elsewhere on the page).
    $this->assertFieldByXpath('//td/a', $label, 'Label found for added block.');

    // Check the number of table row cells.
    $elements = $this->xpath('//div[@class="layout-content"]//table/tbody/tr/td');
    $this->assertEqual(count($elements), 4, 'Correct number of table row cells found.');
    // Check the contents of each row cell. The first cell contains the label,
    // the second contains the machine name, and the third contains the
    // operations list.
    $this->assertIdentical($elements[0]->find('xpath', 'a')->getText(), $label);

    // Edit the entity using the operations link.
    $blocks = $this->container
      ->get('entity.manager')
      ->getStorage('block_content')
      ->loadByProperties(['info' => $label]);
    $block = reset($blocks);
    if (!empty($block)) {
      $this->assertLinkByHref('block/' . $block->id());
      $this->clickLink(t('Edit'));
      $this->assertResponse(200);
      $this->assertTitle(strip_tags(t('Edit custom block %label', ['%label' => $label]) . ' | Drupal'));
      $edit = ['info[0][value]' => $new_label];
      $this->drupalPostForm(NULL, $edit, t('Save'));
    }
    else {
      $this->fail('Did not find Albatross block in the database.');
    }

    // Confirm that once the user returns to the listing, the text of the label
    // (versus elsewhere on the page).
    $this->assertFieldByXpath('//td/a', $new_label, 'Label found for updated custom block.');

    // Delete the added entity using the operations link.
    $this->assertLinkByHref('block/' . $block->id() . '/delete');
    $delete_text = t('Delete');
    $this->clickLink($delete_text);
    $this->assertResponse(200);
    $this->assertTitle(strip_tags(t('Are you sure you want to delete the custom block %label?', ['%label' => $new_label]) . ' | Drupal'));
    $this->drupalPostForm(NULL, [], $delete_text);

    // Verify that the text of the label and machine name does not appear in
    // the list (though it may appear elsewhere on the page).
    $this->assertNoFieldByXpath('//td', $new_label, 'No label found for deleted custom block.');

    // Confirm that the empty text is displayed.
    $this->assertText('There are no custom blocks available.');
    $this->assertLink('custom block');

    $block_content = BlockContent::create([
      'info' => 'Non-reusable block',
      'type' => 'basic',
      'reusable' => FALSE,
    ]);
    $block_content->save();

    $this->drupalGet('admin/structure/block/block-content');
    // Confirm that the empty text is displayed.
    $this->assertSession()->pageTextContains('There are no custom blocks available.');
    // Confirm the non-reusable block is not on the page.
    $this->assertSession()->pageTextNotContains('Non-reusable block');
  }

}
