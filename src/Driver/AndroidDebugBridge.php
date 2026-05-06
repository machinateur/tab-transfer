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

use Machinateur\ChromeTabTransfer\File\ReopenScriptFile;
use Machinateur\ChromeTabTransfer\Shared\Console;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Command process for `adb`.
 *
 * ```
 * Android Debug Bridge version 1.0.41
 * Version 35.0.2-12147458
 *
 * global options:
 *  -a                       listen on all network interfaces, not just localhost
 *  -d                       use USB device (error if multiple devices connected)
 *  -e                       use TCP/IP device (error if multiple TCP/IP devices available)
 *  -s SERIAL                use device with given serial (overrides $ANDROID_SERIAL)
 *  -t ID                    use device with given transport id
 *  -H                       name of adb server host [default=localhost]
 *  -P                       port of adb server [default=5037]
 *  -L SOCKET                listen on given socket for adb server [default=tcp:localhost:5037]
 *  --one-device SERIAL|USB  only allowed with 'start-server' or 'server nodaemon', server will only connect to one USB device, specified by a serial number or USB device address.
 *  --exit-on-write-error    exit if stdout is closed
 *
 * general commands:
 *  devices [-l]             list connected devices (-l for long output)
 *  help                     show this help message
 *  version                  show version num
 *
 * networking:
 *  forward --list           list all forward socket connections
 *  forward [--no-rebind] LOCAL REMOTE
 *      forward socket connection using:
 *        tcp:<port> (<local> may be "tcp:0" to pick any open port)
 *        localabstract:<unix domain socket name>
 *        localreserved:<unix domain socket name>
 *        localfilesystem:<unix domain socket name>
 *        dev:<character device name>
 *        dev-raw:<character device name> (open device in raw mode)
 *        jdwp:<process pid> (remote only)
 *        vsock:<CID>:<port> (remote only)
 *        acceptfd:<fd> (listen only)
 *  forward --remove LOCAL   remove specific forward socket connection
 *  forward --remove-all     remove all forward socket connections
 *  reverse --list           list all reverse socket connections from device
 *  reverse [--no-rebind] REMOTE LOCAL
 *      reverse socket connection using:
 *        tcp:<port> (<remote> may be "tcp:0" to pick any open port)
 *        localabstract:<unix domain socket name>
 *        localreserved:<unix domain socket name>
 *        localfilesystem:<unix domain socket name>
 *
 * environment variables:
 *  $ADB_TRACE
 *      comma/space separated list of debug info to log:
 *      all,adb,sockets,packets,rwx,usb,sync,sysdeps,transport,jdwp,services,auth,fdevent,shell,incremental
 *  $ADB_VENDOR_KEYS         colon-separated list of keys (files or directories)
 *  $ANDROID_SERIAL          serial number to connect to (see -s)
 *  $ANDROID_LOG_TAGS        tags to be used by logcat (see logcat --help)
 *  $ADB_LOCAL_TRANSPORT_MAX_PORT max emulator scan port (default 5585, 16 emus)
 *  $ADB_MDNS_AUTO_CONNECT   comma-separated list of mdns services to allow auto-connect (default adb-tls-connect)
 *
 * Online documentation: https://android.googlesource.com/platform/packages/modules/adb/+/refs/heads/main/docs/user/adb.1.md
 * ```
 */
final class AndroidDebugBridge extends AbstractDriver
{
    /**
     * The default port on the `tcp` value.
     *
     * @var int
     */
    public const DEFAULT_PORT = 9222;

    /**
     * The default timeout for requests to the endpoint.
     */
    public const DEFAULT_TIMEOUT = 10;

    /**
     * The default socket name on the `localabstract` value.
     *
     * @var string
     */
    public const DEFAULT_SOCKET = 'chrome_devtools_remote';

    /**
     * The default amount of seconds to wait before returning from process start/stop calls.
     *
     * @var int
     */
    public const DEFAULT_DELAY = 2;

    public function __construct(
        string                 $file,
        int                    $port        = self::DEFAULT_PORT,
        bool                   $debug       = false,
        int                    $timeout     = self::DEFAULT_TIMEOUT,
        public readonly string $socket      = self::DEFAULT_SOCKET,
        public readonly int    $delay       = self::DEFAULT_DELAY,
        public readonly bool   $skipCleanup = false,
    ) {
        parent::__construct($file, $port, $debug, $timeout);
    }

    protected function run(Process $process, Console $console): void
    {
        $console->progressStart(1);
        $process->start();
        $process->wait();
        $console->progressFinish();

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

        $console->writeln("Waiting for {$this->delay} seconds...", OutputInterface::VERBOSITY_VERY_VERBOSE);
        \sleep($this->delay);
    }

    public function start(): void
    {
        $console = $this->getConsole();
        $console->writeln(' ==> ' . __METHOD__ . ':', OutputInterface::VERBOSITY_DEBUG);

        $console->comment('Running `adb` command...');
        $console->writeln("> adb -d forward tcp:{$this->port} localabstract:{$this->socket}", OutputInterface::VERBOSITY_DEBUG);
        $this->run(
            new Process(['adb', '-d', 'forward', "tcp:{$this->port}", "localabstract:{$this->socket}"]), console: $console
        );

        $console->writeln(' ==> ' . __METHOD__ . ': Done.', OutputInterface::VERBOSITY_DEBUG);
    }

    public function stop(): void
    {
        $console = $this->getConsole();
        $console->writeln(' ==> ' . __METHOD__ . ':', OutputInterface::VERBOSITY_DEBUG);

        if ($this->skipCleanup) {
            $console->writeln([
                '<fg=black;bg=yellow>Skipping adb cleanup command... To dispose manually, run:</>',
                "<fg=black;bg=yellow;options=bold,underscore>> `adb -d forward --remove tcp:{$this->port}`</>",
            ]);
        } else {
            $console->comment('Running `adb` cleanup command...');
            $console->writeln("> adb -d forward --remove tcp:{$this->port}", OutputInterface::VERBOSITY_DEBUG);
            $this->run(
                new Process(['adb', '-d', 'forward', '--remove', "tcp:{$this->port}"]), console: $console
            );
        }

        $console->writeln(' ==> ' . __METHOD__ . ': Done.', OutputInterface::VERBOSITY_DEBUG);
    }

    public function getUrl(): string
    {
        return "http://localhost:{$this->port}/json/list";
    }

    public function getFileTemplates(array $tabs): array
    {
        $fileTemplates = parent::getFileTemplates($tabs);

        // Only write the reopen script file, if the cleanup is done properly.
        //  Otherwise, we are at risk of opening those tabs again on the same device.
        if ( ! $this->skipCleanup) {
            $this->output->writeln('Allow reopen script with cleanup enabled.', OutputInterface::VERBOSITY_VERY_VERBOSE);
            $fileTemplates[] = new ReopenScriptFile($this->file, $tabs, $this->port, $this->socket);
        } else {
            $this->output->writeln('Skipping reopen script with cleanup disabled.', OutputInterface::VERBOSITY_VERY_VERBOSE);
        }

        return $fileTemplates;
    }

    protected function getShellCommand(): string
    {
        return 'adb';
    }
}
