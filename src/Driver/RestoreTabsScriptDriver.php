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

use Machinateur\ChromeTabTransfer\TabLoader\ScriptReopenTabLoader;
use Machinateur\ChromeTabTransfer\TabLoader\TabLoaderInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RestoreTabsScriptDriver extends AbstractDriver
{
    public function __construct(
        string                 $file,
        int                    $port,
        bool                   $debug,
        int                    $timeout,
        public readonly string $callScript,
    ) {
        parent::__construct($file, $port, $debug, $timeout);
    }

    public function start(): void
    {
        $console = $this->getConsole();
        $console->writeln(' ==> ' . __METHOD__ . ':', OutputInterface::VERBOSITY_DEBUG);

        $console->writeln(' ==> ' . __METHOD__ . ': Done.', OutputInterface::VERBOSITY_DEBUG);
    }

    public function getTabLoader(): TabLoaderInterface
    {
        return (new ScriptReopenTabLoader($this->port, $this->timeout, $this->file, $this->getUrl()))
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

    public function stop(): void
    {
        $console = $this->getConsole();
        $console->writeln(' ==> ' . __METHOD__ . ':', OutputInterface::VERBOSITY_DEBUG);

        $console->writeln(' ==> ' . __METHOD__ . ': Done.', OutputInterface::VERBOSITY_DEBUG);
    }

    public function getUrl(): string
    {
        return "{$this->callScript}";
    }
}
