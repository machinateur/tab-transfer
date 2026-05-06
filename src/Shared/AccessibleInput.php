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

namespace Machinateur\ChromeTabTransfer\Shared;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;

/**
 * This is like a little hack to modify the definition of any given {@see Input}, by simply extracting it.
 *
 * This final class inherits from a concrete implementation of the {@see Input}, {@see ArrayInput}, but has a
 *  constructor with `private` visibility, effectively making this a _static class_.
 */
final class AccessibleInput extends ArrayInput
{
    private function __construct()
    {}

    /**
     * @deprecated This is highly unstable and discouraged.
     */
    public static function injectDefinition(InputInterface $input, InputDefinition $referenceDefinition): void
    {
        if ( ! $input instanceof Input) {
            throw new \LogicException('Incompatible input given.');
        }

        $def = $input->definition;

        foreach ($referenceDefinition->getArguments() as $argument) {
            if ($def->hasArgument($argument->getName())) {
                continue;
            }

            $def->addArgument($argument);
        }
        foreach ($referenceDefinition->getOptions() as $option) {
            if ($def->hasOption($option->getName())) {
                continue;
            }

            $def->addOption($option);
        }
    }
}
