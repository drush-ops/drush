<?php

namespace Drupal\Tests\contact\Kernel\Migrate;

use Drupal\contact\Entity\ContactForm;
use Drupal\contact\ContactFormInterface;
use Drupal\Tests\migrate_drupal\Kernel\d6\MigrateDrupal6TestBase;

/**
 * Migrate contact categories to contact.form.*.yml.
 *
 * @group contact_category
 */
class MigrateContactCategoryTest extends MigrateDrupal6TestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['contact'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->executeMigration('contact_category');
  }

  /**
   * Performs various assertions on a single contact form entity.
   *
   * @param string $id
   *   The contact form ID.
   * @param string $expected_label
   *   The expected label.
   * @param string[] $expected_recipients
   *   The recipient e-mail addresses the form should have.
   * @param string $expected_reply
   *   The expected reply message.
   * @param int $expected_weight
   *   The contact form's expected weight.
   */
  protected function assertEntity($id, $expected_label, array $expected_recipients, $expected_reply, $expected_weight) {
    /** @var \Drupal\contact\ContactFormInterface $entity */
    $entity = ContactForm::load($id);
    $this->assertTrue($entity instanceof ContactFormInterface);
    $this->assertIdentical($expected_label, $entity->label());
    $this->assertIdentical($expected_recipients, $entity->getRecipients());
    $this->assertIdentical($expected_reply, $entity->getReply());
    $this->assertIdentical($expected_weight, $entity->getWeight());
  }

  /**
   * The Drupal 6 and 7 contact categories to Drupal 8 migration.
   */
  public function testContactCategory() {
    $this->assertEntity('website_feedback', 'Website feedback', ['admin@example.com'], '', 0);
    $this->assertEntity('some_other_category', 'Some other category', ['test@example.com'], 'Thanks for contacting us, we will reply ASAP!', 1);
    $this->assertEntity('a_category_much_longer_than_thir', 'A category much longer than thirty two characters', ['fortyninechars@example.com'], '', 2);

    // Test there are no duplicated roles.
    $contact_forms = [
      'website_feedback1',
      'some_other_category1',
      'a_category_much_longer_than_thir1',
    ];
    $this->assertEmpty(ContactForm::loadMultiple($contact_forms));

    /*
     * Remove the map row for the Website feedback contact form so that it
     * can be migrated again.
     */
    $id_map = $this->getMigration('contact_category')->getIdMap();
    $id_map->delete(['cid' => '1']);
    $this->executeMigration('contact_category');

    // Test there is a duplicate Website feedback form.
    $contact_form = ContactForm::load('website_feedback1');
    $this->assertEntity('website_feedback1', 'Website feedback', ['admin@example.com'], '', 0);
  }

}
