<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Contract\ContainerInterface;
use MaliBoot\PluginCodeGenerator\Client\Constants\FileType;
use MaliBoot\Utils\File;
use Symfony\Component\Console\Input\InputOption;

class PluginGenModelConsole extends AbstractCodeGenConsole
{
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, 'plugin:gen-model');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Create a new plugin model');
        $this->defaultConfigure();
        $this->tableConfigure();
        $this->addOption('type', null, InputOption::VALUE_OPTIONAL, '模型类别');
    }

    public function handle()
    {
        $option = $this->initOption();
        $pluginName = $this->getPluginName();
        $table = $this->input->getArgument('table');
        if (empty($table)) {
            $this->line('The "table" argument does not exist', 'error');
            return;
        }
        $className = $this->input->getOption('class', null);

        $this->generator($pluginName, $table, $option, $className);
    }

    protected function getStub(): string
    {
        return File::get(__DIR__ . '/stubs/model.stub');
    }

    protected function getInheritance(): string
    {
        return '';
    }

    protected function getUses(): array
    {
        $uses = [
            'MaliBoot\\Lombok\\Annotation\\Field',
        ];

        switch ($this->getType()) {
            case 'aggregate':
                $uses[] = 'MaliBoot\\Cola\\Annotation\\AggregateRoot';
                break;
            case 'entity':
                $uses[] = 'MaliBoot\\Cola\\Annotation\\Entity';
                break;
            case 'vo':
                $uses[] = 'MaliBoot\\Cola\\Annotation\\ValueObject';
                break;
            default:
                $uses[] = 'MaliBoot\\Cola\\Annotation\\AggregateRoot';
                break;
        }

        return $uses;
    }

    protected function getType(): string
    {
        return $this->input->getOption('type') !== null ? $this->input->getOption('type') : 'aggregate';
    }

    protected function getInterface(string $table, ?string $shortClassName = null): string
    {
        return '';
    }

    protected function getFileType(): string
    {
        switch ($this->getType()) {
            case 'aggregate':
                $fileType = FileType::DOMAIN_MODEL_AGGREGATE;
                break;
            case 'entity':
                $fileType = FileType::DOMAIN_MODEL_ENTITY;
                break;
            case 'vo':
                $fileType = FileType::DOMAIN_MODEL_VALUE_OBJECT;
                break;
            default:
                $fileType = FileType::DOMAIN_MODEL_AGGREGATE;
                break;
        }
        return $fileType;
    }

    protected function getPath(string $fileType): string
    {
        return parent::getPath(FileType::DOMAIN_MODEL) . '/' . $this->getBusinessName();
    }

    /**
     * 使用给定类名称生成类.
     */
    protected function buildClass(string $pluginName, string $table, string $className, CodeGenOption $option, array $fields = []): string
    {
        $stub = parent::buildClass($pluginName, $table, $className, $option, $fields);
        $this->replaceClassAnnotation($stub, $table);

        return $stub;
    }

    /**
     * 替换给定模板的版权信息.
     */
    protected function replaceClassAnnotation(string &$stub, string $table): static
    {
        $stub = str_replace(
            ['%CLASS_ANNOTATION%'],
            [$this->getClassAnnotation($table)],
            $stub
        );

        return $this;
    }

    protected function getClassAnnotation(string $table): string
    {
        switch ($this->getType()) {
            case 'aggregate':
                $classAnnotation = 'AggregateRoot';
                break;
            case 'entity':
                $classAnnotation = 'Entity';
                break;
            case 'vo':
                $classAnnotation = 'ValueObject';
                break;
            default:
                $classAnnotation = 'AggregateRoot';
                break;
        }
        return $classAnnotation;
    }
}
