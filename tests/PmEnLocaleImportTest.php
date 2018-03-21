<?php

namespace Unish;

use Drupal\Core\Language\Language;
use Drupal\language\Entity\ConfigurableLanguage;
use Webmozart\PathUtil\Path;

/**
 *  @group slow
 *  @group pm
 */
class PmEnLocaleImportCase extends CommandUnishTestCase
{

    public function testBatchImportTranslations()
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

        $this->drush('php-eval', ['\Drupal\language\Entity\ConfigurableLanguage::create([
            "id" => "nl",
            "label" => "Dutch",
        ])->save()']);

        $this->drush('pm-enable', ['devel'], $options);
        $this->drush('watchdog-show', [], $options);
        $this->assertContains('Translations imported:', $this->getSimplifiedOutput());

        // Clean up the mess this test creates.
        unlink($translationDir . '/devel.nl.po');
        rmdir($translationDir);
    }
}
