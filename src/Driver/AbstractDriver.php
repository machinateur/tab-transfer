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

namespace Machinateur\ChromeTabTransfer\Driver;

use Machinateur\ChromeTabTransfer\File\FileTemplateInterface;
use Machinateur\ChromeTabTransfer\File\JsonFile;
use Machinateur\ChromeTabTransfer\File\MarkdownFile;
use Machinateur\ChromeTabTransfer\Platform;
use Machinateur\ChromeTabTransfer\Shared\Console;
use Machinateur\ChromeTabTransfer\Shared\ConsoleTrait;
use Machinateur\ChromeTabTransfer\Shared\FileDateTrait;
use Machinateur\ChromeTabTransfer\TabLoader\CurlTabLoader;
use Machinateur\ChromeTabTransfer\TabLoader\TabLoaderInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method $this setConsole(Console $console)
 * @method $this setInput(InputInterface $input)
 * @method $this setOutput(OutputInterface $output)
 * @method $this setFileDate(?\DateTimeInterface $date, ?string $dateFormat)
 */
abstract class AbstractDriver implements DriverLifecycleInterface, DriverUrlInterface, DriverEnvironmentCheckInterface
{
    use ConsoleTrait {
        ConsoleTrait::__construct as private initializeConsole;
    }
    use FileDateTrait;

    public function __construct(
        public readonly string $file,
        public readonly int    $port,
        public readonly bool   $debug,
        public readonly int    $timeout,
    ) {
        $this->initializeConsole();
    }

    abstract public function start(): void;

    abstract public function stop(): void;

    abstract public function getUrl(): string;

    public function getTabLoader(): TabLoaderInterface
    {
        $url = $this->getUrl();

        $this->output->writeln("Creating new tab loader for URL `{$url}` (timeout {$this->timeout}s)...", OutputInterface::VERBOSITY_DEBUG);

        return (new CurlTabLoader($url, $this->timeout))
            ->setDebug($this->debug)
            ->setOutput($this->output);
    }

    /**
     * @return array<FileTemplateInterface>
     */
    public function getFileTemplates(array $tabs): array
    {
        return [
            new JsonFile($this->file, $tabs),
            new MarkdownFile($this->file, $tabs),
        ];
    }

    public function checkEnvironment(): bool
    {
        $shellCommand = $this->getShellCommand();
        if (empty($shellCommand)) {
            return true;
        }

        $console = $this->getConsole();
        $console->writeln("Checking for availability of `$shellCommand`.", OutputInterface::VERBOSITY_VERY_VERBOSE);

        $commandAvailable = Platform::isShellCommandAvailable($shellCommand);
        if ($commandAvailable) {
            $console->writeln("The `$shellCommand` command is available.", OutputInterface::VERBOSITY_VERY_VERBOSE);

            return true;
        } else {
            $console->writeln("The `$shellCommand` command was not found!", OutputInterface::VERBOSITY_VERY_VERBOSE);
        }

        $console->writeln("Checking for availability of `$shellCommand` executable.", OutputInterface::VERBOSITY_VERY_VERBOSE);

        $executableAvailable = \is_file($shellCommand) && Platform::isExecutable($shellCommand);
        if ($executableAvailable) {
            $console->writeln("The `$shellCommand` executable is available.", OutputInterface::VERBOSITY_VERY_VERBOSE);

            return true;
        } else {
            $console->writeln("The `$shellCommand` executable was not found!", OutputInterface::VERBOSITY_VERY_VERBOSE);
        }

        return false;
    }

    protected function getShellCommand(): ?string
    {
        return null;
    }
}
