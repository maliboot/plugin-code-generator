<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Contract\ContainerInterface;
use MaliBoot\PluginCodeGenerator\Client\Constants\FileType;
use MaliBoot\Utils\File;
use Symfony\Component\Console\Input\InputOption;

class PluginGenApiConsole extends AbstractCodeGenConsole
{
    protected ?string $pluginName;

    protected ?string $table;

    protected ?string $cnName;

    protected ?string $platform;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, 'plugin:gen-api');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Create a new plugin service');
        $this->defaultConfigure();
        $this->addOption('cn-name', null, InputOption::VALUE_OPTIONAL, '中文业务名称');
        $this->addOption('platform', null, InputOption::VALUE_OPTIONAL, '平台', 'web');
    }

    public function handle()
    {
        $this->pluginName = $this->getPluginName();
        $this->table = $this->input->getArgument('table');
        $this->cnName = $this->input->getOption('cn-name');
        $className = $this->input->getOption('class');
        $this->businessName = $this->getBusinessName();
        $option = $this->initOption();

        $this->generator($this->pluginName, $this->table, $option, $className);
    }

    protected function getStub(): string
    {
        return File::get(__DIR__ . '/stubs/api.stub');
    }

    protected function getInheritance(): string
    {
        return '';
    }

    protected function getUses(): array
    {
        $uses = [
            'MaliBoot\\Dto\\IdVO',
            'MaliBoot\\Dto\\PageVO',
            'MaliBoot\\Dto\\MultiVO',
            'MaliBoot\\Dto\\EmptyVO',
        ];

        $this->addVOUses($uses)->addCmdUses($uses);
        return $uses;
    }

    protected function addVOUses(array &$uses): static
    {
        $namespace = $this->getNamespaceByPath($this->getPath(FileType::CLIENT_VIEW_OBJECT));
        $uses[] = sprintf('%s%sVO', $namespace, $this->businessName);

        return $this;
    }

    protected function addCmdUses(array &$uses): static
    {
        $curds = ['ListByPageQry', 'CreateCmd', 'UpdateCmd'];
        $studlyName = $this->getStudlyName($this->table);

        foreach ($curds as $curd) {
            if (in_array($curd, ['ListByPageQry', 'GetByIdQry'])) {
                $fileType = FileType::CLIENT_DTO_QUERY;
            } else {
                $fileType = FileType::CLIENT_DTO_COMMAND;
            }

            $namespace = $this->getNamespaceByPath($this->getPath($fileType));
            $uses[] = sprintf('%s%s%s', $namespace, $studlyName, $curd);
        }

        return $this;
    }

    protected function getFileType(): string
    {
        return FileType::CLIENT_API;
    }

    protected function getClassSuffix(): string
    {
        return 'Service';
    }
}
