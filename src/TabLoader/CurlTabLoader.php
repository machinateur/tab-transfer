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

use Machinateur\ChromeTabTransfer\Exception\CurlException;
use Machinateur\ChromeTabTransfer\Exception\TabLoadingFailedException;
use Machinateur\ChromeTabTransfer\Shared\Console;
use Machinateur\ChromeTabTransfer\Shared\ConsoleTrait;
use Machinateur\ChromeTabTransfer\Shared\DebugFlagTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method $this setConsole(Console $console)
 * @method $this setInput(InputInterface $input)
 * @method $this setOutput(OutputInterface $output)
 * @method $this setDebug(bool $debug)
 */
class CurlTabLoader implements TabLoaderInterface
{
    use ConsoleTrait {
        ConsoleTrait::__construct as private initializeConsole;
    }
    use DebugFlagTrait;

    public function __construct(
        public readonly string $url,
        public readonly int    $timeout,
    ) {
        $this->initializeConsole();
    }

    final protected function exec(
        ?int    &$capturedErrorCode    = 0,
        ?string &$capturedErrorMessage = null,
        ?string $url     = null,
        ?int    $timeout = null,
        bool    $isPutRequest     = false,
    ): ?string {
        $url     ??= $this->url;
        $timeout ??= $this->timeout;

        $console = $this->getConsole();
        $console->writeln("> curl: {$url} ({$timeout})", OutputInterface::VERBOSITY_VERY_VERBOSE);

        $ch = \curl_init();
        \curl_setopt($ch, \CURLOPT_URL,            $url);
        \curl_setopt($ch, \CURLOPT_TIMEOUT,        $timeout);
        //\curl_setopt($ch, \CURLOPT_CONNECTTIMEOUT, 10);

        if ($this->debug) {
            $console->newLine();

            // This will enable output directly to the STDERR stream of PHP.
            \curl_setopt($ch, \CURLOPT_VERBOSE,    true);
        }

        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        if ($isPutRequest) {
            \curl_setopt($ch, \CURLOPT_CUSTOMREQUEST, 'PUT');
        }

        /** @var string|null $result */
        $result = \curl_exec($ch) ?: null;

        $capturedErrorCode    = \curl_errno($ch);
        $capturedErrorMessage = null;
        if (0 < $capturedErrorCode) {
            $capturedErrorMessage = \sprintf('%s (code %d)', \curl_error($ch), $capturedErrorCode);
        }
        \curl_close($ch);

        unset($url, $ch);

        if ($this->debug) {
            // New line after STDERR output from curl execution.
            $console->newLine();
        }

        return $result;
    }

    /**
     * @throws TabLoadingFailedException
     */
    public function load(): array
    {
        $console = $this->getConsole();
        $console->comment('Downloading tabs from device...');

        $result = $this->exec($capturedErrorCode, $capturedErrorMessage);

        if (null === $result) {
            $console->warning([
                'Unable to download tabs from device! Please check the device connection!',
                'Also make sure google chrome is running on the connected device and USB debugging is active in the developer settings.',
            ]);

            if (null === $capturedErrorMessage) {
                $console->writeln('No curl error message captured, but result was `null`.', OutputInterface::VERBOSITY_VERY_VERBOSE);

                throw CurlException::withoutErrorMessage($capturedErrorCode);
            }

            $console->writeln('Captured curl error message from the request:', OutputInterface::VERBOSITY_VERY_VERBOSE);
            $console->writeln("> {$capturedErrorMessage}", OutputInterface::VERBOSITY_VERY_VERBOSE);

            throw new CurlException($capturedErrorMessage);
        }

        if ($this->debug) {
            $console->writeln(\array_map(static fn(string $line): string => "< {$line}", \explode("\n", $result)));
        }

        $console->success('Successfully downloaded tabs from device!');

        try {
            $tabs = @\json_decode($result, true, flags: \JSON_THROW_ON_ERROR) ?? [];
        } catch (\JsonException $exception) {
            $console->writeln('Unable to decode JSON data!', OutputInterface::VERBOSITY_VERY_VERBOSE);

            throw TabLoadingFailedException::fromJsonException($exception);
        }

        $console->writeln('Successfully decoded json data.', OutputInterface::VERBOSITY_VERY_VERBOSE);

        return $tabs;
    }
}
