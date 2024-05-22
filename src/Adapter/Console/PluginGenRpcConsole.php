<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Contract\ContainerInterface;
use Hyperf\Stringable\Str;
use MaliBoot\PluginCodeGenerator\Client\Constants\FileType;
use MaliBoot\Utils\CodeGen\Plugin;
use MaliBoot\Utils\File;
use Symfony\Component\Console\Input\InputOption;

class PluginGenRpcConsole extends AbstractCodeGenConsole
{
    protected ?string $pluginName;

    protected ?string $table;

    protected ?string $cnName;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, 'plugin:gen-rpc');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Create a new plugin rpc service');
        $this->defaultConfigure();
        $this->addOption('cn-name', null, InputOption::VALUE_OPTIONAL, '中文业务名称');
        $this->addOption('platform', null, InputOption::VALUE_OPTIONAL, '平台', '');
        $this->addOption('enable-query-command', null, InputOption::VALUE_OPTIONAL, '是否支持读写分离架构', 'false');
    }

    public function handle()
    {
        $this->pluginName = $this->getPluginName();
        $this->table = $this->input->getArgument('table');
        $this->cnName = $this->input->getOption('cn-name');
        $this->platform = $this->input->getOption('platform');
        $className = $this->input->getOption('class');
        $this->businessName = $this->getBusinessName();
        $this->enableCmdQry = $this->input->getOption('enable-query-command') === 'true';
        $option = $this->initOption();

        $this->generator($this->pluginName, $this->table, $option, $className);
    }

    protected function getStub(): string
    {
        return File::get(__DIR__ . '/stubs/rpc.stub');
    }

    protected function getInheritance(): string
    {
        return 'AbstractRpcService';
    }

    protected function getInterface(string $table, ?string $shortClassName = null): string
    {
        return $this->getStudlyName($table) . 'Service';
    }

    protected function getUses(): array
    {
        $uses = [
            'MaliBoot\\Di\\Annotation\\Inject',
            'MaliBoot\\Cola\\Adapter\\AbstractRpcService',
            'MaliBoot\\Cola\\Annotation\\API',
            'MaliBoot\\Cola\\Annotation\\Method',
            'MaliBoot\\Dto\\IdVO',
            'MaliBoot\\Dto\\PageVO',
            'MaliBoot\\Dto\\MultiVO',
            'MaliBoot\\Dto\\EmptyVO',
        ];

        $this->addVOUses($uses)->addCmdUses($uses)->addExecutorUses($uses)->addInterfaceUses($uses);
        return $uses;
    }

    protected function addVOUses(array &$uses): static
    {
        $namespace = $this->getNamespaceByPath($this->getPath(FileType::CLIENT_VIEW_OBJECT));
        $uses[] = sprintf('%s%sVO', $namespace, $this->businessName);

        return $this;
    }

    protected function addInterfaceUses(array &$uses): static
    {
        $plugin = new Plugin($this->pluginName);
        $className = Str::studly(Str::singular($this->table));
        $uses[] = $plugin->namespace($this->getInterfacePath()) . $className . 'Service';

        return $this;
    }

    protected function getInterfacePath(): string
    {
        $pluginConfig = $this->config->get('plugin', []);
        $fullPath = sprintf(
            '%s/%s/%s',
            \Hyperf\Collection\data_get($pluginConfig, 'paths.base_path', 'plugin'),
            $this->getPluginName(),
            \Hyperf\Collection\data_get($pluginConfig, 'paths.generator.' . FileType::CLIENT_API . '.path')
        );

        return str_replace(BASE_PATH . '/', '', $fullPath);
    }

    protected function getFileType(): string
    {
        return FileType::ADAPTER_RPC;
    }

    protected function getClassSuffix(): string
    {
        return 'RpcService';
    }
}
