<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Contract\ContainerInterface;
use MaliBoot\PluginCodeGenerator\Client\Constants\FileType;
use MaliBoot\Utils\File;

class PluginGenDOConsole extends AbstractCodeGenConsole
{
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, 'plugin:gen-do');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Create a new plugin data object');
        $this->defaultConfigure();
        $this->tableConfigure();
    }

    public function handle()
    {
        $pluginName = $this->getPluginName();
        $table = $this->input->getArgument('table');
        $className = $this->input->getOption('class', null);
        $this->businessName = $this->getBusinessName();

        $option = $this->initOption();

        $this->generator($pluginName, $table, $option, $className);
    }

    protected function getStub(): string
    {
        return File::get(__DIR__ . '/stubs/database-do.stub');
    }

    protected function getInheritance(): string
    {
        return '';
    }

    protected function getInterface(string $table, ?string $shortClassName = null): string
    {
        return '';
    }

    protected function getUses(): array
    {
        return [
            'MaliBoot\\Cola\\Annotation\\Database',
            'MaliBoot\\Database\\Annotation\\Column',
        ];
    }

    protected function getFileType(): string
    {
        return FileType::INFRA_DATA_OBJECT;
    }

    protected function getClassSuffix(): string
    {
        return 'DO';
    }

    /**
     * 使用给定类名称生成类.
     */
    protected function buildClass(string $pluginName, string $table, string $className, CodeGenOption $option, array $fields = []): string
    {
        $stub = parent::buildClass($pluginName, $table, $className, $option, $fields);
        $this->replaceConnection($stub, $option->getPool())
            ->replaceTable($stub, $table);

        return $stub;
    }

    /**
     * Replace the table name for the given stub.
     */
    protected function replaceTable(string &$stub, string $table): static
    {
        $stub = str_replace('%TABLE%', $table, $stub);

        return $this;
    }

    protected function replaceConnection(string &$stub, string $connection): static
    {
        $stub = str_replace(
            ['%CONNECTION%'],
            [$connection],
            $stub
        );

        return $this;
    }
}
