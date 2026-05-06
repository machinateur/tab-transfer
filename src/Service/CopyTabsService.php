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

namespace Machinateur\ChromeTabTransfer\Service;

use Machinateur\ChromeTabTransfer\Driver\AbstractDriver;
use Machinateur\ChromeTabTransfer\Exception\CopyTabsException;
use Machinateur\ChromeTabTransfer\Exception\FileTemplateDumpException;
use Machinateur\ChromeTabTransfer\Exception\TabLoadingFailedException;
use Machinateur\ChromeTabTransfer\Exception\TabReopenFailedException;
use Machinateur\ChromeTabTransfer\File\AbstractFileTemplate;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

class CopyTabsService
{
    private readonly Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * @throws CopyTabsException
     */
    public function run(AbstractDriver $driver): array
    {
        $driver->start();

        try {
            $tabLoader = $driver->getTabLoader();

            // Q&D: Just check if the instance looks like it implements the `FileDateTrait`.
            if (\method_exists($tabLoader, 'setFileDate')) {
                $tabLoader->setFileDate($driver->getFileDate(), $driver->getDateFormat());
            }

            $tabs = $tabLoader->load();
        } catch (TabLoadingFailedException $exception) {
            // The exception has to be thrown only after the driver was stopped.
        }

        $driver->stop();

        if (isset($exception)) {
            if ($exception instanceof TabReopenFailedException) {
                throw CopyTabsException::fromTabReopenFailedException($exception);
            }

            throw CopyTabsException::fromTabLoadingFailedException($exception);
        }

        if (empty($tabs)) {
            throw CopyTabsException::forEmptyResult($driver->getUrl());
        }

        try {
            $this->dumpFileTemplates($driver, $this->getFileTemplates($driver, $tabs));
        } catch (FileTemplateDumpException $exception) {
            throw CopyTabsException::fromFileTemplateDumpException($exception);
        }

        return $tabs;
    }

    /**
     * @param array<string, string> $files
     * @throws FileTemplateDumpException
     */
    private function dumpFileTemplates(AbstractDriver $driver, array $files): void
    {
        if (empty($files)) {
            return;
        }

        $console = $driver->getConsole();
        $console->note('Generating output files...');

        foreach ($files as $filename => $content) {
            $filenameExtension = \pathinfo($filename, \PATHINFO_EXTENSION);

            $console->note("Writing {$filenameExtension} file...");
            $console->writeln("> {$filename}", OutputInterface::VERBOSITY_VERY_VERBOSE);

            try {
                $this->filesystem->dumpFile($filename, $content);
            } catch (IOException $exception) {
                $console->writeln('Error writing file: ' . $exception->getMessage(), OutputInterface::VERBOSITY_VERY_VERBOSE);

                if ($driver->debug) {
                    // Don't throw inside the loop, and never after download process was finished.
                    //throw FileTemplateDumpException::fromIOException($exception);
                }

                $console->error("Failed to write {$filename} file!");

                continue;
            }

            $filenameExtension = \ucfirst($filenameExtension);

            $console->success("{$filenameExtension} file written successfully!");
            $console->writeln("< {$filename}", OutputInterface::VERBOSITY_VERY_VERBOSE);
        }
    }

    /**
     * @return array<string, string>
     * @throws FileTemplateDumpException
     */
    private function getFileTemplates(AbstractDriver $driver, array $tabs): array
    {
        $console = $driver->getConsole();
        $console->note('Generating output files...');

        $files = [];
        foreach ($driver->getFileTemplates($tabs) as $fileTemplate) {
            // Make sure any output generated by the file will be actually written to the real output.
            if ($fileTemplate instanceof AbstractFileTemplate) {
                $fileTemplate->setOutput($driver->getOutput());
                $fileTemplate->setFileDate($driver->getFileDate(), $driver->getDateFormat());
            }

            $filename = $fileTemplate->getFilename();

            $fileTemplateClass = $fileTemplate::class;
            $console->writeln("-> From {$fileTemplateClass} to file {$filename}.", OutputInterface::VERBOSITY_VERY_VERBOSE);

            if ($this->filesystem->exists($filename)) {
                $replace = $console->confirm("The file `{$filename}` already exists. Do you wish to overwrite it?");

                if ( ! $replace) {
                    if ($driver->debug) {
                        // Don't throw inside the loop, and never after download process was finished.
                        //throw FileTemplateDumpException::forExistingFile($filename);
                    }

                    $console->writeln("Skipping `{$filename}`, as it already exists.", OutputInterface::VERBOSITY_VERY_VERBOSE);

                    continue;
                }
            }

            $content = (string) $fileTemplate;

            if (empty($content)) {
                if ($driver->debug) {
                    // Don't throw inside the loop, and never after download process was finished.
                    //throw FileTemplateDumpException::forEmptyFileContent($filename);
                }

                $console->writeln("Skipping `{$filename}`, as it has no content.", OutputInterface::VERBOSITY_VERY_VERBOSE);

                continue;
            }

            $files[$filename] = $content;

            $console->writeln("Added `{$filename}` for output.", OutputInterface::VERBOSITY_VERY_VERBOSE);
        }

        return $files;
    }
}
