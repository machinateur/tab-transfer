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

use Machinateur\ChromeTabTransfer\Exception\JsonFileLoadingException;
use Machinateur\ChromeTabTransfer\File\JsonFile;
use Machinateur\ChromeTabTransfer\Shared\Console;
use Machinateur\ChromeTabTransfer\Shared\ConsoleTrait;
use Machinateur\ChromeTabTransfer\Shared\DebugFlagTrait;
use Machinateur\ChromeTabTransfer\Shared\FileDateTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A loader that can read the files written through the {@see \Machinateur\ChromeTabTransfer\File\JsonFile} template.
 *
 * @method $this setConsole(Console $console)
 * @method $this setInput(InputInterface $input)
 * @method $this setOutput(OutputInterface $output)
 * @method $this setDebug(bool $debug)
 * @method $this setFileDate(?\DateTimeInterface $date, ?string $dateFormat)
 */
class JsonFileTabLoader implements TabLoaderInterface
{
    use ConsoleTrait {
        ConsoleTrait::__construct as private initializeConsole;
    }
    use DebugFlagTrait;
    use FileDateTrait;

    private readonly Filesystem $filesystem;

    public function __construct(
        public readonly string $file,
    ) {
        $this->initializeConsole();

        $this->filesystem = new Filesystem();
    }

    /**
     * @throws JsonFileLoadingException
     */
    public function load(): array
    {
        $console = $this->getConsole();
        $console->writeln(' ==> ' . __METHOD__ . ':', OutputInterface::VERBOSITY_DEBUG);

        // We can only know the actual filename here, as the `--date` flag is injected only later.
        $file = (new JsonFile($this->file, []))
            ->setFileDate($this->getFileDate(), $this->getDateFormat())
            ->getFilename();

        $console->writeln("Looking for file `{$file}` to load stored tabs... ", OutputInterface::VERBOSITY_VERY_VERBOSE);

        if ( ! $this->filesystem->exists($file)) {
            $console->writeln("File `{$file}` not found!", OutputInterface::VERBOSITY_VERY_VERBOSE);

            throw JsonFileLoadingException::whenFileNotFound($file);
        }

        if ('json' !== \pathinfo($file, \PATHINFO_EXTENSION)) {
            $console->writeln('The file extension does not match `.json`!', OutputInterface::VERBOSITY_VERY_VERBOSE);

            throw JsonFileLoadingException::whenFileNotAJsonFile($file);
        }

        $result = @\file_get_contents($file);

        if ( ! $result) {
            $console->writeln("Error reading file `{$file}`: Falsy result.", OutputInterface::VERBOSITY_VERY_VERBOSE);

            throw JsonFileLoadingException::whenFileNotReadable($file);
        }

        if ($this->debug) {
            $console->writeln(\array_map(static fn(string $line): string => "< {$line}", \explode("\n", $result)));
        }

        try {
            $tabs = @\json_decode($result, true, flags: \JSON_THROW_ON_ERROR) ?? [];
        } catch (\JsonException $exception) {
            $console->writeln('Unable to decode JSON data!', OutputInterface::VERBOSITY_VERY_VERBOSE);

            throw JsonFileLoadingException::fromJsonException($exception);
        }

        $console->writeln('Successfully decoded json data.', OutputInterface::VERBOSITY_VERY_VERBOSE);

        if ( ! \is_array($tabs)) {
            $console->writeln('Warning: The JSON data type is not an array or object.', OutputInterface::VERBOSITY_VERY_VERBOSE);

            throw JsonFileLoadingException::forMalformedContent();
        }

        foreach ($tabs as $tab) {
            if (\array_key_exists('url', $tab)) {
                continue;
            }

            $console->writeln('Warning: Tab without URL found in data. Make sure each tab entry has at least the `url` key assigned.', OutputInterface::VERBOSITY_VERY_VERBOSE);

            throw JsonFileLoadingException::forMalformedContent();
        }

        return $tabs;
    }
}
