<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Contract\ContainerInterface;
use Hyperf\Stringable\Str;
use MaliBoot\PluginCodeGenerator\Client\Constants\FileType;
use MaliBoot\Utils\CodeGen\Plugin;
use MaliBoot\Utils\File;
use Symfony\Component\Console\Input\InputOption;

class PluginGenCommonRepoConsole extends AbstractCodeGenConsole
{
    protected ?string $pluginName;

    protected ?string $table;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, 'plugin:gen-common-repo');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Create a new plugin common repository implementation');
        $this->defaultConfigure();
    }

    public function handle()
    {
        $this->pluginName = $this->getPluginName();
        $this->table = $this->input->getArgument('table');
        $className = $this->input->getOption('class', null);
        $this->businessName = $this->getBusinessName();
        $option = $this->initOption();

        $this->generator($this->pluginName, $this->table, $option, $className);
    }

    protected function getStub(): string
    {
        return File::get(__DIR__ . '/stubs/repo.stub');
    }

    protected function getInheritance(): string
    {
        return 'AbstractCommonDBRepository';
    }

    protected function getUses(): array
    {
        return [
            'MaliBoot\\Cola\\Infra\\AbstractCommonDBRepository',
        ];
    }

    protected function getFileType(): string
    {
        return FileType::INFRA_REPOSITORY;
    }

    protected function getClassSuffix(): string
    {
        return 'Repo';
    }

    /**
     * 使用给定类名称生成类.
     */
    protected function buildClass(string $pluginName, string $table, string $className, CodeGenOption $option, array $fields = []): string
    {
        return parent::buildClass($pluginName, $table, $className, $option, $fields);
    }
}
