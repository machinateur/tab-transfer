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

/**
 * A simple webkit dev-tools protocol client, using web-sockets (`ws:`).
 *  It is used by the {@see \Machinateur\ChromeTabTransfer\TabLoader\WdpReopenTabLoader} to implement
 *   automated tab restoration on iphone.
 *
 * This implementation is based on the
 *  example {@see https://github.com/google/ios-webkit-debug-proxy/blob/master/examples/wdp_client.html from the `ios_webkit_debug_proxy` docs}.
 *
 * The chrome dev-tools protocol and webkit dev-tools protocol have diverged in recent versions,
 *  which causes compatibility issues.  A list of available protocol domains can be found here:
 *   https://github.com/WebKit/webkit/tree/main/Source/JavaScriptCore/inspector/protocol
 *
 * The main commands by the client are `Target.sendCommandToTarget()` and `Runtime.evaluate()`.
 */
class WebsocketWdpClient extends AbstractFileTemplate
{
    public const FILENAME   = 'wdp_client.html';

    protected const JSON_FLAGS = \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT;

    public function __construct(
        array                     $tabs,
        protected readonly int    $port,
        protected readonly string $targetPage,
    ) {
        parent::__construct(self::FILENAME, $tabs);
    }

    public function getExtension(): string
    {
        return 'html';
    }

    public function render(): string
    {
        $port          = $this->port;
        $targetPage    = $this->targetPage;
        $index         = 0;
        $commandsList  = \array_map(static fn(array $tab): array => [
            'id'     => ++$index,
            'method' => 'Target.sendMessageToTarget',
            'params' => [
                'targetId' => "{$targetPage}",
                // Yes, this is json-in-json... :)
                'message'  => \json_encode([
                    'id'     => 1,
                    'method' => 'Runtime.evaluate',
                    'params' => [
                        'expression' => "open('{$tab['url']}');",
                    ],
                ], flags: self::JSON_FLAGS),
            ],
        ], $this->tabs);
        $commandsTotal = \count($commandsList);
        $commands      = \json_encode($commandsList, flags: self::JSON_FLAGS);

        return <<<HTML
<!--
 ~ MIT License
 ~
 ~ Copyright (c) 2021-2026 machinateur
 ~
 ~ Permission is hereby granted, free of charge, to any person obtaining a copy
 ~ of this software and associated documentation files (the "Software"), to deal
 ~ in the Software without restriction, including without limitation the rights
 ~ to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 ~ copies of the Software, and to permit persons to whom the Software is
 ~ furnished to do so, subject to the following conditions:
 ~
 ~ The above copyright notice and this permission notice shall be included in all
 ~ copies or substantial portions of the Software.
 ~
 ~ THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 ~ IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 ~ FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 ~ AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 ~ LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 ~ OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 ~ SOFTWARE.
 ~
 ~ -------------------------------------------------------------------------------
 ~
 ~ Based on https://github.com/google/ios-webkit-debug-proxy/blob/master/examples/wdp_client.html.
 ~
 ~ Google BSD license https://developers.google.com/google-bsd-license
 ~ Copyright 2012 Google Inc. wrightt@google.com
 ~
 ~ An example browser-based client.
 -->
<html lang="en">
<head>
    <title>tab-transfer - iOS WDP Script</title>
    
    <script type="text/javascript">
        var wdpClient = (function () {
            var ws            = undefined;
            var commands      = {$commands};
            var commandsTotal = {$commandsTotal};
            var url           = `ws://localhost:{$port}/devtools/page/{$targetPage}`;
            var targetId      = '{$targetPage}';

            function restore()
            {
                ws && ws.close();
                ws = new WebSocket(url);

                ws.addEventListener('open',    function ()
                {
                    console.log(`Opened socket to \${url}.`);
                });
                ws.addEventListener('message', function (event)
                {
                    console.log('Message:', event.data);

                    // This feature is currently inhibited, see discussion at TODO.
                    var message = JSON.parse(event.data);
                    if (message.method === 'Target.targetCreated') {
                        targetId = message.params.targetInfo.targetId;

                        console.log('New target:', targetId);
                    }

                    sendNextCommand();
                });
                ws.addEventListener('close',   function ()
                {
                    console.log(`Closed socket to \${url}.`);
                });
                ws.addEventListener('error',   function (error)
                {
                    console.log('Error:', error.data);
                });

                return ws;
            }

            function sendNextCommand()
            {
                if ( ! commands.length) {
                    return;
                }

                var command = commands.shift();
                command.params.targetId = targetId;

                var data = JSON.stringify(command);
                console.log('Command:', data);

                ws.send(data);
                
                updateProgress();
            }

            function updateProgress()
            {
                var commandsSent = commandsTotal - commands.length;

                document.getElementById('bar').style.width   = `\${(commandsSent / commandsTotal) * 100}%`;
                document.getElementById('title').textContent = `Restored \${commandsSent} of \${commandsTotal} tabs (\${commands.length ? commands.length : 'none'} left)...`;
            }

            return { restoreTabs: restore };
        })();
    </script>
    <style>
        #progress {
          width: 100%;

          background-color: grey;
          border: 1px solid black;
        }

        #bar {
          width: 0;

          background-color: green;
          border-right: 1px solid black;
          height: 30px;

          transition: width 100ms linear;
        }
    </style>
</head>
<body>
    <h1>tab-transfer: restore to phone</h1>

    <div id="progress">
        <div id="bar"></div>
    </div>

    <p id="title">Waiting...</p>

    <hr/>

    <p style="color: gray;">You can open the dev-tools JavaScript console to see details on communication.</p>

    <hr/>

    <p>Created using <a href="https://github.com/machinateur/tab-transfer" target="_blank"><code>machinateur/tab-transfer</code></a>.</p>

    <script type="text/javascript">
        window.addEventListener('load', wdpClient.restoreTabs);
    </script>
</body>
</html>
HTML;
    }
}
