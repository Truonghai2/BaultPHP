<?php

namespace Core\Console;

use InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class Parser
{
    /**
     * Parse the given console command signature into a name and definition.
     *
     * @param  string  $signature
     * @return array   [string $name, array $arguments, array $options]
     * @throws \InvalidArgumentException
     */
    public static function parse(string $signature): array
    {
        if (! preg_match('/([^\s]+)\s*(.*)/', $signature, $matches)) {
            throw new InvalidArgumentException('Unable to parse signature: ' . $signature);
        }

        $name = $matches[1];
        $definition = $matches[2];

        $arguments = [];
        $options = [];

        preg_match_all('/\{\s*([^\s\}]+.*?)\s*\}/', $definition, $matches);

        foreach ($matches[1] as $token) {
            $parts = preg_split('/\s*:\s*/', $token, 2);
            $token = $parts[0];
            $description = $parts[1] ?? '';

            if (str_starts_with($token, '--')) {
                $options[] = self::parseOption($token, $description);
            } else {
                $arguments[] = self::parseArgument($token, $description);
            }
        }

        return [$name, $arguments, $options];
    }

    protected static function parseArgument(string $token, string $description): InputArgument
    {
        $name = rtrim($token, '?');
        $mode = str_ends_with($token, '?') ? InputArgument::OPTIONAL : InputArgument::REQUIRED;

        return new InputArgument($name, $mode, $description);
    }

    protected static function parseOption(string $token, string $description): InputOption
    {
        $token = ltrim($token, '-');
        $parts = explode('=', $token, 2);
        $name = $parts[0];

        if (str_ends_with($token, '=')) {
            return new InputOption($name, null, InputOption::VALUE_REQUIRED, $description);
        }

        return new InputOption($name, null, InputOption::VALUE_NONE, $description);
    }
}