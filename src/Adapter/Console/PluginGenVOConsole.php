<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Contract\ContainerInterface;
use MaliBoot\PluginCodeGenerator\Client\Constants\FileType;
use MaliBoot\Utils\File;
use Symfony\Component\Console\Input\InputOption;

class PluginGenVOConsole extends AbstractCodeGenConsole
{
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, 'plugin:gen-vo');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Create a new plugin view object');
        $this->defaultConfigure();
        $this->tableConfigure();
        $this->addOption('fields', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, '字段');
    }

    public function handle()
    {
        $option = $this->initOption();
        $pluginName = $this->getPluginName();
        $table = $this->input->getArgument('table');
        $className = $this->input->getOption('class');
        $fields = $this->input->getOption('fields');

        $this->generator($pluginName, $table, $option, $className, $fields);
    }

    protected function getStub(): string
    {
        return File::get(__DIR__ . '/stubs/view-object.stub');
    }

    protected function getInheritance(): string
    {
        return '';
    }

    protected function getUses(): array
    {
        return [
            'MaliBoot\\Dto\\Annotation\\ViewObject',
            'MaliBoot\\Lombok\\Annotation\\Field',
        ];
    }

    protected function getFileType(): string
    {
        return FileType::CLIENT_VIEW_OBJECT;
    }

    protected function getClassSuffix(): string
    {
        return 'VO';
    }
}
