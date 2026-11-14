<?php

declare(strict_types=1);

namespace Drush\Doctor\Commands\config;

use Drupal\Core\Config\StorageComparer;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A copy of core's \Drupal\Core\Config\Command\ConfigCommandTrait (core
 * commit e1b2512322). Remove it with the commands that use it.
 *
 * @internal
 */
trait ConfigCommandTrait
{
    /**
     * Prints the change list of a storage comparer as a table.
     */
    protected function renderTable(StorageComparer $comparer, SymfonyStyle $io): void
    {
        $translations = [
            'delete' => (string) $this->t('Delete'),
            'update' => (string) $this->t('Update'),
            'create' => (string) $this->t('Create'),
            'rename' => (string) $this->t('Rename'),
        ];
        $styles = [
            'delete' => 'fg=white;bg=red',
            'update' => 'fg=black;bg=yellow',
            'create' => 'fg=white;bg=green',
            'rename' => 'fg=black;bg=cyan',
        ];
        $header = [
            (string) $this->t('Collection'),
            (string) $this->t('Config'),
            (string) $this->t('Operation'),
        ];

        $rows = [];
        foreach ($comparer->getAllCollectionNames() as $collection) {
            foreach ($comparer->getChangelist(null, $collection) as $op => $names) {
                foreach ($names as $name) {
                    $rows[] = [
                        $collection,
                        $op !== 'rename' ? $name : str_replace('::', ' -> ', $name),
                        \sprintf('<%s>%s</>', $styles[$op], $translations[$op]),
                    ];
                }
            }
        }

        $io->table($header, $rows);
    }
}
