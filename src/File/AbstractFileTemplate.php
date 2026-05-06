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

namespace Machinateur\ChromeTabTransfer\File;

use Machinateur\ChromeTabTransfer\Shared\Console;
use Machinateur\ChromeTabTransfer\Shared\ConsoleTrait;
use Machinateur\ChromeTabTransfer\Shared\FileDateTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

/**
 * @method AbstractFileTemplate setConsole(Console $console)
 * @method AbstractFileTemplate setInput(InputInterface $input)
 * @method AbstractFileTemplate setOutput(OutputInterface $output)
 * @method AbstractFileTemplate setFileDate(?\DateTimeInterface $date, ?string $dateFormat)
 */
abstract class AbstractFileTemplate implements FileTemplateInterface
{
    use ConsoleTrait {
        ConsoleTrait::__construct as private initializeConsole;
    }
    use FileDateTrait;

    public const DATE_FORMAT = 'Y-m-d';

    protected string $filenameSuffix = '';

    public function __construct(
        protected readonly string $file,
        protected readonly array  $tabs,
    ) {
        $this->initializeConsole();
    }

    public function getFilename(): string
    {
        $file = \pathinfo($this->file, \PATHINFO_FILENAME);
        $dir  = \pathinfo($this->file, \PATHINFO_DIRNAME);

        if ($this->date) {
            $file = "{$file}_{$this->date->format($this->dateFormat ?: self::DATE_FORMAT)}";
        }
        $file = "{$file}{$this->filenameSuffix}.{$this->getExtension()}";

        return Path::join($dir, $file);
    }

    abstract public function getExtension(): string;

    abstract public function render(): string;

    /**
     * @inheritDoc
     */
    final public function __toString(): string
    {
        try {
            return $this->render();
        } catch (\Throwable) {
            // Avoid throwing exceptions in `__toString()` invocation at any cost.
            return '';
        }
    }
}
