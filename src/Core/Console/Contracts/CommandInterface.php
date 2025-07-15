<?php

namespace Core\Console\Contracts;

interface CommandInterface
{
    /**
     * The signature of the command.
     *
     * @return string
     */
    public function signature(): string;

    /**
     * The description of the command.
     *
     * @return string
     */
    public function description(): string;

    /**
     * The handler for the command.
     */
    public function handle(): int;
}