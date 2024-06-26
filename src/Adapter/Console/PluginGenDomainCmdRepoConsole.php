<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Contract\ContainerInterface;
use MaliBoot\PluginCodeGenerator\Client\Constants\FileType;
use MaliBoot\Utils\File;
use Symfony\Component\Console\Input\InputOption;

class PluginGenDomainCmdRepoConsole extends AbstractCodeGenConsole
{
    protected ?string $pluginName;

    protected ?string $table;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, 'plugin:gen-domain-cmd-repo');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Create a new plugin domain command repository');
        $this->addOption('enable-query-command', null, InputOption::VALUE_OPTIONAL, '是否支持读写分离架构', 'false');
        $this->defaultConfigure();
    }

    public function handle()
    {
        $this->pluginName = $this->getPluginName();
        $this->table = $this->input->getArgument('table');
        $className = $this->input->getOption('class', null);
        $this->enableCmdQry = $this->input->getOption('enable-query-command') === 'true';
        $option = $this->initOption();

        $this->generator($this->pluginName, $this->table, $option, $className);
    }

    protected function getStub(): string
    {
        return File::get(__DIR__ . '/stubs/domain-cmd-repo.stub');
    }

    protected function getInheritance(): string
    {
        if (! $this->enableCmdQry) {
            return 'CommonRepositoryInterface';
        }
        return 'CommandRepositoryInterface';
    }

    protected function getUses(): array
    {
        if (! $this->enableCmdQry) {
            return [
                'MaliBoot\\Cola\\Domain\\CommonRepositoryInterface',
            ];
        }
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
