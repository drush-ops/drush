<?php

namespace Drupal\Tests\content_moderation\Functional;

/**
 * Tests moderation state node type integration.
 *
 * @group content_moderation
 */
class ModerationStateNodeTypeTest extends ModerationStateTestBase {

  /**
   * A node type without moderation state disabled.
   *
   * @covers \Drupal\content_moderation\EntityTypeInfo::formAlter
   * @covers \Drupal\content_moderation\Entity\Handler\NodeModerationHandler::enforceRevisionsBundleFormAlter
   */
  public function testNotModerated() {
    $this->drupalLogin($this->adminUser);
    $this->createContentTypeFromUi('Not moderated', 'not_moderated');
    $this->assertText('The content type Not moderated has been added.');
    $this->grantUserPermissionToCreateContentOfType($this->adminUser, 'not_moderated');
    $this->drupalGet('node/add/not_moderated');
    $this->assertRaw('Save');
    $this->drupalPostForm(NULL, [
      'title[0][value]' => 'Test',
    ], t('Save'));
    $this->assertText('Not moderated Test has been created.');
  }

  /**
   * Tests enabling moderation on an existing node-type, with content.
   *
   * @covers \Drupal\content_moderation\EntityTypeInfo::formAlter
   * @covers \Drupal\content_moderation\Entity\Handler\NodeModerationHandler::enforceRevisionsBundleFormAlter
   */
  public function testEnablingOnExistingContent() {
    $editor_permissions = [
      'administer workflows',
      'access administration pages',
      'administer content types',
      'administer nodes',
      'view latest version',
      'view any unpublished content',
      'access content overview',
      'use editorial transition create_new_draft',
    ];
    $publish_permissions = array_merge($editor_permissions, ['use editorial transition publish']);
    $editor = $this->drupalCreateUser($editor_permissions);
    $editor_with_publish = $this->drupalCreateUser($publish_permissions);

    // Create a node type that is not moderated.
    $this->drupalLogin($editor);
    $this->createContentTypeFromUi('Not moderated', 'not_moderated');
    $this->grantUserPermissionToCreateContentOfType($editor, 'not_moderated');
    $this->grantUserPermissionToCreateContentOfType($editor_with_publish, 'not_moderated');

    // Create content.
    $this->drupalGet('node/add/not_moderated');
    $this->drupalPostForm(NULL, [
      'title[0][value]' => 'Test',
    ], t('Save'));
    $this->assertText('Not moderated Test has been created.');

    // Now enable moderation state.
    $this->enableModerationThroughUi('not_moderated');

    // And make sure it works.
    $nodes = \Drupal::entityTypeManager()->getStorage('node')
      ->loadByProperties(['title' => 'Test']);
    if (empty($nodes)) {
      $this->fail('Could not load node with title Test');
      return;
    }
    $node = reset($nodes);
    $this->drupalGet('node/' . $node->id());
    $this->assertResponse(200);
    $this->assertLinkByHref('node/' . $node->id() . '/edit');
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertResponse(200);
    $this->assertSession()->optionExists('moderation_state[0][state]', 'draft');
    $this->assertSession()->optionNotExists('moderation_state[0][state]', 'published');

    $this->drupalLogin($editor_with_publish);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertResponse(200);
    $this->assertSession()->optionExists('moderation_state[0][state]', 'draft');
    $this->assertSession()->optionExists('moderation_state[0][state]', 'published');
  }

}
