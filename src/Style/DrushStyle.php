<?php

namespace Drush\Style;

use Drush\Exceptions\UserAbortException;
use Symfony\Component\Console\Style\SymfonyStyle;

class DrushStyle extends SymfonyStyle
{

    /**
     * @param string $question
     * @param array $choices
     *   If an associative array is passed, the chosen *key* is returned.
     * @param null $default
     * @return mixed
     */
    public function choice($question, array $choices, $default = null)
    {
        $choices = array_merge(['cancel' => 'Cancel'], $choices) ;
        $choices_values = array_values($choices);
        $return = parent::choice($question, $choices_values, $default);
        if ($return == 'Cancel') {
            throw new UserAbortException();
        } else {
            return array_search($return, $choices);
        }
    }
}
