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

namespace Machinateur\ChromeTabTransfer\TabLoader;

use Machinateur\ChromeTabTransfer\Driver\AndroidDebugBridge;
use Machinateur\ChromeTabTransfer\Shared\Console;
use Machinateur\ChromeTabTransfer\Shared\ConsoleTrait;
use Machinateur\ChromeTabTransfer\Shared\DebugFlagTrait;
use Machinateur\ChromeTabTransfer\Shared\FileDateTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * A tab restorer that uses `adb` shell commands to open the input URLs.
 *
 * A possible alternative would be to use a custom WS client (without `origin` headers),
 *  but this is less error-prone when there are again internal changes.
 */
class AdbReopenTabLoader implements TabLoaderInterface
{
    use ConsoleTrait {
        ConsoleTrait::__construct           as private initializeConsole;

        ConsoleTrait::setOutput   as private parent__setOutput;
        ConsoleTrait::setInput    as private parent__setInput;
        ConsoleTrait::setConsole  as private parent__setConsole;
    }
    use DebugFlagTrait {
        DebugFlagTrait::setDebug    as private parent__setDebug;
    }
    use FileDateTrait {
        FileDateTrait::setFileDate as private parent__setFileDate;
    }
    use JsonFileTabLoaderTrait {
        JsonFileTabLoaderTrait::__construct as private initializeJsonFileTabLoader;
    }

    /**
     * The default delay to wait after command execution: `2ms`.
     *
     * @var int
     */
    public const DEFAULT_DELAY = 1000 * AndroidDebugBridge::DEFAULT_DELAY;

    /**
     * @param int $timeout process timeout
     * @param int $delay   wait-time in micro-seconds after process calls
     */
    public function __construct(
        public readonly string $url,
        public readonly int    $timeout,
        public readonly string $file,
        public readonly int    $delay   = self::DEFAULT_DELAY,
    ) {
        $this->initializeConsole();

        $this->initializeJsonFileTabLoader($this->file);
    }

    public function load(): array
    {
        $tabs      = $this->loadTabs();
        $tabsCount = \count($this->tabs);

        $console = $this->getConsole();
        $console->writeln(' ==> ' . __METHOD__ . ':', OutputInterface::VERBOSITY_DEBUG);

        $console->comment("Uploading tabs {$tabsCount} to device...");

        if ( ! $this->debug) {
            $console->progressStart($tabsCount);
        }

        foreach ($tabs as $index => & $tab) {
            ++$index;
            $url = (string) ($tab['url'] ?? '');

            if ($this->debug) {
                $console->comment("Calling `adb` intent ({$index}/{$tabsCount})...");
            }

            if ('' !== $url) {
                $this->callAdbIntent('android.intent.action.VIEW', $url);
            } else {
                $console->writeln('Skipping `adb` intent for empty URL.', OutputInterface::VERBOSITY_DEBUG);
            }

            $tab['transferred'] = true;

            \usleep($this->delay);

            if ( ! $this->debug) {
                $console->progressAdvance();
            }
        }

        if ( ! $this->debug) {
            $console->progressFinish();
        }

        $console->writeln(' ==> ' . __METHOD__ . ': Done.', OutputInterface::VERBOSITY_DEBUG);

        return $tabs;

    }

    protected function callAdbIntent(string $activity, string $url): void
    {
        $console = $this->getConsole();
        $process = new Process(['adb', 'shell', 'am', 'start', '-a' , $activity, '-d', $url], timeout: $this->timeout);

        $this->debug && $console->writeln("> adb shell am start -a {$activity} -d {$url}", OutputInterface::VERBOSITY_DEBUG);

        $process->start();
        $process->wait();

        if ($this->debug) {
            $combinedProcessOutput = \array_filter(
                \array_map(
                    static fn(string $line): string => "< {$line}",
                    \array_merge(
                        \explode(\PHP_EOL, $process->getOutput()),
                        \explode(\PHP_EOL, $process->getErrorOutput()),
                    )
                )
            );

            $console->writeln($combinedProcessOutput);
            $console->newLine();
        }
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function setOutput(OutputInterface $output): static
    {
        $this->jsonFileLoader->setOutput($output);
        return $this->parent__setOutput($output);
    }

    public function setInput(InputInterface $input): static
    {
        $this->jsonFileLoader->setInput($input);
        return $this->parent__setInput($input);
    }

    public function setConsole(Console $console): static
    {
        $this->jsonFileLoader->setConsole($console);
        return $this->parent__setConsole($console);
    }

    public function setDebug(bool $debug): static
    {
        $this->jsonFileLoader->setDebug($debug);
        return $this->parent__setDebug($debug);
    }

    public function setFileDate(?\DateTimeInterface $date, ?string $dateFormat): static
    {
        $this->jsonFileLoader->setFileDate($date, $dateFormat);
        return $this->parent__setFileDate($date, $dateFormat);
    }

}
