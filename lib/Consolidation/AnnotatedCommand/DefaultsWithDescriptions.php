<?php

namespace Consolidation\AnnotatedCommand;

/**
 * An associative array that maps from key to default value;
 * each entry can also have a description.
 */
class DefaultsWithDescriptions
{
    /**
     * @var array Associative array of key : default mappings
     */
    protected $values;

    /**
     * @var array Associative array of key : description mappings
     */
    protected $descriptions;

    /**
     * @var mixed Default value that the default value of items in
     * the collection should take when not specified in the 'add' method.
     */
    protected $defaultDefault;

    public function __construct($values, $defaultDefault = null)
    {
        $this->values = $values;
        $this->descriptions = [];
        $this->defaultDefault = $defaultDefault;
    }

    /**
     * Return just the key : default values mapping
     *
     * @return array
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * Check to see whether the speicifed key exists in the collection.
     *
     * @param string $key
     * @return boolean
     */
    public function exists($key)
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * Get the value of one entry.
     *
     * @param string $key The key of the item.
     * @return string
     */
    public function get($key)
    {
        if (array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }
        return $this->defaultDefault;
    }

    /**
     * Get the description of one entry.
     *
     * @param string $key The key of the item.
     * @return string
     */
    public function getDescription($key)
    {
        if (array_key_exists($key, $this->descriptions)) {
            return $this->descriptions[$key];
        }
        return '';
    }

    /**
     * Add another argument to this command.
     *
     * @param string $key Name of the argument.
     * @param string $description Help text for the argument.
     * @param mixed $defaultValue The default value for the argument.
     */
    public function add($key, $description, $defaultValue = null)
    {
        if (!$this->exists($key) || isset($defaultValue)) {
            $this->values[$key] = isset($defaultValue) ? $defaultValue : $this->defaultDefault;
        }
        unset($this->descriptions[$key]);
        if (!empty($description)) {
            $this->descriptions[$key] = $description;
        }
    }

    /**
     * Change the default value of an entry.
     *
     * @param string $key
     * @param mixed $defaultValue
     */
    public function setDefaultValue($key, $defaultValue)
    {
        $this->values[$key] = $defaultValue;
    }

    /**
     * Remove an entry
     *
     * @param string $key The entry to remove
     */
    public function clear($key)
    {
        unset($this->values[$key]);
        unset($this->descriptions[$key]);
    }

    /**
     * Rename an existing option to something else.
     */
    public function rename($oldName, $newName)
    {
        $this->add($newName, $this->getDescription($oldName), $this->get($oldName));
        $this->clear($oldName);
    }
}
