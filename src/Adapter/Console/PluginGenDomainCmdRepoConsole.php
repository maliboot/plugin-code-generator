<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Contract\ContainerInterface;
use MaliBoot\PluginCodeGenerator\Client\Constants\FileType;
use MaliBoot\Utils\File;

class PluginGenDomainCmdRepoConsole extends AbstractCodeGenConsole
{
    protected ?string $pluginName;

    protected ?string $table;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, 'plugin:gen-domain-cmd-repos');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Create a new plugin domain command repository');
        $this->defaultConfigure();
    }

    public function handle()
    {
        $this->pluginName = $this->getPluginName();
        $this->table = $this->input->getArgument('table');
        $className = $this->input->getOption('class', null);
        $option = $this->initOption();

        $this->generator($this->pluginName, $this->table, $option, $className);
    }

    protected function getStub(): string
    {
        return File::get(__DIR__ . '/stubs/domain-cmd-repo.stub');
    }

    protected function getInheritance(): string
    {
        return 'CommandRepositoryInterface';
    }

    protected function getUses(): array
    {
        return [
            'MaliBoot\\Cola\\Domain\\CommandRepositoryInterface',
        ];
    }

    protected function getFileType(): string
    {
        return FileType::DOMAIN_REPOSITORY;
    }

    protected function getClassSuffix(): string
    {
        return 'Repo';
    }
}
