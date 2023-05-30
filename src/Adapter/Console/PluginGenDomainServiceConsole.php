<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Contract\ContainerInterface;
use MaliBoot\PluginCodeGenerator\Client\Constants\FileType;
use MaliBoot\Utils\File;

class PluginGenDomainServiceConsole extends AbstractCodeGenConsole
{
    protected ?string $pluginName;

    protected ?string $table;

    protected ?string $cnName;

    protected ?string $platform;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, 'plugin:gen-domain-service');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Create a new plugin domain service');
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
        return File::get(__DIR__ . '/stubs/domain-service.stub');
    }

    protected function getInheritance(): string
    {
        return 'AbstractDomainService';
    }

    protected function getUses(): array
    {
        return [
            'MaliBoot\\Di\\Annotation\\Inject',
            'MaliBoot\\Cola\\Domain\\AbstractDomainService',
            'MaliBoot\\Cola\\Annotation\\DomainService',
        ];
    }

    protected function getFileType(): string
    {
        return FileType::DOMAIN_SERVICE;
    }

    protected function getClassSuffix(): string
    {
        return 'DomainService';
    }
}
