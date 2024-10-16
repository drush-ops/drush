<?php

namespace Drush\Formatters;

interface FormatterConfigurationItemProviderInterface
{
    const KEY = '';

    public function getConfigurationItem(\ReflectionAttribute $attribute): array;
}
