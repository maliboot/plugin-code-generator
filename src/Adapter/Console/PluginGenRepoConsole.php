<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Contract\ContainerInterface;

class PluginGenRepoConsole extends AbstractCodeGenConsole
{
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, 'plugin:gen-repo');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Create a new plugin repository');
        $this->defaultConfigure();
    }

    public function handle()
    {
        $commonArguments = [
            'plugin' => $this->input->getArgument('plugin'),
            'table' => $this->input->getArgument('table'),
            '--class' => $this->input->getOption('class') ?? null,
            '--name' => $this->input->getOption('name') ?? null,
            '--pool' => $this->input->getOption('pool') ?? null,
            '--path' => $this->input->getOption('path') ?? null,
            '--prefix' => $this->input->getOption('prefix') ?? null,
            '--force' => $this->input->getOption('force') ?? false,
        ];

        $this->call('plugin:gen-domain-cmd-repo', $commonArguments);
        $this->call('plugin:gen-cmd-repo', $commonArguments);
        $this->call('plugin:gen-qry-repo', $commonArguments);
    }

    protected function getStub(): string
    {
        return '';
    }

    protected function getInheritance(): string
    {
        return '';
    }

    protected function getUses(): array
    {
        return [];
    }

    protected function getFileType(): string
    {
        return '';
    }
}
