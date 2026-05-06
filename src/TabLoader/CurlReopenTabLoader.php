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

use Machinateur\ChromeTabTransfer\Exception\TabReopenFailedException;
use Machinateur\ChromeTabTransfer\File\ReopenScriptFile;
use Machinateur\ChromeTabTransfer\Shared\Console;
use Machinateur\ChromeTabTransfer\Shared\FileDateTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CurlReopenTabLoader extends CurlTabLoader
{
    use FileDateTrait {
        FileDateTrait::setFileDate          as private parent__setFileDate;
    }
    use JsonFileTabLoaderTrait {
        JsonFileTabLoaderTrait::__construct as private initializeJsonFileTabLoader;
    }

    public function __construct(
        string                 $url,
        int                    $timeout,
        public readonly string $file,
    ) {
        parent::__construct($url, $timeout);

        $this->initializeJsonFileTabLoader($this->file);
    }

    /**
     * @throws TabReopenFailedException
     */
    public function load(): array
    {
        $this->loadTabs();

        $console = $this->getConsole();
        $console->writeln(' ==> ' . __METHOD__ . ':', OutputInterface::VERBOSITY_DEBUG);

        $tabs      = [];
        $tabsCount = \count($this->tabs);
        $errors    = 0;

        $console->comment("Uploading tabs {$tabsCount} to device...");

        // The display will be messed up if using the progress bar while writing `-vv`/`-vvv` output.
        $useProgressBar = ! $console->isVeryVerbose() && ! $console->isDebug();

        if ($useProgressBar) {
            $console->progressStart($tabsCount);
        }

        $tabIndex = 0;
        foreach ($this->tabs as $tab) {
            ++$tabIndex;

            [$url, $title] = ReopenScriptFile::parseTab($tab);

            if ($title !== $tab['url']) {
                $console->writeln("> Uploading tab #{$tabIndex} `{$title}` (`{$tab['url']}`)...", OutputInterface::VERBOSITY_VERY_VERBOSE);
            } else {
                $console->writeln("> Uploading tab #{$tabIndex} without title (`{$tab['url']}`)...", OutputInterface::VERBOSITY_VERY_VERBOSE);
            }

            $url    = \sprintf($this->url, $url);
            $result = $this->exec($capturedErrorCode, $capturedErrorMessage, $url, $this->timeout, true);

            if (null !== $result) {
                // Print a warning message to the console.
                $console->writeln('< <fg=black;bg=yellow>Unexpected result for tab upload to device!</>', OutputInterface::VERBOSITY_VERY_VERBOSE);

                if ($this->debug) {
                    $console->writeln(\array_map(static fn(string $line): string => "< {$line}", \explode("\n", $result)));
                }
            } else {
                $console->writeln('< <fg=black;bg=green>Done.</>', OutputInterface::VERBOSITY_VERY_VERBOSE);
            }

            if ($capturedErrorCode) {
                $console->writeln('  Captured curl error message from the request:', OutputInterface::VERBOSITY_VERY_VERBOSE);
                $console->writeln("  > {$capturedErrorMessage}", OutputInterface::VERBOSITY_VERY_VERBOSE);

                if (null === $capturedErrorMessage) {
                    $console->writeln('  No curl error message captured, but an error was found.', OutputInterface::VERBOSITY_VERY_VERBOSE);
                }

                $errors++;
            }

            $tabs[] = [...$tab, 'transferred' => ! $capturedErrorCode];

            if ($useProgressBar) {
                $console->progressAdvance();
            }
        }

        // If not used before, at least display the total of processed tabs as progress bar.
        if ( ! $useProgressBar) {
            $console->progressStart($tabsCount);
        }

        $console->progressFinish();

        $console->writeln(' ==> ' . __METHOD__ . ': Done.', OutputInterface::VERBOSITY_DEBUG);

        if ( ! $errors) {
            return $tabs;
        } elseif ($errors < $tabsCount) {
            throw TabReopenFailedException::forSomeErrors($errors, $tabsCount);
        }

        throw TabReopenFailedException::forErrors();
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function setOutput(OutputInterface $output): static
    {
        $this->jsonFileLoader->setOutput($output);
        return parent::setOutput($output);
    }

    public function setInput(InputInterface $input): static
    {
        $this->jsonFileLoader->setInput($input);
        return parent::setInput($input);
    }

    public function setConsole(Console $console): static
    {
        $this->jsonFileLoader->setConsole($console);
        return parent::setConsole($console);
    }

    public function setDebug(bool $debug): static
    {
        $this->jsonFileLoader->setDebug($debug);
        return parent::setDebug($debug);
    }

    public function setFileDate(?\DateTimeInterface $date, ?string $dateFormat): static
    {
        $this->jsonFileLoader->setFileDate($date, $dateFormat);
        return $this->parent__setFileDate($date, $dateFormat);
    }
}
