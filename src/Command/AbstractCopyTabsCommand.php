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
use Machinateur\ChromeTabTransfer\Driver\AndroidDebugBridge;
use Machinateur\ChromeTabTransfer\Driver\DriverEnvironmentCheckInterface;
use Machinateur\ChromeTabTransfer\Exception\CopyTabsException;
use Machinateur\ChromeTabTransfer\Exception\EnvironmentCheckException;
use Machinateur\ChromeTabTransfer\File\AbstractFileTemplate;
use Machinateur\ChromeTabTransfer\Service\CopyTabsService;
use Machinateur\ChromeTabTransfer\Shared\AccessibleInput;
use Machinateur\ChromeTabTransfer\Shared\Console;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The abstract base class containing common logic for {@see AbstractDriver} driver based commands to copy tabs.
 */
abstract class AbstractCopyTabsCommand extends Command
{
    public const DEFAULT_FILE    = 'tabs.json';
    public const DEFAULT_PORT    = AndroidDebugBridge::DEFAULT_PORT;
    public const DEFAULT_TIMEOUT = AndroidDebugBridge::DEFAULT_TIMEOUT;

    protected \DateTimeInterface $date;

    protected readonly CopyTabsService $service;

    public function __construct(
        protected readonly string $driverName,
    ) {
        $this->date = new \DateTimeImmutable();

        parent::__construct("copy-tabs:{$driverName}");

        $this->service = new CopyTabsService();
    }

    public function getDriverName(): string
    {
        return $this->driverName;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::OPTIONAL, 'The relative filepath to write. The `--date` / `--no-date` flag applies as well.', self::DEFAULT_FILE)
            ->addOption('date', 'd', InputOption::VALUE_NEGATABLE, "Whether to add the date `--date-format|-f` suffix to the filename. Active by Default.", true)
            ->addOption('date-format', 'f', InputOption::VALUE_REQUIRED, "A valid date format to use for the date suffix to the filename, when not `--no-date`.", AbstractFileTemplate::DATE_FORMAT)
            ->addOption('port', 'p', InputOption::VALUE_REQUIRED, 'The port to forward requests through.', self::DEFAULT_PORT)
            ->addOption('timeout', 't', InputOption::VALUE_REQUIRED, 'The network timeout for the download request (at last 10 seconds).', self::DEFAULT_TIMEOUT)
            ->addOption('skip-check', null, InputOption::VALUE_NONE, 'Skip the check for required dependencies ony your system will be performed.')
        ;
    }

    /**
     * Call the {@see checkCommandEnvironment} command internally.
     *
     * @return int{0,2}
     */
    protected function performEnvironmentCheck(Console $console): int
    {
        $command = $this->getApplication()
            ->get(CheckEnvironment::NAME);

        if ( ! $command instanceof CheckEnvironment) {
            throw new \LogicException(\sprintf('The application must contain the %s command.', $command->getName()));
        }

        // Warning: We can only pass the ínput directly in here, because the `driver` option (or any other) is never looked up inside the `CheckEnvironment` command
        //  when a `$command` (here `$this`) is passed as third argument!
        return $command->execute($console->input, $console, $this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console  = new Console($input, $output);

        // Run the inner logic. This is extracted to a separate method,
        //  to allow for easier changes in resulting output of the specific driver command.
        try {
            $tabs = $this->executeDriver($console);
        } catch (CopyTabsException $exception) {
            $console->error($exception->getMessage());

            return Command::FAILURE;
        }

        $numberOfTabsTransferred = \count($tabs);
        if (!$numberOfTabsTransferred) {
            $console->note('No tabs were copied from the device.');
        }

        $console->success("Successfully copied {$numberOfTabsTransferred} tabs from the device.");
        $console->newLine();

        // With `-vvv` parameter, print the fetched tabs to stdout. Only if there were tabs downloaded, else skip this step.
        if (0 < $numberOfTabsTransferred && $output->isDebug()) {
            $table = $console->createTable()
                ->setHeaders(['Title', 'URL']);

            foreach ($tabs as $tab) {
                $title = $tab['title'] ?: '--';
                $url   = $tab['url'];

                $table->addRow([$title, $url]);
            }
            unset($tab, $title, $url);

            $table->render();
            $console->newLine();
        }

        return Command::SUCCESS;
    }

    /**
     * @throws CopyTabsException
     */
    final protected function executeDriver(Console $console): array
    {
        if ( ! $this->getArgumentSkipCheck($console)) {
            $return = $this->performEnvironmentCheck($console);

            if (Command::SUCCESS !== $return) {
                throw EnvironmentCheckException::whenFailed($return);
            }
        } else {
            $console->note('Skipping environment check.');
        }

        return $this->service->run(
            $this->getDriver($console)
                ->setConsole($console)
                ->setFileDate(
                    $this->getArgumentDate($console),
                    $this->getArgumentDateFormat($console),
                )
        );
    }

    abstract public function getDriver(Console $console): AbstractDriver;

    /**
     * This method should encapsulate the environment requirements for the specific command.
     *
     * Usually this simply calls through to the {@see DriverEnvironmentCheckInterface::checkEnvironment()} with some
     *  additional debug output to the provided `$console`.
     *
     * It is not meant to be called directly from within this same class, but from {@see CheckEnvironment}, which will
     *  add important contextual output to the provided `$console`.
     */
    public function checkCommandEnvironment(Console $console): bool
    {
        $console->writeln("Checking environment for {$this->driverName} ({$this->getName()})...", OutputInterface::VERBOSITY_VERY_VERBOSE);

        return $this->getDriver($this->getDefaultConsole($console))
            ->setConsole($console)
            ->checkEnvironment();
    }

    /**
     * This is where the magic happens. Here, {@see AccessibleInput::injectDefinition()} is used to combine the original input definition with the definition of
     *  this very command (i.e. the driver-specific command).
     */
    protected function getDefaultConsole(Console $console): Console
    {
        AccessibleInput::injectDefinition($console->input, $this->getDefinition());

        return new Console($console->input, $console->output);
    }

    protected function getArgumentFile(Console $console): string
    {
        try {
            return $console->input->getArgument('file');
        } catch (InvalidArgumentException) {
            return self::DEFAULT_FILE;
        }
    }

    protected function getArgumentDate(Console $console): ?\DateTimeInterface
    {
        if ($console->input->hasParameterOption('--no-date')) {
            return null;
        }

        return $console->input->getOption('date')
            ? $this->date
            : null;
    }

    protected function getArgumentDateFormat(Console $console): string
    {
        $argumentDateFormat = $console->input->getOption('date-format');

        if (!\date($argumentDateFormat)) {
            $argumentDateFormat = AbstractFileTemplate::DATE_FORMAT;

            $console->warning("Invalid date format provided, default to `{$argumentDateFormat}`..");
        }

        return $argumentDateFormat;
    }

    protected function getArgumentPort(Console $console): int
    {
        $argumentPort = (int)$console->input->getOption('port');

        if (0 >= $argumentPort) {
            $argumentPort = self::DEFAULT_PORT;

            $console->warning("Invalid port given, default to {$argumentPort}.");
        }

        return $argumentPort;
    }

    protected function getArgumentDebug(Console $console): bool
    {
        return $console->output->isDebug();
    }

    protected function getArgumentTimeout(Console $console): int
    {
        $argumentTimeout = (int)$console->input->getOption('timeout');

        if (10 > $argumentTimeout) {
            $argumentTimeout = self::DEFAULT_TIMEOUT;

            $console->warning("Invalid timeout given, default to {$argumentTimeout}s.");
        }

        return $argumentTimeout;
    }

    protected function getArgumentSkipCheck(Console $console): bool
    {
        return (bool)$console->input->getOption('skip-check');
    }
}
