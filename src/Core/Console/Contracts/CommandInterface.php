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
     *
     * @return int Should return 0 on success, or a non-zero integer on failure.
     */
    public function handle(): int;
}
