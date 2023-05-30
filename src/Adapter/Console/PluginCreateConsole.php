<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Command\Command as BaseConsole;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Str;
use MaliBoot\Utils\File;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class PluginCreateConsole extends BaseConsole
{
    protected LoggerInterface $logger;

    public function __construct(
        protected LoggerFactory $loggerFactory,
        protected ContainerInterface $container,
        protected ConfigInterface $config
    ) {
        $this->logger = $loggerFactory->get(__CLASS__);
        parent::__construct('plugin:create');
    }

    public function configure(): void
    {
        parent::configure();
        $this->addArgument('plugin', InputArgument::REQUIRED, '插件名称');
        $this->addOption('base-path', null, InputOption::VALUE_OPTIONAL, '插件根目录');
        $this->setDescription('Create a new plugin');
    }

    public function handle()
    {
        $pluginName = $this->formatPluginName($this->input->getArgument('plugin'));
        $codeGeneratorConfig = $this->config->get('plugin');

        if (empty($pluginBasePath = $this->input->getOption('base-path'))) {
            $pluginBasePath = Arr::get($codeGeneratorConfig, 'paths.base_path');
        }

        $pluginDirFiles = Arr::get($codeGeneratorConfig, 'paths.generator');

        $this->makeDirectoryFiles($pluginName, $pluginBasePath, $pluginDirFiles);
        $this->generateComposerFileAndConfigProvider($pluginName);
        $this->info(sprintf('成功创建插件：%s', $pluginName));
    }

    protected function formatPluginName(string $name): string
    {
        return str_replace('_', '-', Str::kebab($name));
    }

    private function makeDirectoryFiles(string $pluginName, string $pluginBasePath, array $pluginDirFiles)
    {
        foreach ($pluginDirFiles as $item) {
            if (! $item['generate']) {
                continue;
            }

            $dir = $item['path'];
            $path = $pluginBasePath . '/' . $pluginName . '/' . $dir;
            if (! File::exists($path)) {
                File::makeDirectory($path, 0755, true, true);
            }

            if ($item['gitignore']) {
                File::put($path . '/.gitignore', "/*\n!.gitignore");
            }

            $this->info(sprintf('成功创建目录：%s', $path));
        }
        return true;
    }

    private function generateComposerFileAndConfigProvider(string $pluginName)
    {
        $arguments = [
            'plugin' => $pluginName,
        ];
        $this->call('plugin:gen-composer', $arguments);
        $this->call('plugin:gen-config-provider', $arguments);
    }
}
