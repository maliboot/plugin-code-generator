<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator;

use MaliBoot\PluginCodeGenerator\Adapter\Console;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'commands' => [
                Console\PluginCreateConsole::class,
                Console\PluginGenConfigProviderConsole::class,
                Console\PluginGenComposerConsole::class,
                Console\PluginGenControllerConsole::class,
                Console\PluginGenRpcConsole::class,
                Console\PluginGenExecutorConsole::class,
                Console\PluginGenVOConsole::class,
                Console\PluginGenCommandConsole::class,
                Console\PluginGenApiConsole::class,
                Console\PluginGenModelConsole::class,
                Console\PluginGenRepoConsole::class,
                Console\PluginGenDomainCmdRepoConsole::class,
                Console\PluginGenDomainServiceConsole::class,
                Console\PluginGenCmdRepoConsole::class,
                Console\PluginGenQryRepoConsole::class,
                Console\PluginGenQryServiceConsole::class,
                Console\PluginGenDOConsole::class,
                Console\PluginGenCurdConsole::class,
                Console\PluginGenByApifoxConsole::class,
                Console\PluginGenCommonRepoConsole::class,
            ],
            'dependencies' => [
            ],
            'listeners' => [
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for plugin code generator.',
                    'source' => __DIR__ . '/../publish/plugin.php',
                    'destination' => BASE_PATH . '/config/autoload/plugin.php',
                ],
            ],
        ];
    }
}
