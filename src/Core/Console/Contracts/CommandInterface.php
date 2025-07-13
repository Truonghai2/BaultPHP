<?php

namespace Core\Console\Contracts;

interface CommandInterface
{
    /**
     * The signature of the command.
     * This defines the command name, arguments, and options.
     * e.g., 'make:controller {name} {--api}'
     *
     * @return string
     */
    public function signature(): string;

    /**
     * The description of the command.
     * This will be shown in the 'list' command.
     *
     * @return string
     */
    public function description(): string;

    /**
     * Execute the console command.
     *
     * @param array $args The raw arguments from the command line.
     */
    public function handle(array $args = []): void;
}