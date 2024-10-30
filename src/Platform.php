<?php
/*
 * MIT License
 *
 * Copyright (c) 2021-2024 machinateur
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

namespace Machinateur\ChromeTabTransfer;

final class Platform
{
    private function __construct()
    {}

    public static function isWindows(): bool
    {
        return 0 === \strpos(\PHP_OS, 'WIN');
    }

    /**
     * Extract a reference to a class-private property.
     *
     * This is a last-resort approach. Use with care. You have been warned.
     *
     * @see https://ocramius.github.io/blog/accessing-private-php-class-members-without-reflection/
     */
    public static function & extractPropertyReference(object $object, string $propertyName): mixed
    {
        $value = & \Closure::bind(function & () use ($propertyName) {
            return $this->{$propertyName};
        }, $object, $object)->__invoke();

        return $value;
    }

    public static function isPhar(): bool
    {
        return '' !== \Phar::running();
    }
}
