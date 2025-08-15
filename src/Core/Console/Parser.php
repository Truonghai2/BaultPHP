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
     * @return array{0: string, 1: \Symfony\Component\Console\Input\InputArgument[], 2: \Symfony\Component\Console\Input\InputOption[]}
     * @throws \InvalidArgumentException
     */
    public static function parse(string $signature): array
    {
        $name = static::name($signature);

        if (preg_match_all('/\{\s*(.*?)\s*\}/', $signature, $matches)) {
            if (count($matches[1])) {
                return [$name, ...static::parameters($matches[1])];
            }
        }

        return [$name, [], []];
    }

    /**
     * Extract the command name from the signature.
     *
     * @param  string  $signature
     * @return string
     * @throws \InvalidArgumentException
     */
    protected static function name(string $signature): string
    {
        if (! preg_match('/[^\s]+/', $signature, $matches)) {
            throw new InvalidArgumentException('Unable to determine command name from signature: ' . $signature);
        }

        return $matches[0];
    }

    /**
     * Extract all of the parameters from the tokens.
     *
     * @param  array  $tokens
     * @return array{\Symfony\Component\Console\Input\InputArgument[], \Symfony\Component\Console\Input\InputOption[]}
     */
    protected static function parameters(array $tokens): array
    {
        $arguments = [];
        $options = [];

        foreach ($tokens as $token) {
            if (str_starts_with($token, '--')) {
                $options[] = static::parseOption(ltrim($token, '-'));
            } else {
                $arguments[] = static::parseArgument($token);
            }
        }

        return [$arguments, $options];
    }

    /**
     * Parse an argument expression.
     *
     * @param  string  $token
     * @return \Symfony\Component\Console\Input\InputArgument
     */
    protected static function parseArgument(string $token): InputArgument
    {
        [$token, $description] = static::extractDescription($token);

        switch (true) {
            case str_ends_with($token, '?*'):
                return new InputArgument(trim($token, '?*'), InputArgument::IS_ARRAY | InputArgument::OPTIONAL, $description);
            case str_ends_with($token, '*'):
                return new InputArgument(trim($token, '*'), InputArgument::IS_ARRAY | InputArgument::REQUIRED, $description);
            case str_ends_with($token, '?'):
                return new InputArgument(trim($token, '?'), InputArgument::OPTIONAL, $description);
            case preg_match('/(.+)=(.+)/', $token, $matches):
                return new InputArgument($matches[1], InputArgument::OPTIONAL, $description, $matches[2]);
            default:
                return new InputArgument($token, InputArgument::REQUIRED, $description);
        }
    }

    /**
     * Parse an option expression.
     *
     * @param  string  $token
     * @return \Symfony\Component\Console\Input\InputOption
     */
    protected static function parseOption(string $token): InputOption
    {
        [$token, $description] = static::extractDescription($token);

        $matches = preg_split('/\s*\|\s*/', $token, 2);
        $shortcut = null;

        if (isset($matches[1])) {
            $shortcut = $matches[1];
            $token = $matches[0];
        }

        switch (true) {
            case preg_match('/^(.+)=(.+)$/', $token, $matches):
                return new InputOption($matches[1], $shortcut, InputOption::VALUE_OPTIONAL, $description, $matches[2]);
            case str_ends_with($token, '=*'):
                return new InputOption(trim($token, '=*'), $shortcut, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, $description);
            case str_ends_with($token, '='):
                return new InputOption(trim($token, '='), $shortcut, InputOption::VALUE_REQUIRED, $description);
            case preg_match('/(.+)\[=.*\]/', $token, $matches):
                return new InputOption($matches[1], $shortcut, InputOption::VALUE_OPTIONAL, $description);
            default:
                return new InputOption($token, $shortcut, InputOption::VALUE_NONE, $description);
        }
    }

    /**
     * Grab the description from the token.
     *
     * @param  string  $token
     * @return array{0: string, 1: string}
     */
    protected static function extractDescription(string $token): array
    {
        $parts = preg_split('/\s+:\s+/', trim($token), 2);

        if (count($parts) === 2) {
            return [trim($parts[0]), trim($parts[1])];
        }

        return [trim($token), ''];
    }
}
