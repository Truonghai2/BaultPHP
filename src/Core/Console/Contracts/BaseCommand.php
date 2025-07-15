<?php

namespace Core\Console\Contracts;

use Core\Console\Parser;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * The base class for all console commands in the application.
 *
 * This class provides a streamlined way to create commands by defining a signature,
 * handling input/output, and integrating with the application's service container.
 * It abstracts away much of the boilerplate required by Symfony Console.
 */
abstract class BaseCommand extends SymfonyCommand implements CommandInterface
{
	/**
	 * The Symfony Style instance for beautiful console output.
	 *
	 * @var \Symfony\Component\Console\Style\SymfonyStyle
	 */
	protected \Symfony\Component\Console\Style\SymfonyStyle $io;

	/**
	 * The input interface instance.
	 *
	 * @var \Symfony\Component\Console\Input\InputInterface
	 */
	protected \Symfony\Component\Console\Input\InputInterface $input;

	/**
	 * The application instance.
	 *
	 * @var \Core\Application
	 */
	protected \Core\Application $app;

	/**
	 * The signature of the command.
	 * This defines the command name, arguments, and options.
	 *
	 * @return string
	 */
	abstract public function signature(): string;

	/**
	 * The description of the command.
	 *
	 * @return string
	 */
	abstract public function description(): string;

	/**
	 * The handler for the command.
	 * This is where the command's logic is implemented.
	 *
	 * @return int The exit code (0 for success)
	 */
	abstract public function handle(): int;

	/**
	 * Configures the current command.
	 * This method is called by Symfony Console to set up the command.
	 */
	protected function configure(): void
	{
		// Parse the signature to get the name, arguments, and options
		[$name, $arguments, $options] = Parser::parse($this->signature());

		$this->setName($name)
			->setDescription($this->description())
			->setDefinition(array_merge($arguments, $options));
	}

	/**
	 * Executes the current command.
	 * This method is called by Symfony Console when the command is run.
	 * It initializes the I/O style and calls the main `handle` method.
	 *
	 * @param \Symfony\Component\Console\Input\InputInterface $input
	 * @param \Symfony\Component\Console\Output\OutputInterface $output
	 * @return int
	 */
	protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
	{
		$this->input = $input;
		$this->io = new \Symfony\Component\Console\Style\SymfonyStyle($input, $output);

		// The `handle` method is the single entry point for command logic.
		return $this->handle();
	}

	/**
	 * Get an argument from the input.
	 *
	 * @param string $key The name of the argument.
	 * @return mixed
	 */
	protected function argument(string $key): mixed
	{
		return $this->input->getArgument($key);
	}

	/**
	 * Get an option from the input.
	 *
	 * @param string $key The name of the option.
	 * @return mixed
	 */
	protected function option(string $key): mixed
	{
		return $this->input->getOption($key);
	}

	/**
	 * Write a string as standard output.
	 *
	 * @param string $string The message to write.
	 * @param string|null $style The style to apply (e.g., 'info', 'comment', 'question', 'error').
	 */
	public function line(string $string, string $style = null): void
	{
		$styled = $style ? "<{$style}>{$string}</{$style}>" : $string;
		$this->io->writeln($styled);
	}

	/**
	 * Write a string as information output.
	 *
	 * @param string $string The message to write.
	 */
	public function info(string $string): void
	{
		$this->line($string, 'info');
	}

	/**
	 * Write a string as comment output.
	 *
	 * @param string $string The message to write.
	 */
	public function comment(string $string): void
	{
		$this->line($string, 'comment');
	}

	/**
	 * Write a string as error output.
	 *
	 * @param string $string The message to write.
	 */
	public function error(string $string): void
	{
		$this->line($string, 'error');
	}

	/**
	 * Set the application instance.
	 * This is used by the ConsoleKernel to inject the core application container.
	 *
	 * @param \Core\Application $app
	 */
	public function setCoreApplication(\Core\Application $app): void
	{
		$this->app = $app;
	}
}
