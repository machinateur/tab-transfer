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

class MarkdownFile extends AbstractFileTemplate
{
    public function __construct(string $file, array $jsonArray)
    {
        parent::__construct($file, $jsonArray);

        $this->filenameSuffix = '-gist';
    }

    public function getExtension(): string
    {
        return 'md';
    }

    public function render(): string
    {
        return '# Tabs'
            . \PHP_EOL
            . \PHP_EOL
            . \join(
                \PHP_EOL, \array_map(static function (array $tab): string {
                    $url   = $tab['url'];
                    $title = $tab['title'] ?: $url;

                    return \sprintf('- [%s](%s)', $title, $url);
                }, $this->tabs)
            )
            . \PHP_EOL
            . \PHP_EOL
            . 'Created using [machinateur/tab-transfer](https://github.com/machinateur/tab-transfer).'
            . \PHP_EOL;
    }
}
