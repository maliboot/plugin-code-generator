<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Contract\ContainerInterface;
use MaliBoot\PluginCodeGenerator\Client\Constants\FileType;
use MaliBoot\Utils\File;

class PluginGenQryServiceConsole extends AbstractCodeGenConsole
{
    protected ?string $pluginName;

    protected ?string $table;

    protected ?string $cnName;

    protected ?string $platform;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, 'plugin:gen-qry-service');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Create a new plugin query service');
        $this->defaultConfigure();
    }

    public function handle()
    {
        $this->pluginName = $this->getPluginName();
        $this->table = $this->input->getArgument('table');
        $className = $this->input->getOption('class');
        $this->businessName = $this->getBusinessName();
        $option = $this->initOption();

        $this->generator($this->pluginName, $this->table, $option, $className);
    }

    protected function getStub(): string
    {
        return File::get(__DIR__ . '/stubs/query-service.stub');
    }

    protected function getInheritance(): string
    {
        return 'AbstractQueryService';
    }

    protected function getUses(): array
    {
        return [
            'MaliBoot\\Di\\Annotation\\Inject',
            'MaliBoot\\Cola\\Query\\AbstractQueryService',
            'MaliBoot\\Cola\\Annotation\\QueryService',
        ];
    }

    protected function getFileType(): string
    {
        return FileType::QUERY;
    }

    protected function getClassSuffix(): string
    {
        return 'QryService';
    }
}
