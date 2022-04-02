<?php

namespace Drush\Style;

use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class DrushStyle extends SymfonyStyle
{
    public function confirm(string $question, bool $default = true): bool
    {
        // Automatically accept confirmations if the --yes argument was supplied.
        if (Drush::affirmative()) {
            $this->comment($question . ': yes.');
            return true;
        } elseif (Drush::negative()) {
            // Automatically cancel confirmations if the --no argument was supplied.
            $this->warning($question . ': no.');
            return false;
        }
        return parent::confirm($question, $default);
    }

    public function choice(string $question, array $choices, mixed $default = null): mixed
    {
        // Display the choices without their keys.
        $choices_values = array_values($choices);
        $return = parent::choice($question, $choices_values, $default);

        return array_search($return, $choices);
    }

    public function warning(string|array $message)
    {
        $this->block($message, 'WARNING', 'fg=black;bg=yellow', ' ! ', true);
    }

    public function note(string|array $message)
    {
        $this->block($message, 'NOTE', 'fg=black;bg=yellow', ' ! ');
    }

    public function caution(string|array $message)
    {
        $this->block($message, 'CAUTION', 'fg=black;bg=yellow', ' ! ', true);
    }

    /**
     * @return mixed
     */
    public function askRequired($question)
    {
        $question = new Question($question);
        $question->setValidator(function (?string $value) {
            // FALSE is not considered as empty value because question helper use
            // it as negative answer on confirmation questions.
            if ($value === null || $value === '') {
                throw new \UnexpectedValueException('This value is required.');
            }

            return $value;
        });

        return $this->askQuestion($question);
    }
}
