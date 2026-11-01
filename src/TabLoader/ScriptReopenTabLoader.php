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

use Machinateur\ChromeTabTransfer\Shared\ConsoleTrait;
use Machinateur\ChromeTabTransfer\Shared\DebugFlagTrait;
use Machinateur\ChromeTabTransfer\Shared\FileDateTrait;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\PhpProcess;

class ScriptReopenTabLoader implements TabLoaderInterface
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

    public function __construct(
        public readonly int    $port,
        public readonly int    $timeout,
        public readonly string $file,
        public readonly string $callScript,
    ) {
        $this->initializeConsole();

        $this->initializeJsonFileTabLoader($this->file);
    }

    public function load(): array
    {
        $this->loadTabs();

        $serializedTabs = \var_export($this->tabs, true);

        // Call the script with isolated scope.
        $process = new PhpProcess("<?php \$_TABS = {$serializedTabs}; require_once '{$this->callScript}';");
        $console = $this->getConsole();

        // Run the process, also use timeout as given by the command.
        $process->setTimeout($this->timeout);
        try {
            $process->mustRun();

            $console->write($process->getOutput());
        } catch (ProcessTimedOutException $exception) {
            $console->writeln(['<fg=white;bg=red>Process timeout error</>', $exception->getMessage()]);
        } catch (ProcessFailedException $exception) {
            $console->writeln(['<fg=white;bg=red>Process status error</>', $exception->getMessage()]);
        }

        return $this->tabs;
    }
}
