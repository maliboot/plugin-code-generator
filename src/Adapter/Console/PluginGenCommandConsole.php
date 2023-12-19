<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Contract\ContainerInterface;
use Hyperf\Stringable\Str;
use MaliBoot\PluginCodeGenerator\Client\Constants\FileType;
use MaliBoot\Utils\File;
use Symfony\Component\Console\Input\InputOption;

class PluginGenCommandConsole extends AbstractCodeGenConsole
{
    protected ?string $method;

    protected ?string $cmdType;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, 'plugin:gen-command');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Create a new plugin command');
        $this->defaultConfigure();
        $this->tableConfigure();
        $this->addOption('method', null, InputOption::VALUE_OPTIONAL, '方法名称', 'curd');
        $this->addOption('cmd-type', null, InputOption::VALUE_OPTIONAL, '命令类型', 'query');
        $this->addOption('fields', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, '字段');
    }

    public function handle()
    {
        $pluginName = $this->getPluginName();
        $table = $this->input->getArgument('table');
        $this->table = $table;
        $className = $this->input->getOption('class', null);
        $this->method = $this->input->getOption('method');
        $this->cmdType = $this->input->getOption('cmd-type');
        $fields = $this->input->getOption('fields');

        if ($this->method !== 'curd') {
            $option = $this->initOption();
            $this->generator($pluginName, $table, $option, $className, $fields);
            return true;
        }

        $this->generatorCurd($pluginName, $table, $className);
        return true;
    }

    protected function generatorCurd(string $pluginName, string $table, ?string $className = null)
    {
        $curds = ['listByPage', 'create', 'update'];
        foreach ($curds as $curd) {
            $this->method = $curd;
            if (in_array($curd, ['listByPage'])) {
                $this->cmdType = 'query';
            } else {
                $this->cmdType = 'command';
            }
            $option = $this->initOption();
            $this->generator($pluginName, $table, $option, $className);
        }
    }

    protected function getStub(): string
    {
        return File::get(__DIR__ . '/stubs/command.stub');
    }

    protected function getInheritance(): string
    {
        return '';
    }

    protected function getUses(): array
    {
        return [
            'MaliBoot\\Dto\\Annotation\\DataTransferObject',
            'MaliBoot\\Lombok\\Annotation\\Field',
        ];
    }

    protected function getFileType(): string
    {
        if ($this->cmdType === 'query') {
            return FileType::CLIENT_DTO_QUERY;
        }
        return FileType::CLIENT_DTO_COMMAND;
    }

    protected function getClassSuffix(): string
    {
        $suffix = '';
        if (! empty($this->table)) {
            $suffix = Str::studly($this->method);
        }

        if ($this->cmdType === 'query') {
            $suffix .= 'Qry';
        } else {
            $suffix .= 'Cmd';
        }

        return $suffix;
    }

    protected function buildProperties(string $table, CodeGenOption $option, $fields = [])
    {
        $defaultFilterColumns = $this->filterColumns;
        switch ($this->method) {
            case 'list':
                $this->filterColumns = array_merge(['id'], $defaultFilterColumns);
                $properties = '';
                break;
            case 'create':
                $this->filterColumns = array_merge(['id', 'created_at', 'updated_at'], $defaultFilterColumns);
                $properties = parent::buildProperties($table, $option, $fields);
                break;
            case 'update':
                $this->filterColumns = array_merge(['created_at', 'updated_at'], $defaultFilterColumns);
                $properties = parent::buildProperties($table, $option, $fields);
                break;
            case 'delete':
            case 'getById':
                $properties = $this->buildIdQueryProperty($table, $option, $fields);
                break;
            default:
                if (! empty($fields)) {
                    $properties = parent::buildProperties($table, $option, $fields);
                } else {
                    $properties = '';
                }
                break;
        }

        $this->filterColumns = $defaultFilterColumns;
        return $properties;
    }

    protected function buildGetterAndSetter(string $table, CodeGenOption $option, $fields = []): string
    {
        $defaultFilterColumns = $this->filterColumns;
        switch ($this->method) {
            case 'list':
                $this->filterColumns = array_merge(['id'], $defaultFilterColumns);
                $methods = '';
                break;
            case 'create':
                $this->filterColumns = array_merge(['id', 'created_at', 'updated_at'], $defaultFilterColumns);
                $methods = parent::buildGetterAndSetter($table, $option, $fields);
                break;
            case 'update':
                $this->filterColumns = array_merge(['created_at', 'updated_at'], $defaultFilterColumns);
                $methods = parent::buildGetterAndSetter($table, $option, $fields);
                break;
            case 'delete':
            case 'getById':
                $methods = $this->getIdQueryGetSetMethods($table, $option, $fields);
                break;
            default:
                if (! empty($fields)) {
                    $methods = parent::buildGetterAndSetter($table, $option, $fields);
                } else {
                    $methods = '';
                }
                break;
        }

        $this->filterColumns = $defaultFilterColumns;
        return $methods;
    }

    protected function buildIdQueryProperty(string $table, CodeGenOption $option, $fields = [])
    {
        return parent::buildProperties(
            $table,
            $option,
            [
                [
                    'name' => 'id',
                    'type' => 'int',
                ],
            ]
        );
    }

    protected function getIdQueryGetSetMethods(string $table, CodeGenOption $option, $fields = [])
    {
        return parent::buildGetterAndSetter(
            $table,
            $option,
            [
                [
                    'name' => 'id',
                    'type' => 'int',
                ],
            ]
        );
    }

    /**
     * 使用给定类名称生成类.
     */
    protected function buildClass(string $pluginName, string $table, string $className, CodeGenOption $option, array $fields = []): string
    {
        $stub = parent::buildClass($pluginName, $table, $className, $option, $fields);
        $this->replaceCommandType($stub, $table);

        return $stub;
    }

    /**
     * 替换给定模板的版权信息.
     */
    protected function replaceCommandType(string &$stub, string $table): static
    {
        $cmdType = $this->getCommandType($table);
        $this->method === 'listByPage' && $cmdType = $cmdType . '-page';
        $stub = str_replace(
            '%COMMAND_TYPE%',
            $cmdType,
            $stub
        );

        return $this;
    }

    protected function getCommandType(string $table): string
    {
        return Str::lower($this->cmdType);
    }
}
