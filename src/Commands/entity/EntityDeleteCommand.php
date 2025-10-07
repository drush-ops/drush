<?php

declare(strict_types=1);

namespace Drush\Commands\entity;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drush\Commands\AutowireTrait;
use Drush\Exceptions\UserAbortException;
use Drush\Style\DrushStyle;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Delete content entities.',
    aliases: ['edel', 'entity-delete'],
)]
final class EntityDeleteCommand extends Command
{
    use AutowireTrait;

    public const NAME = 'entity:delete';

    public function __construct(
        protected readonly EntityTypeManagerInterface $entityTypeManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('entity_type', InputArgument::REQUIRED, 'An entity machine name.')
            ->addArgument('ids', InputArgument::OPTIONAL, 'A comma delimited list of Ids.')
            ->addOption('bundle', null, InputOption::VALUE_REQUIRED, 'Restrict deletion to the specified bundle. Ignored when ids is specified.')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED, 'Exclude certain entities from deletion. Ignored when ids is specified.')
            ->addOption('chunks', null, InputOption::VALUE_REQUIRED, 'Specify how many entities will be deleted in the same step.', '50')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit on the number of entities to delete.')
            ->addUsage('entity:delete node --bundle=article')
            ->addUsage('entity:delete shortcut')
            ->addUsage('entity:delete node 22,24')
            ->addUsage('entity:delete user')
            ->addUsage('entity:delete node --exclude=9,14,81')
            ->addUsage('entity:delete node --chunks=5')
            ->addUsage('entity:delete node --limit=500');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $options = $input->getOptions();
        $io = new DrushStyle($input, $output);
        $entity_type = $input->getArgument('entity_type');
        $ids = $input->getArgument('ids');

        $query = $this->getQuery($entity_type, $ids, $options);
        $result = $query->execute();

        // Don't delete uid=1, uid=0.
        if ($entity_type === 'user') {
            unset($result[0], $result[1]);
        }

        if (empty($result)) {
            $io->success(dt('No matching entities found.'));
        } else {
            if (empty($options['limit']) && empty($ids)) {
                if (!$io->confirm(dt('You are about to delete !count entities. Do you wish to continue?', ['!count' => count($result)]), false)) {
                    throw new UserAbortException();
                }
            }

            $chunks = array_chunk($result, (int) $options['chunks'], true);
            $io->progressStart(count($chunks));
            foreach ($chunks as $chunk) {
                drush_op([$this, 'doDelete'], $entity_type, $chunk);
                $io->progressAdvance();
            }
            $io->progressFinish();
            $io->success(dt("Deleted !type entity Ids: !ids", ['!type' => $entity_type, '!ids' => implode(', ', array_values($result))]));
        }

        return self::SUCCESS;
    }

    /**
     * Actual delete method.
     *
     *
     * @throws InvalidPluginDefinitionException
     * @throws PluginNotFoundException
     * @throws EntityStorageException
     */
    public function doDelete(string $entity_type, array $ids): void
    {
        $storage = $this->entityTypeManager->getStorage($entity_type);
        $entities = $storage->loadMultiple($ids);
        $storage->delete($entities);
    }

    /**
     * @param string|null $ids
     * @throws InvalidPluginDefinitionException
     * @throws PluginNotFoundException
     */
    protected function getQuery(string $entity_type, ?string $ids, array $options): QueryInterface
    {
        $storage = $this->entityTypeManager->getStorage($entity_type);
        $query = $storage->getQuery()->accessCheck(false);
        if ($ids = StringUtils::csvToArray((string) $ids)) {
            $idKey = $this->entityTypeManager->getDefinition($entity_type)->getKey('id');
            $query = $query->condition($idKey, $ids, 'IN');
        } elseif ($options['bundle'] || $options['exclude'] || $options['limit']) {
            if ($exclude = StringUtils::csvToArray((string) $options['exclude'])) {
                $idKey = $this->entityTypeManager->getDefinition($entity_type)->getKey('id');
                $query = $query->condition($idKey, $exclude, 'NOT IN');
            }
            if ($bundle = $options['bundle']) {
                $bundleKey = $this->entityTypeManager->getDefinition($entity_type)->getKey('bundle');
                $query = $query->condition($bundleKey, $bundle);
            }
            if ($limit = $options['limit']) {
                $query->range(0, $limit);
            }
        }
        return $query;
    }
}
