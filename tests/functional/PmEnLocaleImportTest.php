<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Core\Language\Language;
use Drupal\language\Entity\ConfigurableLanguage;
use Drush\Drupal\Commands\config\ConfigCommands;
use Drush\Drupal\Commands\core\LanguageCommands;
use Drush\Drupal\Commands\core\WatchdogCommands;
use Drush\Drupal\Commands\pm\PmCommands;
use Symfony\Component\Filesystem\Path;

/**
 * @group locale
 * @group slow
 *  @group pm
 */
class PmEnLocaleImportTest extends CommandUnishTestCase
{
    public function testBatchImportTranslations()
    {
        $info_yml = Path::join($this->webroot(), 'modules/unish/drush_empty_module/drush_empty_module.info.yml');
        if (strpos(file_get_contents($info_yml), 'project:') === false || $this->isWindows()) {
            $this->markTestSkipped('Devel dev snapshot detected. Incompatible with translation import.');
        }

        $this->setUpDrupal(1, true);
        $root = $this->webroot();

        $this->drush(PmCommands::INSTALL, ['language', 'locale', 'dblog']);
        $this->drush(ConfigCommands::SET, ['locale.settings', 'translation.import_enabled', true]);

        // Setup the interface translation system and prepare a source translation file.
        // The test uses a local po file as translation source. This po file will be
        // imported from the translations directory when a module is enabled.
        $this->drush(ConfigCommands::SET, ['locale.settings', 'translation.use_source', 'locale']);
        $this->drush(ConfigCommands::SET, ['locale.settings', 'translation.default_filename', '%project.%language.po']);
        $this->drush(ConfigCommands::SET, ['locale.settings', 'translation.path', '../translations']);
        $source = Path::join(__DIR__, 'resources/drush_empty_module.nl.po');
        $translationDir = Path::join($root, '../translations');
        $this->mkdir($translationDir);
        copy($source, Path::join($translationDir, 'drush_empty_module.nl.po'));

        $this->drush(LanguageCommands::ADD, ['nl']);

        $this->drush(PmCommands::INSTALL, ['drush_empty_module']);
        $this->drush(WatchdogCommands::SHOW);
        $this->assertStringContainsString('Translations imported:', $this->getSimplifiedOutput());

        // Clean up the mess this test creates.
        unlink(Path::join($translationDir, 'drush_empty_module.nl.po'));
        rmdir($translationDir);
    }
}
