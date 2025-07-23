<?php

namespace Drush\Style;

use Drush\Drush;
use JetBrains\PhpStorm\Deprecated;
use Laravel\Prompts\MultiSearchPrompt;
use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\PasswordPrompt;
use Laravel\Prompts\Progress;
use Laravel\Prompts\SearchPrompt;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\Spinner;
use Laravel\Prompts\SuggestPrompt;
use Laravel\Prompts\TextPrompt;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

class DrushStyle extends SymfonyStyle
{
    public function confirm(string $question, bool $default = true, string $yes = 'Yes', string $no = 'No', bool|string $required = false, ?\Closure $validate = null, string $hint = ''): bool
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
        return confirm($question, $default, $yes, $no, $required, $validate, $hint);
    }

    #[Deprecated('Use select() or multiselect() instead.')]
    public function choice(string $question, array $choices, mixed $default = null, bool $multiSelect = false, int $scroll = 10, ?\Closure $validate = null, string $hint = '', bool|string $required = true): mixed
    {
        if ($multiSelect) {
            // For backward compat. Deprecated.
            return multiselect($question, $choices, $default ?? [], $scroll, $required, $validate, $hint);
        } else {
            return select($question, $choices, $default, $scroll, $validate, $hint, $required);
        }
    }

    /**
     * Prompt the user for text input.
     */
    public function ask(
        \Stringable|string $question,
        ?string $default = null,
        #[Deprecated('Use $validate parameter instead.')]
        ?callable $validator = null,
        \Stringable|string $placeholder = '',
        bool|string $required = false,
        ?\Closure $validate = null,
        \Stringable|string $hint = ''
    ): mixed {
        assert($validator === null, 'The $validator parameter is non-functional. Use $validate instead.');
        return (new TextPrompt($question, $placeholder, (string)$default, $required, $validate, $hint))->prompt();
    }

    /**
     * Prompt the user for input, hiding the value.
     */
    public function password(\Stringable|string $label, \Stringable|string $placeholder = '', bool|string $required = false, ?\Closure $validate = null, \Stringable|string $hint = ''): string
    {
        return (new PasswordPrompt($label, $placeholder, $required, $validate, $hint))->prompt();
    }

    /**
     * Prompt the user to select an option.
     *
     * @param  array<int|string, string>  $options
     * @param  true|string  $required
     */
    public function select(string $label, array $options, int|string|null $default = null, int $scroll = 10, ?\Closure $validate = null, string $hint = '', bool|string $required = true): int|string
    {
        return (new SelectPrompt($label, $options, $default, $scroll, $validate, $hint, $required))->prompt();
    }

    /**
     * Prompt the user to select multiple options.
     *
     * @param  array<int|string, string>  $options
     * @param  array<int|string>  $default
     * @return array<int|string>
     */
    public function multiselect(string $label, array $options, array $default = [], int $scroll = 10, bool|string $required = false, ?\Closure $validate = null, string $hint = 'Use the space bar to select options.'): array
    {
        return (new MultiSelectPrompt($label, $options, $default, $scroll, $required, $validate, $hint))->prompt();
    }

    /**
     * Prompt the user for text input with auto-completion.
     *
     * @param  array<string>|\Closure(string): array<string>  $options
     */
    public function suggest(string $label, array|\Closure $options, string $placeholder = '', string $default = '', int $scroll = 10, bool|string $required = false, ?\Closure $validate = null, string $hint = 'Start typing the first letter(s) and matching choices will be shown.'): string
    {
        return (new SuggestPrompt($label, $options, $placeholder, $default, $scroll, $required, $validate, $hint))->prompt();
    }

    /**
     * Allow the user to search for an option.
     *
     * @param  \Closure(string): array<int|string, string>  $options
     * @param  true|string  $required
     */
    public function search(string $label, \Closure $options, string $placeholder = '', int $scroll = 10, ?\Closure $validate = null, string $hint = '', bool|string $required = true): int|string
    {
        return (new SearchPrompt($label, $options, $placeholder, $scroll, $validate, $hint, $required))->prompt();
    }

    /**
     * Allow the user to search for multiple option.
     *
     * @param  \Closure(string): array<int|string, string>  $options
     * @return array<int|string>
     */
    public function multisearch(string $label, \Closure $options, string $placeholder = '', int $scroll = 10, bool|string $required = false, ?\Closure $validate = null, string $hint = 'Use the space bar to select options.'): array
    {
        return (new MultiSearchPrompt($label, $options, $placeholder, $scroll, $required, $validate, $hint))->prompt();
    }

    /**
     * Render a spinner while the given callback is executing.
     *
     * @template TReturn of mixed
     *
     * @param  \Closure(): TReturn  $callback
     * @return TReturn
     */
    public function spin(\Closure $callback, string $message = ''): mixed
    {
        return (new Spinner($message))->spin($callback);
    }

    /**
     * Display a progress bar.
     *
     * @template TSteps of iterable<mixed>|int
     * @template TReturn
     *
     * @param  TSteps  $steps
     * @param  ?\Closure((TSteps is int ? int : value-of<TSteps>), Progress<TSteps>): TReturn  $callback
     * @return ($callback is null ? Progress<TSteps> : array<TReturn>)
     */
    public function progress(string $label, iterable|int $steps, ?\Closure $callback = null, string $hint = ''): array|Progress
    {
        $progress = new Progress($label, $steps, $hint);

        if ($callback !== null) {
            return $progress->map($callback);
        }

        return $progress;
    }

    public function warning(string|array $message): void
    {
        $this->block($message, 'WARNING', 'fg=black;bg=yellow', ' ! ', true);
    }

    public function note(string|array $message): void
    {
        $this->block($message, 'NOTE', 'fg=black;bg=yellow', ' ! ');
    }

    public function caution(string|array $message): void
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
