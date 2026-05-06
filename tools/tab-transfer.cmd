:
: MIT License
:
: Copyright (c) 2021-2026 machinateur
:
: Permission is hereby granted, free of charge, to any person obtaining a copy
: of this software and associated documentation files (the "Software"), to deal
: in the Software without restriction, including without limitation the rights
: to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
: copies of the Software, and to permit persons to whom the Software is
: furnished to do so, subject to the following conditions:
:
: The above copyright notice and this permission notice shall be included in all
: copies or substantial portions of the Software.
:
: THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
: IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
: FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
: AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
: LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
: OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
: SOFTWARE.
:

@echo off

:: localize variables
setlocal

:: set up path
set "PATH=%cd%\php-8.3.13;%PATH%"
set "PATH=%cd%\platform-tools-35.0.2;%PATH%"
set "PATH=%cd%\ios-webkit-debug-proxy-1.9.1;%PATH%"

:: prompt
echo.
echo " -!-  You can always stop the process by pressing `CTRL + C`"
echo.
pause "Press any key to get started..."
echo.

:: run the phar phar
php tab-transfer.phar %*

:: prompt
echo.
pause "Press any key to exit..."

exit /b 0
