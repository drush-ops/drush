<?php

namespace Unish;

use Drupal\Core\Language\Language;
use Drupal\language\Entity\ConfigurableLanguage;
use Webmozart\PathUtil\Path;

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
        $info_yml = Path::join($this->webroot(), 'modules/unish/drush_empty_module/drush_empty_module.info.yml');
        if (strpos(file_get_contents($info_yml), 'project:') === false || $this->isWindows()) {
            $this->markTestSkipped('Devel dev snapshot detected. Incompatible with translation import.');
        }

        $this->drush('pm-enable', ['language', 'locale', 'dblog']);
        $this->drush('config-set', ['locale.settings', 'translation.import_enabled', true]);

        // Setup the interface translation system and prepare a source translation file.
        // The test uses a local po file as translation source. This po file will be
        // imported from the translations directory when a module is enabled.
        $this->drush('config-set', ['locale.settings', 'translation.use_source', 'locale']);
        $this->drush('config-set', ['locale.settings', 'translation.default_filename', '%project.%language.po']);
        $this->drush('config-set', ['locale.settings', 'translation.path', '../translations']);
        $source = Path::join(__DIR__, 'resources/drush_empty_module.nl.po');
        $translationDir = Path::join($this->webroot(), '../translations');
        $this->mkdir($translationDir);
        copy($source, Path::join($translationDir, 'drush_empty_module.nl.po'));

        $this->drush('pm-enable', ['drush_empty_module']);
        $this->drush('language-add', ['nl']);

        $this->drush('watchdog-show', []);
        $this->assertContains('Translations imported:', $this->getSimplifiedOutput());

        // Clean up the mess this test creates.
        unlink(Path::join($translationDir, 'drush_empty_module.nl.po'));
        rmdir($translationDir);
    }
}
