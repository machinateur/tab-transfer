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

namespace Machinateur\ChromeTabTransfer\Command;

use Machinateur\ChromeTabTransfer\Driver\AbstractDriver;
use Machinateur\ChromeTabTransfer\Driver\IosWebkitDebugProxy;
use Machinateur\ChromeTabTransfer\Platform;
use Machinateur\ChromeTabTransfer\Shared\Console;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A command implementation to copy tabs using the {@see IosWebkitDebugProxy} driver.
 *
 * This command does not support the reopen script (for now, until I find a way to open tabs on iOS).
 *  Maybe it's possible to use https://github.com/sirn-se/websocket-php to directly control the websocket.
 * The main issue here is that a bash/cmd script will not be possible then. Therefor the {@see ReopenTabs} command
 *  should be used. The `reopen.cmd`/`reopen.sh` is only supported for legacy reasons with android users.
 *
 * @see https://github.com/google/ios-webkit-debug-proxy
 */
class CopyTabsFromIphone extends AbstractCopyTabsCommand
{
    public const DEFAULT_WAIT = IosWebkitDebugProxy::DEFAULT_DELAY;

    public function __construct()
    {
        parent::__construct('iphone');
    }

    protected function configure(): void
    {
        parent::configure();

        $definition = $this->getDefinition();
        // Here we use some black magic to extract a reference to the `$description` property of the InputOption.
        $argumentPortDescription = & Platform::extractPropertyReference($definition->getOption('port'), 'description');
        // Next we simply write to that reference - et-voilà.
        $argumentPortDescription = 'The port to forward requests through `ios_webkit_debug_proxy`.';

        $this
            ->setDescription('Transfer tabs from your iPhone\'s Chrome browser.')
            ->addOption('wait', 'w', InputOption::VALUE_REQUIRED, 'The time to wait before starting the download request (between 0 and 60 seconds).', self::DEFAULT_WAIT)
        ;
    }

    private ?IosWebkitDebugProxy $driver = null;

    public function getDriver(Console $console): AbstractDriver
    {
        if ($this->driver) {
            $console->writeln("Loading {$this->driverName} driver...", OutputInterface::VERBOSITY_VERY_VERBOSE);

            $this->driver = null;
        } else {
            $console->writeln("Creating {$this->driverName} driver...", OutputInterface::VERBOSITY_VERY_VERBOSE);
        }

        return $this->driver ??= new IosWebkitDebugProxy(
            $this->getArgumentFile($console),
            $this->getArgumentPort($console),
            $this->getArgumentDebug($console),
            $this->getArgumentTimeout($console),
            $this->getArgumentWait($console),
        );
    }

    protected function getArgumentWait(Console $console): int
    {
        $argumentWait = (int)$console->input->getOption('wait');

        if (0 >= $argumentWait || 60 < $argumentWait) {
            $argumentWait = self::DEFAULT_WAIT;

            $console->warning("Invalid wait given, default to {$argumentWait}s.");
        }

        return $argumentWait;
    }
}
