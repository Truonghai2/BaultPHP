<?php

namespace Core\Console\Contracts;

interface CommandInterface
{
    /**
     * The signature of the console command.
     */
    public function signature(): string;

    /**
     * Execute the console command.
     */
    public function handle(array $arguments): void;
}