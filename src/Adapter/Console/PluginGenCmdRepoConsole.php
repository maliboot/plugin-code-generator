<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Contract\ContainerInterface;
use Hyperf\Stringable\Str;
use MaliBoot\PluginCodeGenerator\Client\Constants\FileType;
use MaliBoot\Utils\CodeGen\Plugin;
use MaliBoot\Utils\File;

class PluginGenCmdRepoConsole extends AbstractCodeGenConsole
{
    protected ?string $pluginName;

    protected ?string $table;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, 'plugin:gen-cmd-repo');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Create a new plugin command repository implementation');
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
        return File::get(__DIR__ . '/stubs/cmd-repo.stub');
    }

    protected function getInheritance(): string
    {
        return 'AbstractCommandDBRepository';
    }

    protected function getInterface(string $table, ?string $shortClassName = null): string
    {
        return $this->getStudlyName($table) . 'Repo';
    }

    protected function getUses(): array
    {
        return [
            'MaliBoot\\Cola\\Infra\\AbstractCommandDBRepository',
            $this->getInterfaceUse(),
        ];
    }

    protected function getInterfaceUse(): string
    {
        $plugin = new Plugin($this->pluginName);
        $className = Str::studly(Str::singular($this->table));
        return $plugin->namespace($this->getInterfacePath()) . $className . 'Repo';
    }

    protected function getInterfacePath(): string
    {
        $pluginConfig = $this->config->get('plugin', []);
        $fullPath = sprintf(
            '%s/%s/%s',
            data_get($pluginConfig, 'paths.base_path', 'plugin'),
            $this->getPluginName(),
            data_get($pluginConfig, 'paths.generator.' . FileType::DOMAIN_REPOSITORY . '.path')
        );

        return str_replace(BASE_PATH . '/', '', $fullPath);
    }

    protected function getFileType(): string
    {
        return FileType::INFRA_REPOSITORY;
    }

    protected function getClassSuffix(): string
    {
        return 'CmdRepo';
    }

    /**
     * 使用给定类名称生成类.
     */
    protected function buildClass(string $pluginName, string $table, string $className, CodeGenOption $option, array $fields = []): string
    {
        return parent::buildClass($pluginName, $table, $className, $option, $fields);
    }
}
