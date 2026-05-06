<?php
/*
 * MIT License
 *
 * Copyright (c) 2021-2026 machinateur
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

declare(strict_types=1);

namespace Machinateur\ChromeTabTransfer\Command;

use Machinateur\ChromeTabTransfer\Shared\Console;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckEnvironment extends Command
{
    public const NAME = 'check-environment';

    protected function configure(): void
    {
        $this
            ->setName(self::NAME)
            ->setDescription('Check environment for required dependencies on your system. Implicitly executed before copying tabs.')
            ->addOption('driver', 'i', InputOption::VALUE_REQUIRED, 'The driver name to use for the check. If not given (default) check for all drivers.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output, ?AbstractCopyTabsCommand $command = null): int
    {
        $console    = new Console($input, $output);

        // When no `$command` is provided with the call (i.e. direct invocation via CLI).
        if ( ! $command instanceof AbstractCopyTabsCommand) {
            $driverName = $this->getArgumentDriver($console);

            $console->writeln("No command provided.", OutputInterface::VERBOSITY_VERY_VERBOSE);

            // Only check the specified driver, if the `--driver` (`-i`) option was specified.
            if (null !== $driverName) {
                $console->writeln("Driver name specified: {$driverName}.", OutputInterface::VERBOSITY_VERY_VERBOSE);

                $command = $this->findCommand($console, $driverName);
            }
        }

        if ($command instanceof AbstractCopyTabsCommand) {
            $result = $this->check($console, $command);
        } else {
            $console->writeln('Falling back to checking all available drivers...', OutputInterface::VERBOSITY_VERY_VERBOSE);

            $result = true;

            // Perform checks on all of them, if no single one is specified (default).
            foreach ($this->getCommands() as $command) {
                $result = $result && $this->check($console, $command);
            }
        }

        if (!$result) {
            $console->error('Environment check failed!');
            return Command::FAILURE;
        }

        $console->success('Environment check successful!');
        return Command::SUCCESS;
    }

    protected function check(Console $console, AbstractCopyTabsCommand $command): bool
    {
        return $command->checkCommandEnvironment($console);
    }

    public function findCommand(Console $console, string $driverName): ?AbstractCopyTabsCommand
    {
        $console->writeln("Searching for command with driver `{$driverName}`.", OutputInterface::VERBOSITY_VERY_VERBOSE);

        try {
            $command = $this->getApplication()
                ->find($commandName = "copy-tabs:{$driverName}");

            if ( ! $command instanceof AbstractCopyTabsCommand) {
                $console->writeln(\sprintf("Found command `{$commandName}` but not not compatible with interface (driver was `{$driverName}`)."), OutputInterface::VERBOSITY_VERY_VERBOSE);

                return null;
            }

            $console->writeln("Found command `{$command->getName()}` for driver `{$driverName}`.", OutputInterface::VERBOSITY_VERY_VERBOSE);

            return $command;
        } catch (CommandNotFoundException $exception) {
            if ($alternatives = $exception->getAlternatives()) {
                $console->writeln(\sprintf("No command `{$commandName}` found for driver `{$driverName}`: %s", \implode('`, `', $alternatives)), OutputInterface::VERBOSITY_VERY_VERBOSE);
            } else {
                $console->writeln("No command `{$commandName}` found for driver `{$driverName}.`", OutputInterface::VERBOSITY_VERY_VERBOSE);
            }
        }

        return null;
    }

    /**
     * Load all the available commands inside the `copy-tabs` namespace from the application.
     *
     * @return array<AbstractCopyTabsCommand>
     */
    private function getCommands(): array
    {
        $commands = $this->getApplication()
            ->all('copy-tabs');

        // Make sure only actual instances of the `AbstractCOpyTabsCommand` base class are returned.
        return \array_filter($commands, static fn(Command $command): bool => $command instanceof AbstractCopyTabsCommand);
    }

    protected function getArgumentDriver(Console $console): ?string
    {
        return $console->input->getOption('driver');
    }
}
