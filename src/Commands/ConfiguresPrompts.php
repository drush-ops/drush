<?php

/**
 * Adapted from Illuminate\Console\Concerns
 */

namespace Drush\Commands;

use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\MultiSearchPrompt;
use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\PasswordPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SearchPrompt;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\SuggestPrompt;
use Laravel\Prompts\TextPrompt;
use Symfony\Component\Console\Input\InputInterface;
use Unish\UnishTestCase;

trait ConfiguresPrompts
{
    /**
     * Configure the prompt fallbacks.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return void
     */
    protected function configurePrompts(InputInterface $input)
    {
        Prompt::setOutput($this->output);

        Prompt::interactive(($input->isInteractive() && defined('STDIN') && stream_isatty(STDIN)) || $this->runningUnitTests());

        Prompt::fallbackWhen(!$input->isInteractive() || UnishTestCase::isWindows() || $this->runningUnitTests());

        TextPrompt::fallbackUsing(fn (TextPrompt $prompt) => $this->promptUntilValid(
            fn () => $this->io()->ask($prompt->label, $prompt->default ?: null) ?? '',
            $prompt->required,
            $prompt->validate
        ));

        PasswordPrompt::fallbackUsing(fn (PasswordPrompt $prompt) => $this->promptUntilValid(
            // @todo there is no secret().
            fn () => $this->io()->secret($prompt->label) ?? '',
            $prompt->required,
            $prompt->validate
        ));

        ConfirmPrompt::fallbackUsing(fn (ConfirmPrompt $prompt) => $this->promptUntilValid(
            fn () => $this->io()->confirm($prompt->label, $prompt->default),
            $prompt->required,
            $prompt->validate
        ));

        SelectPrompt::fallbackUsing(fn (SelectPrompt $prompt) => $this->promptUntilValid(
            fn () => $this->io()->choice($prompt->label, $prompt->options, $prompt->default),
            false,
            $prompt->validate
        ));

        MultiSelectPrompt::fallbackUsing(function (MultiSelectPrompt $prompt) {
            if ($prompt->default !== []) {
                return $this->promptUntilValid(
                    fn () => $this->io()->choice($prompt->label, $prompt->options, implode(',', $prompt->default), true),
                    $prompt->required,
                    $prompt->validate
                );
            }

            return $this->promptUntilValid(
                fn () => collect($this->io()->choice($prompt->label, ['' => 'None', ...$prompt->options], 'None', true))
                    ->reject('')
                    ->all(),
                $prompt->required,
                $prompt->validate
            );
        });

        SuggestPrompt::fallbackUsing(fn (SuggestPrompt $prompt) => $this->promptUntilValid(
            // @todo No askWithCompletion
            fn () => $this->io()->askWithCompletion($prompt->label, $prompt->options, $prompt->default ?: null) ?? '',
            $prompt->required,
            $prompt->validate
        ));

        SearchPrompt::fallbackUsing(fn (SearchPrompt $prompt) => $this->promptUntilValid(
            function () use ($prompt) {
                $query = $this->io()->ask($prompt->label);

                $options = ($prompt->options)($query);

                return $this->io()->choice($prompt->label, $options);
            },
            false,
            $prompt->validate
        ));

        MultiSearchPrompt::fallbackUsing(fn (MultiSearchPrompt $prompt) => $this->promptUntilValid(
            function () use ($prompt) {
                $query = $this->io()->ask($prompt->label);

                $options = ($prompt->options)($query);

                if ($prompt->required === false) {
                    if (array_is_list($options)) {
                        return collect($this->io()->choice($prompt->label, ['None', ...$options], 'None', true))
                            ->reject('None')
                            ->values()
                            ->all();
                    }

                    return collect($this->io()->choice($prompt->label, ['' => 'None', ...$options], '', true))
                        ->reject('')
                        ->values()
                        ->all();
                }

                return $this->io()->choice($prompt->label, $options, true);
            },
            $prompt->required,
            $prompt->validate
        ));
    }

    /**
     * Prompt the user until the given validation callback passes.
     *
     * @param  \Closure  $prompt
     * @param  bool|string  $required
     * @param  \Closure|null  $validate
     * @return mixed
     */
    protected function promptUntilValid($prompt, $required, $validate)
    {
        while (true) {
            $result = $prompt();

            if ($required && ($result === '' || $result === [] || $result === false)) {
                $this->io()->error(is_string($required) ? $required : 'Required.');

                continue;
            }

            if ($validate) {
                $error = $validate($result);

                if (is_string($error) && strlen($error) > 0) {
                    $this->io()->error($error);

                    continue;
                }
            }

            return $result;
        }
    }

    /**
     * Restore the prompts output.
     *
     * @return void
     */
    protected function restorePrompts()
    {
        Prompt::setOutput($this->output);
    }

    protected function runningUnitTests(): bool
    {
        if (! defined('PHPUNIT_COMPOSER_INSTALL') && ! defined('__PHPUNIT_PHAR__')) {
            // is not PHPUnit run
            return false;
        }
        return true;
    }
}
