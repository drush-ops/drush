<?php

namespace Unish;

use Drupal\Core\Language\Language;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 *  @group slow
 *  @group pm
 */
class PmEnLocaleImportCase extends CommandUnishTestCase
{

    public function testBatchImportTranslations()
    {
        $this->setUpDrupal(1, true);
        $this->drush('pm-enable', ['language', 'locale', 'dblog']);
        $this->drush('config-set', ['locale.settings', 'translation.import_enabled', true]);

        $this->drush('php-eval', ['\Drupal\language\Entity\ConfigurableLanguage::create([
            "id" => "nl",
            "label" => "Dutch",
        ])->save()']);

        $this->drush('pm-enable', ['devel']);
        $this->drush('watchdog-show');
        $this->assertContains('Translations imported:', $this->getSimplifiedOutput());
    }
}
