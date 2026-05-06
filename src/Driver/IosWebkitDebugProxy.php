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

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Command process for `ios_webkit_debug_proxy`.
 *
 * ```
 * Usage: ios_webkit_debug_proxy [OPTIONS]
 * iOS WebKit Remote Debugging Protocol Proxy v1.9.1.
 *
 * By default, the proxy will list all attached iOS devices on:
 *   http://localhost:9221
 * and assign each device an incremented port number, e.g.:
 *   http://localhost:9222
 * which lists the device's pages and provides inspector access.
 *
 * Your attached iOS device(s) must have the inspector enabled via:
 *   Settings > Safari > Advanced > Web Inspector = ON
 * and have one or more open browser pages.
 *
 * To view the DevTools UI, either use the above links (which use the "frontend"
 * URL noted below) or use Chrome's built-in inspector, e.g.:
 *   chrome-devtools://devtools/bundled/inspector.html?ws=localhost:9222/devtools/page/1
 *
 * OPTIONS:
 *
 *   -u UDID[:minPort-[maxPort]]   Target a specific device by its digital ID.
 * minPort defaults to 9222.  maxPort defaults to minPort.
 * This is shorthand for the following "-c" option.
 *
 *   -c, --config CSV      UDID-to-port(s) configuration.
 * Defaults to:
 *           null:9221,:9222-9322
 *         which lists devices ("null:") on port 9221 and assigns
 *         all other devices (":") to the next unused port in the
 *         9222-9322 range, in the (somewhat random) order that the
 *         devices are detected.
 * The value can be the path to a file in the above format.
 *
 *   -f, --frontend URL    DevTools frontend UI path or URL.
 * Defaults to:
 *           http://chrome-devtools-frontend.appspot.com/static/27.0.1453.93/devtools.html
 *         Examples:
 *           * Use Chrome's built-in inspector:
 *               chrome-devtools://devtools/bundled/inspector.html
 *           * Use a local WebKit checkout:
 *               /usr/local/WebCore/inspector/front-end/inspector.html
 *           * Use an online copy of the inspector pages:
 *               http://chrome-devtools-frontend.appspot.com/static/33.0.1722.0/devtools.html
 *             where other online versions include:
 *               18.0.1025.74
 *               25.0.1364.169
 *               28.0.1501.0
 *               30.0.1599.92
 *               31.0.1651.0
 *               32.0.1689.3
 *
 *   -F, --no-frontend     Disable the DevTools frontend.
 *
 *   -s, --simulator-webinspector  Simulator web inspector socket
 *         address. Provided value needs to be in format
 *         HOSTNAME:PORT or UNIX:PATH
 *         Defaults to:
 *           localhost:27753
 *         Examples:
 *           * TCP socket:
 *             192.168.0.20:27753
 *           * Unix domain socket:
 *             unix:/private/tmp/com.apple.launchd.2j5k1TMh6i/com.apple.webinspectord_sim.socket
 *
 *   -d, --debug           Enable debug output.
 *   -h, --help            Print this usage information.
 *   -V, --version         Print version information and exit.
 * ```
 */
final class IosWebkitDebugProxy extends AbstractDriver
{
    public const DEFAULT_PORT = AndroidDebugBridge::DEFAULT_PORT;

    public const DEFAULT_TIMEOUT = AndroidDebugBridge::DEFAULT_TIMEOUT;

    public const DEFAULT_DELAY = AndroidDebugBridge::DEFAULT_DELAY;

    private readonly Process $process;

    /**
     * Create a background process to open the debug proxy for iOS.
     *
     * In contrast to the {@see AndroidDebugBridge}, this process is blocking and will remain running while the application is running.
     */
    public function __construct(
        string              $file,
        int                 $port    = self::DEFAULT_PORT,
        bool                $debug   = false,
        int                 $timeout = self::DEFAULT_TIMEOUT,
        public readonly int $delay   = self::DEFAULT_DELAY,
    ) {
        parent::__construct($file, $port, $debug, $timeout);

        $command = ['ios_webkit_debug_proxy', '-F', '-c', 'null:9221,:9222-9322'];

        if ($debug) {
            $command[] = '--debug';
        }

        $this->process = new Process($command);
    }

    public function start(): void
    {
        $console = $this->getConsole();
        $console->writeln(' ==> ' . __METHOD__ . ':', OutputInterface::VERBOSITY_DEBUG);

        $console->comment('Starting `ios_webkit_debug_proxy` background process...');

        $console->progressStart(1);
        $this->process->start();
        $console->progressFinish();

        if (0 < $this->delay) {
            $console->writeln("Waiting for {$this->delay} seconds...", OutputInterface::VERBOSITY_VERY_VERBOSE);
            \sleep($this->delay);
        }

        $console->writeln(' ==> ' . __METHOD__ . ': Done.', OutputInterface::VERBOSITY_DEBUG);
    }

    public function stop(): void
    {
        $console = $this->getConsole();
        $console->writeln(' ==> ' . __METHOD__ . ':', OutputInterface::VERBOSITY_DEBUG);

        $console->comment('Stopping `ios_webkit_debug_proxy` background process...');

        $console->progressStart(1);
        $this->process->stop();
        $this->process->wait();
        $console->progressFinish();

        if ($this->debug) {
            $combinedProcessOutput = \array_filter(
                \array_map(
                    static fn(string $line): string => "< {$line}",
                    \array_merge(
                        \explode(\PHP_EOL, $this->process->getOutput()),
                        \explode(\PHP_EOL, $this->process->getErrorOutput()),
                    )
                )
            );

            $console->writeln($combinedProcessOutput);
            $console->newLine();
        }

        if (0 < $this->delay) {
            $console->writeln("Waiting for {$this->delay} seconds...", OutputInterface::VERBOSITY_VERY_VERBOSE);
            \sleep($this->delay);
        }

        $console->writeln(' ==> ' . __METHOD__ . ': Done.', OutputInterface::VERBOSITY_DEBUG);
    }

    public function getUrl(): string
    {
        return "http://localhost:{$this->port}/json";
    }

    protected function getShellCommand(): string
    {
        return 'ios_webkit_debug_proxy';
    }
}
