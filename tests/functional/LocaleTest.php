<?php

namespace Unish;

use Webmozart\PathUtil\Path;

/**
 *  @group slow
 *  @group pm
 */
class LocaleTest extends CommandUnishTestCase
{

    public function testLocaleImport()
    {
        $this->setUpDrupal(1, true);
        $this->drush('pm:enable', ['language', 'locale']);
        $this->drush('language:add', ['nl'], ['skip-translations' => null]);

        $source = Path::join(__DIR__, '/resources/drush_empty_module.nl.po');

        $this->drush('locale:import', ['nl', $source]);
        $this->assertTranslation('Drush Empty Module', 'NL Drush Empty Module', 'nl', 0);

        // Import without override.
        $this->drush('sql:query', ["UPDATE locales_target SET translation = 'NO Drush Empty Module'"]);
        $this->assertTranslation('Drush Empty Module', 'NO Drush Empty Module', 'nl', 0);
        $this->drush('locale:import', ['nl', $source], ['override' => 'none']);
        $this->assertTranslation('Drush Empty Module', 'NO Drush Empty Module', 'nl', 0);

        // Import with override.
        $this->drush('sql:query', ["UPDATE locales_target SET translation = 'NO Drush Empty Module'"]);
        $this->assertTranslation('Drush Empty Module', 'NO Drush Empty Module', 'nl', 0);
        $this->drush('locale:import', ['nl', $source], ['override' => 'not-customized']);
        $this->assertTranslation('Drush Empty Module', 'NL Drush Empty Module', 'nl', 0);

        // Import without override of custom translation
        $this->drush('sql:query', ["UPDATE locales_target SET translation = 'NO Drush Empty Module', customized = 1"]);
        $this->assertTranslation('Drush Empty Module', 'NO Drush Empty Module', 'nl', 1);
        $this->drush('locale:import', ['nl', $source], ['override' => 'not-customized']);
        $this->assertTranslation('Drush Empty Module', 'NO Drush Empty Module', 'nl', 1);

        // Import with override of custom translation.
        $this->drush('sql:query', ["UPDATE locales_target SET translation = 'NO Drush Empty Module', customized = 1"]);
        $this->assertTranslation('Drush Empty Module', 'NO Drush Empty Module', 'nl', 1);
        $this->drush('locale:import', ['nl', $source], ['override' => 'customized']);
        $this->assertTranslation('Drush Empty Module', 'NL Drush Empty Module', 'nl', 0);

        // Import with override of custom translation as customized.
        $this->drush('sql:query', ["UPDATE locales_target SET translation = 'NO Drush Empty Module', customized = 1"]);
        $this->assertTranslation('Drush Empty Module', 'NO Drush Empty Module', 'nl', 1);
        $this->drush('locale:import', ['nl', $source], ['type' => 'customized', 'override' => 'customized']);
        $this->assertTranslation('Drush Empty Module', 'NL Drush Empty Module', 'nl', 1);
    }

    /**
     * @param string $source
     * @param string $translation
     * @param string $langcode
     * @param int $custom
     * @param string $context
     */
    private function assertTranslation($source, $translation, $langcode, $custom = 0, $context = '')
    {
        $this->drush('sql:query', ["SELECT ls.source, ls.context, lt.translation, lt.language, lt.customized FROM locales_source ls JOIN locales_target lt ON ls.lid = lt.lid WHERE ls.source = '$source' AND ls.context = '$context' AND lt.language = '$langcode'"]);
        $output = $this->getOutputAsList();
        $expected = "/$source.*$context.*$translation.*$langcode.*$custom/";
        $this->assertRegExp($expected, array_pop($output));
    }
}
