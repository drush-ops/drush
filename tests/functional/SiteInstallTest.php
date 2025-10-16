<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\RoleCommands;
use Drush\Commands\core\StatusCommands;
use Drush\Commands\sql\SqlCommands;
use Drush\Commands\core\SiteInstallCommands;
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
     * Test functionality of attempting to install a profile that does not exist.
     */
    public function testSiteInstallNoSuchProfile()
    {
        $this->drush(SiteInstallCommands::INSTALL, ['no_such_profile'], ['no-interaction' => null], null, null, self::EXIT_ERROR);
        $error_output = $this->getErrorOutput();
        $this->assertStringContainsString('The profile no_such_profile does not exist.', $error_output);
    }

    /**
     * Test functionality of attempting to install a recipe that does not exist.
     */
    public function testSiteInstallNoSuchRecipe()
    {
        $this->drush(SiteInstallCommands::INSTALL, ['core/recipes/no-such-recipe'], ['no-interaction' => null], null, null, self::EXIT_ERROR);
        $error_output = $this->getErrorOutput();
        $this->assertStringContainsString('Could not find a recipe.yml file for core/recipes/no-such-recipe', $error_output);
    }

    /**
     * Test functionality of attempting to install a recipe on a version of Drupal that does not support them.
     */
    public function testSiteInstallRecipesNotSupported()
    {
        if ($this->isDrupalGreaterThanOrEqualTo('10.3.0')) {
            $this->markTestSkipped('We can only test the recipes requirement check on versions prior to Drupal 10.3.0.');
        }

        if ($this->dbDriver() === 'sqlite') {
            $this->markTestSkipped('This test runs afoul of profile-selection code that does not work right with SQLite, since we have not set up the db-url for this test.');
        }

        $recipeDir = $this->fixturesDir() . '/recipes/test_recipe';
        $this->drush(SiteInstallCommands::INSTALL, [$recipeDir], ['no-interaction' => null], null, null, self::EXIT_ERROR);
        $error_output = $this->getErrorOutput();
        $this->assertStringContainsString('Recipes are only supported on Drupal 10.3.0 and later.', $error_output);
    }

    /**
     * Test functionality of installing a site with a recipe.
     */
    public function testSiteInstallRecipe()
    {
        if (!$this->isDrupalGreaterThanOrEqualTo('10.3.0')) {
            $this->markTestSkipped('Recipes require Drupal 10.3.0 or later.');
        }

        // Install Drupal with our test recipe.
        $recipeDir = $this->fixturesDir() . '/recipes/test_recipe';
        $this->installDrupal('dev', true, ['recipeOrProfile' => $recipeDir]);

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
