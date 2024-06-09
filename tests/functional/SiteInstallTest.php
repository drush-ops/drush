<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\RoleCommands;
use Drush\Commands\core\StatusCommands;
use Drush\Commands\sql\SqlCommands;
use Unish\Utils\Fixtures;

/**
 * @group base
 * @group slow
 */
class SiteInstallTest extends CommandUnishTestCase
{
    use Fixtures;

    /**
     * Test functionality of installing a site with a database prefix.
     */
    public function testSiteInstallPrefix()
    {
        if ($this->dbDriver() === 'sqlite') {
            $this->markTestSkipped('SQLite do not prefix tables, it prefixes DB storage files');
        } else {
            // Install Drupal in "drupal_" prefix.
            $this->installDrupal('dev', true, ['db-prefix' => 'drupal_']);

            // Run 'core-status' and insure that we can bootstrap Drupal.
            $this->drush(StatusCommands::STATUS, [], ['fields' => 'bootstrap']);
            $output = $this->getOutput();
            $this->assertStringContainsString('Successful', $output);

            // Issue a query and check the result to verify the connection.
            $this->drush(SqlCommands::QUERY, ["SELECT uid FROM drupal_users where uid = 1;"]);
            $output = $this->getOutput();
            $this->assertStringContainsString('1', $output);
        }
    }

    /**
     * Test functionality of installing a site with a core recipe.
     */
    public function testSiteInstallRecipe()
    {
        // Install Drupal with our test recipe.
        $recipeDir = $this->fixturesDir() . '/recipes/test_recipe';
        $this->installDrupal('dev', true, ['profile' => $recipeDir]);

        // Run 'core-status' and insure that we can bootstrap Drupal.
        $this->drush(StatusCommands::STATUS, [], ['fields' => 'bootstrap']);
        $output = $this->getOutput();
        $this->assertStringContainsString('Successful', $output);

        // Fetch the Content Editor role and see if its label is 'Site Editor'.
        // The label of Content Editor in the Standard profile & recipe is
        // 'Content Editor', so if our expectation is satisfied, we know that
        // we must have installed from our recipe, and not from anywhere else.
        $this->drush(RoleCommands::LIST, [], ['format' => 'json']);
        $roles = $this->getOutputFromJSON();
        $this->assertEquals('Site editor', $roles['content_editor']['label']);
    }
}
