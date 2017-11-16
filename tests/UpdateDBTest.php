<?php
namespace Unish;

/**
 *  @group slow
 *  @group commands
 */
class UpdateDBTest extends CommandUnishTestCase {

    protected $pathPostUpdate;

    public function testUpdateDBStatus()
    {
        $this->setUpDrupal(1, true);
        $this->drush('pm:enable', ['devel']);
        $this->drush('updatedb:status', [], ['format' => 'json']);
        $out = $this->getOutputFromJSON();
        $this->assertNull($out);

        // Force a pending update.
        $this->drush('php-script', ['updatedb_script'], ['script-path' => __DIR__ . '/resources']);

        // Assert that pending hook_update_n appears
        $this->drush('updatedb:status', [], ['format' => 'json']);
        $out = $this->getOutputFromJSON('devel_update_8002');
        $this->assertEquals('Add enforced dependencies to system.menu.devel', trim($out->description));

        // Run hook_update_n
        $this->drush('updatedb', []);

        // Assert that we ran hook_update_n properly
        $this->drush('updatedb:status', [], ['format' => 'json']);
        $out = $this->getOutputFromJSON();
        $this->assertNull($out);

        // Assure that a pending post-update is reported.
        $this->pathPostUpdate = $this->getSut() . '/web/modules/unish/devel/devel.post_update.php';
        copy(__DIR__ . '/resources/devel.post_update.php', $this->pathPostUpdate);
        $this->drush('updatedb:status', [], ['format' => 'json']);
        $out = $this->getOutputFromJSON('devel-post-null_op');
        $this->assertEquals('This is a test of the emergency broadcast system.', trim($out->description));
    }

    public function tearDown()
    {
        $this->recursiveDelete($this->pathPostUpdate, true);
        parent::tearDown();
    }
}
