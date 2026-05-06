#!/usr/bin/env bash

#
# MIT License
#
# Copyright (c) 2021-2026 machinateur
#
# Permission is hereby granted, free of charge, to any person obtaining a copy
# of this software and associated documentation files (the "Software"), to deal
# in the Software without restriction, including without limitation the rights
# to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
# copies of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:
#
# The above copyright notice and this permission notice shall be included in all
# copies or substantial portions of the Software.
#
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
# IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
# FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
# AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
# LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
# OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
# SOFTWARE.
#

if [ -f "tab-transfer.phar" ]; then
  # prefer phar, if present
  php tab-transfer.phar "$@"
else
  # fallback to php script

  if [ ! -d "vendor/" ]; then
    if [ ! -f "composer.phar" ]; then
      echo "Composer.phar not found!"

      exit 1
    fi

    echo "Dependencies not found. Installing..."
    echo

    # if no dependencies are installed, do that first
    php composer.phar install
  fi

  php tab-transfer "$@"
fi
