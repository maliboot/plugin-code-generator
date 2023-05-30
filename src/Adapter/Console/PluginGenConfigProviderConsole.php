<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Contract\ContainerInterface;
use MaliBoot\PluginCodeGenerator\Client\Constants\FileType;
use MaliBoot\Utils\File;

class PluginGenConfigProviderConsole extends AbstractCodeGenConsole
{
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, 'plugin:gen-config-provider');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Create a new config provider');
        $this->defaultConfigure();
    }

    public function handle()
    {
        $option = $this->initOption();
        $pluginName = $this->getPluginName();

        $this->generator($pluginName, '', $option, 'Plugin');
    }

    protected function getStub(): string
    {
        return File::get(__DIR__ . '/stubs/config-provider.stub');
    }

    protected function getInheritance(): string
    {
        return 'AbstractPlugin';
    }

    protected function getUses(): array
    {
        return [
            'MaliBoot\\Plugin\\AbstractPlugin',
        ];
    }

    protected function getFileType(): string
    {
        return FileType::ROOT;
    }

    protected function getClassSuffix(): string
    {
        return '';
    }
}
