<?php

namespace Drush\Prompts;

use Closure;
use Illuminate\Support\Collection;
use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\MultiSearchPrompt;
use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\PasswordPrompt;
use Laravel\Prompts\Progress;
use Laravel\Prompts\SearchPrompt;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\Spinner;
use Laravel\Prompts\SuggestPrompt;
use Laravel\Prompts\TextPrompt;

/**
 * Prompt the user for text input.
 */
function text(string $label, string $placeholder = '', string $default = '', bool|string $required = false, Closure $validate = null, string $hint = ''): string
{
    return (new TextPrompt($label, $placeholder, $default, $required, $validate, $hint))->prompt();
}

/**
 * Prompt the user for input, hiding the value.
 */
function password(string $label, string $placeholder = '', bool|string $required = false, Closure $validate = null, string $hint = ''): string
{
    return (new PasswordPrompt($label, $placeholder, $required, $validate, $hint))->prompt();
}

/**
 * Prompt the user to select an option.
 *
 * @param  array<int|string, string>|Collection<int|string, string>  $options
 * @param  true|string  $required
 */
function select(string $label, array|Collection $options, int|string $default = null, int $scroll = 5, Closure $validate = null, string $hint = '', bool|string $required = true): int|string
{
    return (new SelectPrompt($label, $options, $default, $scroll, $validate, $hint, $required))->prompt();
}

/**
 * Prompt the user to select multiple options.
 *
 * @param  array<int|string, string>|Collection<int|string, string>  $options
 * @param  array<int|string>|Collection<int, int|string>  $default
 * @return array<int|string>
 */
function multiselect(string $label, array|Collection $options, array|Collection $default = [], int $scroll = 5, bool|string $required = false, Closure $validate = null, string $hint = 'Use the space bar to select options.'): array
{
    return (new MultiSelectPrompt($label, $options, $default, $scroll, $required, $validate, $hint))->prompt();
}

/**
 * Prompt the user to confirm an action.
 */
function confirm(string $label, bool $default = true, string $yes = 'Yes', string $no = 'No', bool|string $required = false, Closure $validate = null, string $hint = ''): bool
{
    return (new ConfirmPrompt($label, $default, $yes, $no, $required, $validate, $hint))->prompt();
}

/**
 * Prompt the user for text input with auto-completion.
 *
 * @param  array<string>|Collection<int, string>|Closure(string): array<string>  $options
 */
function suggest(string $label, array|Collection|Closure $options, string $placeholder = '', string $default = '', int $scroll = 5, bool|string $required = false, Closure $validate = null, string $hint = ''): string
{
    return (new SuggestPrompt($label, $options, $placeholder, $default, $scroll, $required, $validate, $hint))->prompt();
}

/**
 * Allow the user to search for an option.
 *
 * @param  Closure(string): array<int|string, string>  $options
 * @param  true|string  $required
 */
function search(string $label, Closure $options, string $placeholder = '', int $scroll = 5, Closure $validate = null, string $hint = '', bool|string $required = true): int|string
{
    return (new SearchPrompt($label, $options, $placeholder, $scroll, $validate, $hint, $required))->prompt();
}

/**
 * Allow the user to search for multiple option.
 *
 * @param  Closure(string): array<int|string, string>  $options
 * @return array<int|string>
 */
function multisearch(string $label, Closure $options, string $placeholder = '', int $scroll = 5, bool|string $required = false, Closure $validate = null, string $hint = 'Use the space bar to select options.'): array
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
function spin(Closure $callback, string $message = ''): mixed
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
 * @param  ?Closure((TSteps is int ? int : value-of<TSteps>), Progress<TSteps>): TReturn  $callback
 * @return ($callback is null ? Progress<TSteps> : array<TReturn>)
 */
function progress(string $label, iterable|int $steps, Closure $callback = null, string $hint = ''): array|Progress
{
    $progress = new Progress($label, $steps, $hint);

    if ($callback !== null) {
        return $progress->map($callback);
    }

    return $progress;
}
