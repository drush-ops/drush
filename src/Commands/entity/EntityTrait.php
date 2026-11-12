<?php

declare(strict_types=1);

namespace Drush\Commands\entity;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Completion\CompletionInput;

/**
 * Helpers shared by the entity commands.
 *
 * Classes using this trait inject EntityTypeManagerInterface as
 * $this->entityTypeManager.
 */
trait EntityTrait
{
    use StringTranslationTrait;

    /**
     * Throws when no entity type has the given id.
     *
     * Call this at the top of __invoke() so an unknown entity type produces
     * a plain argument error instead of a plugin exception.
     */
    protected function validateEntityType(string $entityType): void
    {
        if (!$this->entityTypeManager->hasDefinition($entityType)) {
            throw new \InvalidArgumentException((string) $this->t('Unknown entity type: @type', ['@type' => $entityType]));
        }
    }

    /**
     * Builds the query selecting the entities a command operates on.
     *
     * An explicit list of ids wins; otherwise --bundle, --exclude and --limit
     * narrow the result. With none of them the query matches every entity of
     * the type.
     */
    protected function getQuery(string $entityType, ?string $ids, ?string $bundle, ?string $exclude, ?int $limit): QueryInterface
    {
        $definition = $this->entityTypeManager->getDefinition($entityType);
        $query = $this->entityTypeManager->getStorage($entityType)->getQuery()->accessCheck(false);
        if ($ids = StringUtils::csvToArray((string) $ids)) {
            $query->condition($definition->getKey('id'), $ids, 'IN');
        } else {
            if ($exclude = StringUtils::csvToArray((string) $exclude)) {
                $query->condition($definition->getKey('id'), $exclude, 'NOT IN');
            }
            if ($bundle) {
                $query->condition($definition->getKey('bundle'), $bundle);
            }
            if ($limit) {
                $query->range(0, $limit);
            }
        }
        return $query;
    }

    /**
     * Shell completion for the entity_type argument.
     *
     * Referenced from #[Argument(suggestedValues: [self::class, 'suggestEntityTypes'])].
     */
    public function suggestEntityTypes(CompletionInput $input): array
    {
        $types = array_filter(
            $this->entityTypeManager->getDefinitions(),
            fn($definition) => $definition instanceof ContentEntityTypeInterface
        );
        return array_keys($types);
    }
}
