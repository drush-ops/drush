<?php
namespace Drush\Boot;

/**
 * Preprocess commandline arguments
 *
 * If we are still going to support --php and --php-options flags, then
 * we need to remove those here as well (or add them to the Symfony
 * application).
 */
class PreprocessArgs
{
        public function __construct($argv)
        {

        }

        public function args()
        {
            return [];
        }

        public function alias()
        {
            return false;
        }

        public function selectedSite()
        {
            return false;
        }

        public function configPath()
        {
            return false;
        }

        public function aliasPath()
        {
            return false;
        }
}
