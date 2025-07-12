<?php

namespace Core\Console\Contracts;

interface CommandInterface
{
    public function signature(): string;
    public function handle(array $arguments): void;
}
