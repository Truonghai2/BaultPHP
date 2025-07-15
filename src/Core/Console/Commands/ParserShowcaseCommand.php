<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;

class ParserShowcaseCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * This signature demonstrates all capabilities of the new Parser:
     * - required_arg: An argument that must be provided.
     * - optional_arg?: An argument that is optional.
     * - arg_with_default: An optional argument with a default value.
     * - required_array_arg*: A required argument that accepts multiple values.
     * - optional_array_arg?*: An optional argument that accepts multiple values.
     * - --flag: A simple boolean flag.
     * - --required_val=: An option that must have a value.
     * - --optional_val[=...]: An option that can have a value, with an optional default.
     * - --array_opt=*: An option that can be specified multiple times.
     * - --shortcut|-S: An option with a short version.
     * - --verbose|-v : A description for the option.
     *
     * @var string
     */
    protected string $signature = 'parser:showcase 
                                   {required_arg : The user ID that is required.}
                                   {optional_arg?}
                                   {arg_with_default=default_value}
                                   {required_array_arg*}
                                   {optional_array_arg?*}
                                   {--flag}
                                   {--required_val=}
                                   {--optional_val[=low]}
                                   {--array_opt=*}
                                   {--shortcut|-S}
                                   {--verbose|-v : Increase verbosity level.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Demonstrates all capabilities of the advanced command signature parser.';

    public function signature(): string
    {
        return $this->signature;
    }

    public function description(): string
    {
        return $this->description;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->comment('--- Demonstrating Command Signature Parser ---');

        $this->displayArguments();
        $this->displayOptions();

        $this->comment("\n--- End of Demonstration ---");
        return 0;
    }

    /**
     * Displays the parsed arguments from the command input.
     */
    private function displayArguments(): void
    {
        $this->info("\n<fg=yellow>Arguments:</>");
        $this->line("1. Required (required_arg): <fg=cyan>" . $this->argument('required_arg') . "</>");
        $this->line("2. Optional (optional_arg): <fg=cyan>" . ($this->argument('optional_arg') ?? '[Not Provided]') . "</>");
        $this->line("3. Optional w/ Default (arg_with_default): <fg=cyan>" . $this->argument('arg_with_default') . "</>");
        $this->line("4. Required Array (required_array_arg): <fg=cyan>" . $this->formatArrayOutput($this->argument('required_array_arg')) . "</>");
        $this->line("5. Optional Array (optional_array_arg): <fg=cyan>" . $this->formatArrayOutput($this->argument('optional_array_arg')) . "</>");
    }

    /**
     * Displays the parsed options from the command input.
     */
    private function displayOptions(): void
    {
        $this->info("\n<fg=yellow>Options:</>");
        $this->line("1. Flag (--flag): <fg=cyan>" . ($this->option('flag') ? 'true' : 'false') . "</>");
        $this->line("2. Value Required (--required_val): <fg=cyan>" . ($this->option('required_val') ?? '[Not Provided]') . "</>");
        $this->line("3. Value Optional (--optional_val): <fg=cyan>" . $this->option('optional_val') . "</>");
        $this->line("4. Array (--array_opt): <fg=cyan>" . $this->formatArrayOutput($this->option('array_opt')) . "</>");
        $this->line("5. Shortcut (--shortcut|-S): <fg=cyan>" . ($this->option('shortcut') ? 'true' : 'false') . "</>");
        $this->line("6. Verbose Flag (--verbose|-v): <fg=cyan>" . ($this->option('verbose') ? 'true' : 'false') . "</>");
    }

    /**
     * Formats an array for console output.
     *
     * @param array $data The array to format.
     * @return string The formatted string.
     */
    private function formatArrayOutput(array $data): string
    {
        if (empty($data)) {
            return '[Not Provided]';
        }

        return implode(', ', $data);
    }
}