<?php

declare(strict_types=1);

namespace Drush\Preflight;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

class PreflightLog
{
    protected bool $debug;
    protected ?OutputInterface $output;

    public function __construct(?OutputInterface $output = null)
    {
        $this->output = $output ?: new StreamOutput(fopen('php://stderr', 'w'));
    }
    public function getDebug(): ?bool
    {
        return $this->debug;
    }

    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }

    public function log($message): void
    {
        if ($this->getDebug()) {
            $this->output->write(' [preflight] ' . $message . "\n");
        }
    }
}
