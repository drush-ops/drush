<?php
namespace Unish;

/**
 *  @group slow
 *  @group commands
 */
class updateDBTest extends CommandUnishTestCase {

  protected $pathPostUpdate;

  public function testUpdateDBStatus() {
    $sites = $this->setUpDrupal(1, TRUE);
    $options = array(
      'yes' => NULL,
      'root' => $this->webroot(),
      'uri' => key($sites),
    );
    $this->drush('pm:enable', array('devel'), $options);
    $this->drush('updatedb:status', array(), $options + ['format' => 'json']);
    $out = $this->getOutputFromJSON();
    $this->assertNull($out);

    // Force a pending update.
    $this->drush('php-script', array('updatedb_script'), $options + array('script-path' => __DIR__ . '/resources'));

    // Assert that pending hook_update_n appears
    $this->drush('updatedb:status', array(), $options + ['format' => 'json']);
    $out = $this->getOutputFromJSON('devel_update_8002');
    $this->assertEquals('Add enforced dependencies to system.menu.devel', trim($out->description));

    // Run hook_update_n
    $this->drush('updatedb', array(), $options);

    // Assert that we ran hook_update_n properly
    $this->drush('updatedb:status', array(), $options + ['format' => 'json']);
    $out = $this->getOutputFromJSON();
    $this->assertNull($out);

    // Assure that a pending post-update is reported.
    $this->pathPostUpdate = $this->getSut() . '/web/modules/unish/devel/devel.post_update.php';
    copy(__DIR__ . '/resources/devel.post_update.php', $this->pathPostUpdate);
    $this->drush('updatedb:status', array(), $options + ['format' => 'json']);
    $out = $this->getOutputFromJSON('devel-post-null_op');
    $this->assertEquals('This is a test of the emergency broadcast system.', trim($out->description));
  }

  function tearDown() {
    $this->recursive_delete($this->pathPostUpdate, true);
    parent::tearDown();
  }
}
