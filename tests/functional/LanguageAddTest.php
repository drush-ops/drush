<?php

namespace Unish;

/**
 *  @group slow
 *  @group pm
 */
class LanguageAddCase extends CommandUnishTestCase
{
    protected function setUp()
    {
        parent::setUp();
        if (empty($this->getSites())) {
            $this->setUpDrupal(1, true);
            $this->drush('pm-enable', ['language']);
        }
    }

    public function testLanguageInfoAdd()
    {
        $this->drush('language-info', []);
        $this->assertContains('English (en)', $this->getSimplifiedOutput());

        $this->drush('language-add', ['nl,fr'], ['skip-translations' => null]);

        $this->drush('language-info', []);
        $this->assertContains('Dutch (nl)', $this->getSimplifiedOutput());
        $this->assertContains('French (fr)', $this->getSimplifiedOutput());
    }

    public function testLanguageAddWithTranslations()
    {
        $this->drush('pm-enable', ['language', 'locale', 'dblog']);
        $this->drush('config-set', ['locale.settings', 'translation.import_enabled', true]);
        $this->drush('config-set', ['locale.settings', 'translation.use_source', 'locale']);

        $this->drush('pm-enable', ['language_test']);
        $this->drush('language-add', ['nl']);

        $this->drush('watchdog-show', []);
        $this->assertContains('Translations imported:', $this->getSimplifiedOutput());
    }
}
