<?php

declare(strict_types=1);

namespace Drush\Psysh;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceFieldItemList;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList;
use Symfony\Component\VarDumper\Caster\Caster as BaseCaster;

/**
 * Caster class for VarDumper casters for the shell.
 */
class Caster
{
    /**
     * Casts \Drupal\Core\Entity\ContentEntityInterface classes.
     */
    public static function castContentEntity(ContentEntityInterface $entity, $array, $stub, $isNested)
    {
        if (!$isNested) {
            $array[BaseCaster::PREFIX_VIRTUAL . 'translationLanguages'] = implode(', ', array_keys($entity->getTranslationLanguages()));
            foreach ($entity->getFields() as $fieldName => $fieldItemList) {
                $fieldStorageDefinition = $fieldItemList->getFieldDefinition()
                    ->getFieldStorageDefinition();
                $value = $fieldItemList->getValue();
                $key = sprintf('%s (%s)', BaseCaster::PREFIX_VIRTUAL . $fieldName, $fieldStorageDefinition->getType());
                if (count($value) > 1) {
                    $key .= ' x' . count($value);
                }
                if ($fieldItemList instanceof EntityReferenceFieldItemListInterface) {
                    $value = self::handleReferences($value, $fieldItemList, $fieldStorageDefinition);
                }
                // Truncate long values.
                array_walk_recursive($value, function (&$x) {
                    if (is_string($x) && strlen($x) > 200) {
                        $x = Unicode::truncate($x, 80, false, true);
                    }
                });
                // Collapse single value'd field values.
                if (count($value[0] ?? []) === 1) {
                    // Filter out non-strings.
                    $to_implode = array_filter(array_column($value, array_keys($value[0])[0]), fn ($v) => is_string($v));
                    $value = implode(', ', $to_implode);
                }
                $array[$key] = $value;
            }
        }

        return $array;
    }

    /**
     * Casts \Drupal\Core\Field\FieldItemListInterface classes.
     */
    public static function castFieldItemList($list_item, $array, $stub, $isNested)
    {
        if (!$isNested) {
            foreach ($list_item as $delta => $item) {
                $array[BaseCaster::PREFIX_VIRTUAL . $delta] = $item;
            }
        }

        return $array;
    }

    /**
     * Casts \Drupal\Core\Field\FieldItemInterface classes.
     */
    public static function castFieldItem($item, $array, $stub, $isNested)
    {
        if (!$isNested) {
            $array[BaseCaster::PREFIX_VIRTUAL . 'value'] = $item->getValue();
        }

        return $array;
    }

    /**
     * Casts \Drupal\Core\Config\Entity\ConfigEntityInterface classes.
     */
    public static function castConfigEntity($entity, $array, $stub, $isNested)
    {
        if (!$isNested) {
            foreach ($entity->toArray() as $property => $value) {
                $array[BaseCaster::PREFIX_PROTECTED . $property] = $value;
            }
        }

        return $array;
    }

    /**
     * Casts \Drupal\Core\Config\ConfigBase classes.
     */
    public static function castConfig($config, $array, $stub, $isNested)
    {
        if (!$isNested) {
            foreach ($config->get() as $property => $value) {
                $array[BaseCaster::PREFIX_VIRTUAL . $property] = $value;
            }
        }

        return $array;
    }

    /**
     * Casts \Drupal\Component\DependencyInjection\Container classes.
     */
    public static function castContainer($container, $array, $stub, $isNested)
    {
        if (!$isNested) {
            $service_ids = $container->getServiceIds();
            sort($service_ids);
            foreach ($service_ids as $service_id) {
                $service = $container->get($service_id);
                $array[BaseCaster::PREFIX_VIRTUAL . $service_id] = is_object($service) ? get_class($service) : $service;
            }
        }

        return $array;
    }

    /**
     * Casts \Drupal\Component\Render\MarkupInterface classes.
     */
    public static function castMarkup($markup, $array, $stub, $isNested)
    {
        if (!$isNested) {
            $array[BaseCaster::PREFIX_VIRTUAL . 'markup'] = (string) $markup;
        }

        return $array;
    }

    protected static function handleReferences(array $value, EntityReferenceFieldItemListInterface $fieldItemList, FieldStorageDefinitionInterface $fieldStorageDefinition): array
    {
        if ($target_type = $fieldStorageDefinition->getSetting('target_type')) {
            $shortClass = self::getShortClass($target_type);
        }
        if ($fieldItemList instanceof EntityReferenceRevisionsFieldItemList) {
            $callback = fn ($item) => ['!ref' => sprintf('%s::loadRevision(%d) (id: %d)', $shortClass, $item['target_revision_id'], $item['target_id'])];
        } elseif ($fieldItemList instanceof DynamicEntityReferenceFieldItemList) {
            $callback = fn ($item) => ['!ref' => sprintf('%s::load(%s)', self::getShortClass($item['target_type']), $item['target_id'])];
        } elseif ($fieldStorageDefinition->getColumns()['target_id']['type'] === 'int') {
            $callback = fn ($item) => ['!ref' => sprintf('%s::load(%d)', $shortClass, $item['target_id'])];
        }
        return isset($callback) ? array_map($callback, $value) : $value;
    }

    protected static function getShortClass(string $entity_type_id): string
    {
        $class = \Drupal::entityTypeManager()
            ->getDefinition($entity_type_id)
            ->getClass();
        $parts = explode('\\', $class);
        return end($parts);
    }
}
