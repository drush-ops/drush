<?php

/**
 * @file
 * Contains \Drush\Psysh\Caster.
 */

namespace Drush\Psysh;

use Symfony\Component\VarDumper\Caster\Caster as BaseCaster;

/**
 * Caster class for VarDumper casters for the shell.
 */
class Caster
{

    /**
     * Casts \Drupal\Core\Entity\ContentEntityInterface classes.
     */
    public static function castContentEntity($entity, $array, $stub, $isNested)
    {
        if (!$isNested) {
            foreach ($entity as $property => $item) {
                $array[BaseCaster::PREFIX_PROTECTED . $property] = $item;
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
}
