<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Collection\Arr;
use Hyperf\Command\Command as BaseConsole;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Stringable\Str;
use MaliBoot\PluginCodeGenerator\Client\Constants\FileType;
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
        $this->addOption('enable-domain-model', null, InputOption::VALUE_OPTIONAL, '是否支持DDD架构');
        $this->addOption('enable-query-command', null, InputOption::VALUE_OPTIONAL, '是否支持读写分离');
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

        $enableDomain = $this->input->getOption('enable-domain-model') != null;
        if (! $enableDomain) {
            isset($pluginDirFiles[FileType::DOMAIN]) && $pluginDirFiles[FileType::DOMAIN]['generate'] = false;
            isset($pluginDirFiles[FileType::DOMAIN_MODEL]) && $pluginDirFiles[FileType::DOMAIN_MODEL]['generate'] = false;
            isset($pluginDirFiles[FileType::DOMAIN_SERVICE]) && $pluginDirFiles[FileType::DOMAIN_SERVICE]['generate'] = false;
            isset($pluginDirFiles[FileType::DOMAIN_REPOSITORY]) && $pluginDirFiles[FileType::DOMAIN_REPOSITORY]['generate'] = false;
        }
        $enableQueryCommand = $this->input->getOption('enable-query-command') != null;
        if (! $enableQueryCommand) {
            isset($pluginDirFiles[FileType::APP_EXECUTOR_COMMAND]) && $pluginDirFiles[FileType::APP_EXECUTOR_COMMAND]['generate'] = false;
            isset($pluginDirFiles[FileType::APP_EXECUTOR_COMMAND_ADMIN]) && $pluginDirFiles[FileType::APP_EXECUTOR_COMMAND_ADMIN]['generate'] = false;
            isset($pluginDirFiles[FileType::APP_EXECUTOR_COMMAND_MOBILE]) && $pluginDirFiles[FileType::APP_EXECUTOR_COMMAND_MOBILE]['generate'] = false;
            isset($pluginDirFiles[FileType::APP_EXECUTOR_COMMAND_WAP]) && $pluginDirFiles[FileType::APP_EXECUTOR_COMMAND_WAP]['generate'] = false;
            isset($pluginDirFiles[FileType::APP_EXECUTOR_COMMAND_WEB]) && $pluginDirFiles[FileType::APP_EXECUTOR_COMMAND_WEB]['generate'] = false;
            isset($pluginDirFiles[FileType::CLIENT_DTO_COMMAND]) && $pluginDirFiles[FileType::CLIENT_DTO_COMMAND]['generate'] = false;

            isset($pluginDirFiles[FileType::APP_EXECUTOR_QUERY]) && $pluginDirFiles[FileType::APP_EXECUTOR_QUERY]['generate'] = false;
            isset($pluginDirFiles[FileType::APP_EXECUTOR_QUERY_ADMIN]) && $pluginDirFiles[FileType::APP_EXECUTOR_QUERY_ADMIN]['generate'] = false;
            isset($pluginDirFiles[FileType::APP_EXECUTOR_QUERY_MOBILE]) && $pluginDirFiles[FileType::APP_EXECUTOR_QUERY_MOBILE]['generate'] = false;
            isset($pluginDirFiles[FileType::APP_EXECUTOR_QUERY_WAP]) && $pluginDirFiles[FileType::APP_EXECUTOR_QUERY_WAP]['generate'] = false;
            isset($pluginDirFiles[FileType::APP_EXECUTOR_QUERY_WEB]) && $pluginDirFiles[FileType::APP_EXECUTOR_QUERY_WEB]['generate'] = false;
            isset($pluginDirFiles[FileType::CLIENT_DTO_QUERY]) && $pluginDirFiles[FileType::CLIENT_DTO_QUERY]['generate'] = false;
            isset($pluginDirFiles[FileType::QUERY]) && $pluginDirFiles[FileType::QUERY]['generate'] = false;

            isset($pluginDirFiles[FileType::APP_EXECUTOR_ADMIN]) && $pluginDirFiles[FileType::APP_EXECUTOR_ADMIN]['generate'] = true;
            isset($pluginDirFiles[FileType::APP_EXECUTOR_MOBILE]) && $pluginDirFiles[FileType::APP_EXECUTOR_MOBILE]['generate'] = true;
            isset($pluginDirFiles[FileType::APP_EXECUTOR_WAP]) && $pluginDirFiles[FileType::APP_EXECUTOR_WAP]['generate'] = true;
            isset($pluginDirFiles[FileType::APP_EXECUTOR_WEB]) && $pluginDirFiles[FileType::APP_EXECUTOR_WEB]['generate'] = true;
        }

        $this->makeDirectoryFiles($pluginName, $pluginBasePath, $pluginDirFiles, $enableDomain, $enableQueryCommand);
        $this->generateComposerFileAndConfigProvider($pluginName);
        $this->info(sprintf('成功创建插件：%s', $pluginName));
    }

    protected function formatPluginName(string $name): string
    {
        return str_replace('_', '-', Str::kebab($name));
    }

    private function makeDirectoryFiles(string $pluginName, string $pluginBasePath, array $pluginDirFiles, bool $enableDomain, bool $enableQueryCommand)
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
