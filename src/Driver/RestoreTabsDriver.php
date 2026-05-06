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

use Machinateur\ChromeTabTransfer\File\WebsocketWdpClient;
use Machinateur\ChromeTabTransfer\Platform;
use Machinateur\ChromeTabTransfer\Shared\Console;
use Machinateur\ChromeTabTransfer\TabLoader\CurlReopenTabLoader;
use Machinateur\ChromeTabTransfer\TabLoader\TabLoaderInterface;
use Machinateur\ChromeTabTransfer\TabLoader\WdpReopenTabLoader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Use this driver to control start/stop another diver, like a proxy object passing calls through.
 *
 * The logic is (likely) the same for both drivers, so the call goes to ghe same URL, but with different backends (processes) if that makes snse.
 */
class RestoreTabsDriver extends AbstractDriver
{
    public function __construct(
        string                         $file,
        int                            $port,
        bool                           $debug,
        int                            $timeout,
        public readonly AbstractDriver $driver,
    ) {
        parent::__construct($file, $port, $debug, $timeout);
    }

    public function start(): void
    {
        // We have to enable the frontend accordingly, to be able to use the WDP script created above.
        if ($this->driver instanceof IosWebkitDebugProxy) {
            $process     = Platform::extractPropertyReference($this->driver, 'process');
            $commandline = & Platform::extractPropertyReference($process, 'commandline');
            // Splice in the `-f` value into the $commandline array, to enable the WDP frontend.
            \array_splice($commandline, \array_search('-F', $commandline), 1, ['-f', WebsocketWdpClient::FILENAME]);
        }

        $this->driver->start();
    }

    public function stop(): void
    {
        $this->driver->stop();
    }

    public function getUrl(): string
    {
        // Note the `%s` which is used in the `CurlReopenTabLoader`.
        return "http://localhost:{$this->port}/json/new?%s";
    }

    public function getTabLoader(): TabLoaderInterface
    {
        $console = $this->getConsole();

        // On iphone, the `/json/new?...` endpoint is not supported. Bummer. But I've found a workaround using WDP directly.
        if ($this->driver instanceof IosWebkitDebugProxy) {
            // The first tab is the target page in almost all scenarios. Usually the ID is updated automatically on
            //  establishing the connection (as well as on every opening of a new tab).
            $targetPage = (string)$console->input->getParameterOption('--wdp-target') ?: '1';

            $console->writeln("Creating new tab restorer for iOS using WDP directly (page {$targetPage}).");
            $console->writeln('<fg=black;bg=yellow>This is an experimental feature and might be unstable.</>');

            return (new WdpReopenTabLoader($this->port, $this->timeout, $this->file, $targetPage))
                ->setDebug($this->debug)
                ->setOutput($this->output);
        }

        $url  = $this->getUrl();

        $console->writeln("Creating new tab restorer for URL `{$url}` (timeout {$this->timeout}s)...", OutputInterface::VERBOSITY_DEBUG);

        // Use the default `/json/new?...` endpoint for android.
        return (new CurlReopenTabLoader($url, $this->timeout, $this->file))
            ->setOutput($this->output)
            ->setDebug($this->debug)
            ->setFileDate($this->date, $this->dateFormat);
    }

    /**
     * No output files.
     */
    public function getFileTemplates(array $tabs): array
    {
        return [];
    }

    /**
     * No check needed. The check is done through {@see \Machinateur\ChromeTabTransfer\Command\ReopenTabs::checkCommandEnvironment()}.
     */
    public function checkEnvironment(): bool
    {
        return true;
    }

    public function setConsole(Console $console): static
    {
        $this->driver->setConsole($console);
        return parent::setConsole($console);
    }

    public function setInput(InputInterface $input): static
    {
        $this->driver->setInput($input);
        return parent::setInput($input);
    }

    public function setOutput(OutputInterface $output): static
    {
        $this->driver->setOutput($output);
        return parent::setOutput($output);
    }

    public function setFileDate(?\DateTimeInterface $date, ?string $dateFormat): static
    {
        $this->driver->setFileDate($date, $dateFormat);
        return parent::setFileDate($date, $dateFormat);
    }
}
