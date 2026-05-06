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

use Machinateur\ChromeTabTransfer\Exception\FileTemplateDumpException;
use Machinateur\ChromeTabTransfer\File\WebsocketWdpClient;
use Machinateur\ChromeTabTransfer\Platform;
use Machinateur\ChromeTabTransfer\Shared\Console;
use Machinateur\ChromeTabTransfer\Shared\ConsoleTrait;
use Machinateur\ChromeTabTransfer\Shared\DebugFlagTrait;
use Machinateur\ChromeTabTransfer\Shared\FileDateTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A tab loader that will dump and open a new {@see \Machinateur\ChromeTabTransfer\File\WebsocketWdpClient} script in the browser.
 *
 * This implementation is to be used with iphone to restore tabs on the device. This is required to be done via websocket,
 *  as the usual `/json/new`...` endpoint (used for android) is not supported by `ios_webkit_debug_proxy`.
 * See {@see }
 */
class WdpReopenTabLoader implements TabLoaderInterface
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

    private readonly Filesystem $filesystem;

    public function __construct(
        public readonly int    $port,
        public readonly int    $timeout,
        public readonly string $file,
        public readonly string $targetPage,
    ) {
        $this->initializeConsole();

        $this->initializeJsonFileTabLoader($this->file);

        $this->filesystem = new Filesystem();
    }

    public function load(): array
    {
        $tabs      = $this->loadTabs();
        $tabsCount = \count($this->tabs);

        /** @var string $filename */
        $this->dumpWdpClient($tabs, $filename);

        $console = $this->getConsole();
        $console->writeln(' ==> ' . __METHOD__ . ':', OutputInterface::VERBOSITY_DEBUG);

        $console->comment("Uploading tabs {$tabsCount} to device...");

        // Blocking process call to open the system's web-browser.
        $result   = Platform::openInBrowser($wdpClientUrl = "http://localhost:{$this->port}/devtools/{$filename}");

        if ( ! $result) {
            $console->writeln('Browser process failed with non-zero exit-code!', OutputInterface::VERBOSITY_VERY_VERBOSE);
        }

        $console->info([
            "Please open `{$wdpClientUrl}` in your browser. The transfer should start immediately.",
            'Simply close the page when the tabs were transferred.',
        ]);

        // This needs `setConsole()` instead of `setOutput()` (currently used everywhere) to work.
        if ($console->input->isInteractive()) {
            $console->confirm('Please press <info>[enter]</> to continue...');
        } else {
            $console->writeln("Waiting for {$this->timeout} seconds (timeout on non-interactive console)...", OutputInterface::VERBOSITY_VERY_VERBOSE);

            \sleep($this->timeout);
        }

        // When not in debug mode, the WDP script will be removed again.
        if ( ! $this->debug) {
            $this->filesystem->remove($filename);
        }

        $console->progressStart($tabsCount);
        $tabs = \array_map(static fn(array $tab): array => [...$tab, 'transferred' => true], $tabs);
        $console->progressFinish();

        $console->writeln(' ==> ' . __METHOD__ . ': Done.', OutputInterface::VERBOSITY_DEBUG);

        return $tabs;
    }

    /**
     * @throws FileTemplateDumpException
     */
    final protected function dumpWdpClient(array $tabs, ?string & $filename): void
    {
        $console = $this->getConsole();

        $fileTemplate = (new WebsocketWdpClient($tabs, $this->port, $this->targetPage))
            ->setOutput($this->getOutput())
            ->setFileDate($this->getFileDate(), $this->getDateFormat());
        $filename     = $fileTemplate->getFilename();
        $content      = (string)$fileTemplate;

        try {
            $this->filesystem->dumpFile($filename, $content);
        } catch (IOException $exception) {
            $console->writeln('Error writing file: ' . $exception->getMessage(), OutputInterface::VERBOSITY_VERY_VERBOSE);

            throw FileTemplateDumpException::fromIOException($exception);
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
