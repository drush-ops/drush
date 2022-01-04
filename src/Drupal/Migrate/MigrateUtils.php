<?php

namespace Drush\Drupal\Migrate;

/**
 * Utility methods.
 */
class MigrateUtils
{
    /**
     * Parses as an array the list of IDs received from console.
     *
     * IDs are delimited by comma. Each ID consists in one are many ID columns,
     * separated by a colon (":").
     *
     * @param string|null $idlist
     *
     * @return array
     */
    public static function parseIdList(?string $idlist): array
    {
        $idlist = array_filter(str_getcsv((string) $idlist));
        array_walk($idlist, function (string &$value) {
            $value = str_getcsv(trim($value), ':');
        });
        return $idlist;
    }
}
