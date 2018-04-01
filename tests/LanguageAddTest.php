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

    public function testLanguageInfo()
    {
        $sites = $this->setUpDrupal(1, true);
        $uri = key($sites);
        $root = $this->webroot();
        $options = array(
            'root' => $root,
            'uri' => $uri,
        );

        $this->drush('pm-enable', ['language'], $options);
        $this->drush('language-info', [], $options);
        $this->assertContains('English (en)', $this->getSimplifiedOutput());
    }

    public function testLanguageAdd()
    {
        $sites = $this->setUpDrupal(1, true);
        $uri = key($sites);
        $root = $this->webroot();
        $options = array(
            'root' => $root,
            'uri' => $uri,
        );
        $this->drush('pm-enable', ['language'], $options);

        $this->drush('language-add', ['nl,fr'], $options + ['no-translations' => null]);

        $this->drush('language-info', [], $options);
        $this->assertContains('Dutch (nl)', $this->getSimplifiedOutput());
        $this->assertContains('French (fr)', $this->getSimplifiedOutput());
    }

    public function testLanguageAddWithTranslations()
    {
        $sites = $this->setUpDrupal(1, true);
        $uri = key($sites);
        $root = $this->webroot();
        $options = array(
            'root' => $root,
            'uri' => $uri,
        );

        $this->drush('pm-enable', ['language', 'locale', 'dblog'], $options);
        $this->drush('config-set', ['locale.settings', 'translation.import_enabled', true], $options);

        // Setup the interface translation system and prepare a source translation file.
        // The test uses a local po file as translation source. This po file will be
        // imported from the translations directory when a module is enabled.
        $this->drush('config-set', ['locale.settings', 'translation.use_source', 'locale'], $options);
        $this->drush('config-set', ['locale.settings', 'translation.default_filename', '%project.%language.po'], $options);
        $this->drush('config-set', ['locale.settings', 'translation.path', '../translations'], $options);
        $source = Path::join(__DIR__, '/resources/devel.nl.po');
        $translationDir = Path::join($root, '../translations');
        $this->mkdir($translationDir);
        copy($source, $translationDir . '/devel.nl.po');

        $this->drush('pm-enable', ['devel'], $options);
        $this->drush('language-add', ['nl'], $options);

        $this->drush('watchdog-show', [], $options);
        $this->assertContains('Translations imported:', $this->getSimplifiedOutput());

        // Clean up the mess this test creates.
        unlink($translationDir . '/devel.nl.po');
        rmdir($translationDir);
    }
}
