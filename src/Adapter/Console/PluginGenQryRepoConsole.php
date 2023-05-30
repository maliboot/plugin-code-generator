<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Contract\ContainerInterface;
use MaliBoot\PluginCodeGenerator\Client\Constants\FileType;
use MaliBoot\Utils\File;

class PluginGenQryRepoConsole extends AbstractCodeGenConsole
{
    protected ?string $pluginName;

    protected ?string $table;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, 'plugin:gen-qry-repo');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Create a new plugin query repository');
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
        return File::get(__DIR__ . '/stubs/qry-repo.stub');
    }

    protected function getInheritance(): string
    {
        return 'AbstractQueryDBRepository';
    }

    protected function getInterface(string $table, ?string $shortClassName = null): string
    {
        return 'QueryDBRepositoryInterface';
    }

    protected function getUses(): array
    {
        return [
            'MaliBoot\\Cola\\Infra\\AbstractQueryDBRepository',
            'MaliBoot\\Cola\\Infra\\QueryDBRepositoryInterface',
        ];
    }

    protected function getFileType(): string
    {
        return FileType::INFRA_REPOSITORY;
    }

    protected function getClassSuffix(): string
    {
        return 'QryRepo';
    }

    /**
     * 使用给定类名称生成类.
     */
    protected function buildClass(string $pluginName, string $table, string $className, CodeGenOption $option, array $fields = []): string
    {
        return parent::buildClass($pluginName, $table, $className, $option);
    }
}
