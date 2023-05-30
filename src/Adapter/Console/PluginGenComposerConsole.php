<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Str;
use MaliBoot\PluginCodeGenerator\Client\Constants\FileType;
use MaliBoot\Utils\File;
use MaliBoot\Utils\Process\Process;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class PluginGenComposerConsole extends AbstractCodeGenConsole
{
    protected array $pluginConfig = [];

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, 'plugin:gen-composer');
        $this->pluginConfig = $container->get(ConfigInterface::class)->get('plugin', []);
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Create a new plugin composer.json');
        $this->addArgument('plugin', InputArgument::REQUIRED, '插件名称');
        $this->addOption('force', null, InputOption::VALUE_OPTIONAL, '是否强制覆盖', false);
    }

    public function handle()
    {
        $pluginName = $this->getPluginName();

        $this->generatorComposer($pluginName);
        $this->composerInstallPlugin($pluginName);
    }

    protected function generatorComposer(string $pluginName): void
    {
        $generatorFilePath = $this->getPathPrefix($pluginName) . '/composer.json';
        $codeContent = $this->buildComposerClass($pluginName);
        $this->generatorFile($generatorFilePath, $codeContent);
        $this->clearGitignore($generatorFilePath);
    }

    protected function getStub(): string
    {
        return File::get(__DIR__ . '/stubs/composer.stub');
    }

    /**
     * 使用给定类名称生成类.
     */
    protected function buildComposerClass(string $pluginName): string
    {
        $stub = $this->getStub();

        $this->replaceCopyright($stub)
            ->replaceUsername($stub)
            ->replacePluginName($stub)
            ->replacePluginNamespace($stub)
            ->replacePluginTestNamespace($stub);

        return $stub;
    }

    protected function replaceUsername(string &$stub): static
    {
        $stub = str_replace(
            ['%USERNAME%'],
            [$this->getUsername()],
            $stub
        );

        return $this;
    }

    protected function getUsername(): string
    {
        return Arr::get($this->pluginConfig, 'composer.username');
    }

    protected function replacePluginName(string &$stub): static
    {
        $stub = str_replace(
            ['%PLUGIN_NAME%'],
            [$this->getPluginName()],
            $stub
        );

        return $this;
    }

    protected function replacePluginNamespace(string &$stub): static
    {
        $stub = str_replace(
            ['%PLUGIN_NAMESPACE%'],
            [$this->getPluginNamespace()],
            $stub
        );

        return $this;
    }

    protected function getPluginNamespace(): string
    {
        return sprintf(
            '%s\\\\%s\\\\',
            Arr::get($this->pluginConfig, 'composer.namespace_prefix'),
            Str::studly($this->getPluginName())
        );
    }

    protected function replacePluginTestNamespace(string &$stub): static
    {
        $stub = str_replace(
            ['%PLUGIN_TEST_NAMESPACE%'],
            [$this->getPluginTestNamespace()],
            $stub
        );

        return $this;
    }

    protected function getPluginTestNamespace(): string
    {
        return sprintf(
            '%sTest\\\\%s\\\\',
            Arr::get($this->pluginConfig, 'composer.namespace_prefix'),
            Str::studly($this->getPluginName())
        );
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
        return FileType::ROOT;
    }

    protected function getClassSuffix(): string
    {
        return '';
    }

    private function composerInstallPlugin(string $pluginName): void
    {
        $process = Process::run(sprintf('composer require %s/%s:"*"', $this->config->get('plugin.composer.username'), $pluginName));

        if (! $process->isSuccessful()) {
            $this->error('Failed to install plugin, error: ' . $process->getErrorOutput());
            return;
        }

        $this->info($process->getOutput());
    }
}
