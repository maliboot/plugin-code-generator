<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Collection\Arr;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Stringable\Str;
use MaliBoot\PluginCodeGenerator\Client\Constants\FileType;
use MaliBoot\Utils\File;
use Symfony\Component\Console\Input\InputOption;

class PluginGenExecutorConsole extends AbstractCodeGenConsole
{
    protected ?string $pluginName;

    protected ?string $table;

    protected ?string $method;

    protected ?string $platform;

    protected ?string $exeType;

    protected ?string $vo;

    private bool $enableDomain = false;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, 'plugin:gen-executor');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Create a new plugin executor');
        $this->defaultConfigure();
        $this->addOption('method', null, InputOption::VALUE_OPTIONAL, '方法名称', 'curd');
        $this->addOption('platform', null, InputOption::VALUE_OPTIONAL, '平台', 'admin');
        $this->addOption('exe-type', null, InputOption::VALUE_OPTIONAL, 'exe类型', 'query');
        $this->addOption('empty', null, InputOption::VALUE_OPTIONAL, '是否为空');
        $this->addOption('vo', null, InputOption::VALUE_OPTIONAL, '指定 view object');
        $this->addOption('enable-query-command', null, InputOption::VALUE_OPTIONAL, '是否支持读写分离架构', 'false');
        $this->addOption('enable-domain-model', null, InputOption::VALUE_OPTIONAL, '是否支持DDD架构', 'false');
    }

    public function handle()
    {
        $this->pluginName = $this->getPluginName();
        $this->table = $this->input->getArgument('table');
        $this->platform = $this->input->getOption('platform');
        $this->exeType = $this->input->getOption('exe-type');
        $this->method = $this->input->getOption('method');
        $this->vo = $this->input->getOption('vo');
        $className = $this->input->getOption('class');
        $this->businessName = $this->getBusinessName();
        $this->enableCmdQry = $this->input->getOption('enable-query-command') === 'true';
        $this->enableDomain = $this->input->getOption('enable-domain-model') === 'true';

        if ($this->method !== 'curd') {
            $option = $this->initOption();
            $this->generator($this->pluginName, $this->table, $option, $className);
            return true;
        }

        $this->generatorCurd($this->pluginName, $this->table, $className);
        return true;
    }

    protected function generatorCurd(string $pluginName, string $table, ?string $className = null)
    {
        $curds = ['listByPage', 'create', 'update', 'delete', 'getById'];
        foreach ($curds as $curd) {
            $this->method = $curd;
            if (in_array($curd, ['listByPage', 'getById'])) {
                $this->exeType = 'query';
            } else {
                $this->exeType = 'command';
            }
            $option = $this->initOption();
            $this->generator($pluginName, $table, $option, $className);
        }
    }

    protected function getStub(): string
    {
        if ($this->input->getOption('empty')) {
            return File::get(__DIR__ . '/stubs/executor-empty.stub');
        }

        return File::get(__DIR__ . '/stubs/executor.stub');
    }

    protected function getInheritance(): string
    {
        return 'AbstractExecutor';
    }

    protected function getUses(): array
    {
        $uses = [
            'MaliBoot\\Di\\Annotation\\Inject',
            'MaliBoot\\Cola\\App\\AbstractExecutor',
            'MaliBoot\\Cola\\Annotation\\AppService',
        ];

        $this->addCommandUses($uses)->addVOUses($uses);

        if (! $this->input->getOption('empty')) {
            $this->addModelUses($uses)
                ->addRepositoryUses($uses);
        }

        return $uses;
    }

    protected function addModelUses(array &$uses): static
    {
        if (! $this->enableDomain) {
            return $this;
        }

        // delete不引用model
        if (($this->exeType === 'command' && $this->method !== 'delete') || ($this->exeType === 'query' && $this->method === 'getById')) {
            $namespace = $this->getNamespaceByPath($this->getPath(FileType::DOMAIN_MODEL) . '/' . $this->businessName);
            $uses[] = sprintf('%s%s', $namespace, $this->businessName);
        }

        return $this;
    }

    protected function addCommandUses(array &$uses): static
    {
        if ($this->exeType === 'query') {
            $fileType = FileType::CLIENT_DTO_QUERY;
        } else {
            $fileType = FileType::CLIENT_DTO_COMMAND;
        }

        if (! $this->enableCmdQry) {
            $fileType = FileType::CLIENT_DTO;
        }

        if (! in_array($this->method, ['getById', 'delete'])) {
            $namespace = $this->getNamespaceByPath($this->getPath($fileType));
            $uses[] = sprintf('%s%s', $namespace, Str::studly($this->getCommandName()));
        }

        return $this;
    }

    protected function addVOUses(array &$uses): static
    {
        if (! empty($this->vo)) {
            $uses[] = $this->vo;
        } elseif ($this->method === 'create') {
            $uses[] = 'MaliBoot\\Dto\\IdVO';
        } elseif ($this->method === 'listByPage') {
            $uses[] = 'MaliBoot\\Dto\\PageVO';
        } elseif ($this->method === 'getById') {
            $namespace = $this->getNamespaceByPath($this->getPath(FileType::CLIENT_VIEW_OBJECT));
            $uses[] = sprintf('%s%sVO', $namespace, $this->businessName);
        } else {
            $uses[] = 'MaliBoot\\Dto\\EmptyVO';
        }

        return $this;
    }

    protected function addRepositoryUses(array &$uses): static
    {
        $infraRepoNamespace = $this->getNamespaceByPath($this->getPath(FileType::INFRA_REPOSITORY));
        $domainRepoNamespace = $this->getNamespaceByPath($this->getPath(FileType::DOMAIN_REPOSITORY));
        if ($this->exeType === 'query') {
            if (! $this->enableCmdQry) {
                $uses[] = sprintf('%s%sRepo', $infraRepoNamespace, $this->businessName);
            } else {
                $uses[] = sprintf('%s%sQryRepo', $infraRepoNamespace, $this->businessName);
            }
        } else {
            if ($this->enableDomain) {
                $namespace = $domainRepoNamespace;
            } else {
                $namespace = $infraRepoNamespace;
            }
            $uses[] = sprintf('%s%sRepo', $namespace, $this->businessName);
        }

        return $this;
    }

    protected function getFileType(): string
    {
        $fileTypes = [
            'admin' => FileType::APP_EXECUTOR_ADMIN,
            'mobile' => FileType::APP_EXECUTOR_MOBILE,
            'wap' => FileType::APP_EXECUTOR_WAP,
            'web' => FileType::APP_EXECUTOR_WEB,
            'command.admin' => FileType::APP_EXECUTOR_COMMAND_ADMIN,
            'command.mobile' => FileType::APP_EXECUTOR_COMMAND_MOBILE,
            'command.wap' => FileType::APP_EXECUTOR_COMMAND_WAP,
            'command.web' => FileType::APP_EXECUTOR_COMMAND_WEB,
            'query.admin' => FileType::APP_EXECUTOR_QUERY_ADMIN,
            'query.mobile' => FileType::APP_EXECUTOR_QUERY_MOBILE,
            'query.wap' => FileType::APP_EXECUTOR_QUERY_WAP,
            'query.web' => FileType::APP_EXECUTOR_QUERY_WEB,
        ];

        if ($this->platform) {
            if ($this->enableCmdQry) {
                return $fileTypes[$this->exeType . '.' . $this->platform];
            }
            return $fileTypes[$this->platform];
        }

        if (! $this->enableCmdQry) {
            return FileType::APP_EXECUTOR;
        }

        if ($this->exeType === 'query') {
            return FileType::APP_EXECUTOR_QUERY;
        }
        return FileType::APP_EXECUTOR_COMMAND;
    }

    protected function getClassSuffix(): string
    {
        $suffix = '';
        if (! empty($this->table)) {
            $suffix = Str::studly($this->method);
        }

        $shortExeType = 'Qry';
        if ($this->exeType === 'command') {
            $shortExeType = 'Cmd';
        }

        if (! $this->enableCmdQry) {
            $shortExeType = 'DTO';
        }
        return sprintf('%s%sExe', $suffix, $shortExeType);
    }

    protected function buildClass(string $pluginName, string $table, string $className, CodeGenOption $option, array $fields = []): string
    {
        $stub = parent::buildClass($pluginName, $table, $className, $option, $fields);
        $this->replaceReturnType($stub, $className)
            ->replaceStudlyCommandName($stub)
            ->replaceCamelCommandName($stub)
            ->replaceReturnResult($stub)
            ->replaceRepositoryParamsLine($stub)
            ->replaceRepositoryParams($stub)
            ->replaceRepositoryMethod($stub)
            ->replaceStudlyRepository($stub)
            ->replaceCamelRepository($stub);

        return $stub;
    }

    protected function replaceReturnType(string &$stub, string $className): static
    {
        $stub = str_replace(
            ['%RETURN_TYPE%'],
            [$this->getReturnType()],
            $stub
        );

        return $this;
    }

    protected function getReturnType(): string
    {
        if (! empty($this->vo)) {
            $vo = explode('\\', $this->vo);
            return Arr::last($vo);
        }

        switch ($this->method) {
            case 'listByPage':
                $returnType = 'PageVO';
                break;
            case 'getById':
                $returnType = '?' . $this->businessName . 'VO';
                break;
            case 'create':
                $returnType = 'IdVO';
                break;
            case 'update':
            case 'delete':
                $returnType = 'EmptyVO';
                break;
            default:
                $returnType = 'EmptyVO';
                break;
        }

        return $returnType;
    }

    protected function replaceStudlyCommandName(string &$stub): static
    {
        switch ($this->method) {
            case 'delete':
            case 'getById':
                $studlyCommandName = 'int';
                break;
            default:
                $studlyCommandName = Str::studly($this->getCommandName());
                break;
        }

        $stub = str_replace(
            ['%STUDLY_COMMAND_NAME%'],
            [$studlyCommandName],
            $stub
        );

        return $this;
    }

    protected function replaceCamelCommandName(string &$stub): static
    {
        switch ($this->method) {
            case 'delete':
            case 'getById':
                $camelCommandName = 'id';
                break;
            default:
                $camelCommandName = Str::camel($this->getCommandName());
                break;
        }
        $stub = str_replace(
            ['%CAMEL_COMMAND_NAME%'],
            [$camelCommandName],
            $stub
        );

        return $this;
    }

    protected function getCommandName(): string
    {
        $commandName = $this->businessName;

        if (! Str::contains($commandName, $this->method)) {
            $commandName .= Str::studly($this->method);
        }

        if (! $this->enableCmdQry) {
            return $commandName . 'DTO';
        }

        if ($this->exeType === 'command') {
            $commandName .= 'Cmd';
        } else {
            $commandName .= 'Qry';
        }

        return $commandName;
    }

    protected function replaceReturnResult(string &$stub): static
    {
        $stub = str_replace(
            ['%RETURN_RESULT%'],
            [$this->getReturnResult()],
            $stub
        );

        return $this;
    }

    protected function getReturnResult(): string
    {
        $businessName = $this->businessName;
        switch ($this->method) {
            case 'getById':
                $result = sprintf('return %sVO::ofDO($result);', $businessName);
                break;
            case 'create':
                $result = 'return (new IdVO())->setId($result);';
                break;
            case 'delete':
            case 'update':
                $result = 'return new EmptyVO();';
                break;
            default:
                $result = 'return $result;';
                break;
        }

        return $result;
    }

    protected function replaceRepositoryParamsLine(string &$stub): static
    {
        $stub = str_replace(
            ['%REPOSITORY_PARAMS_LINE%'],
            [$this->getRepositoryParamsLine()],
            $stub
        );

        return $this;
    }

    protected function getRepositoryParamsLine(): string
    {
        $businessName = $this->businessName;
        $commandName = Str::camel($this->getCommandName());
        switch ($this->method) {
            case 'listByPage':
                $repositoryParams = sprintf('$params = $%s; // do something...', $commandName);
                break;
            case 'update':
            case 'create':
                if ($this->enableDomain) {
                    $repositoryParams = sprintf('$params = %s::of($%s->toArray());', $businessName, $commandName);
                } else {
                    $repositoryParams = sprintf('$params = $%s->toArray();', $commandName);
                }
                break;
            default:
                $repositoryParams = '';
                break;
        }

        return $repositoryParams;
    }

    protected function replaceRepositoryParams(string &$stub): static
    {
        $stub = str_replace(
            ['%REPOSITORY_PARAMS%'],
            [$this->getRepositoryParams()],
            $stub
        );

        return $this;
    }

    protected function getRepositoryParams(): string
    {
        switch ($this->method) {
            case 'delete':
            case 'getById':
                $repositoryParams = '$id';
                break;
            default:
                $repositoryParams = '$params';
                break;
        }

        return $repositoryParams;
    }

    protected function replaceRepositoryMethod(string &$stub): static
    {
        $stub = str_replace(
            ['%REPOSITORY_METHOD%'],
            [$this->getRepositoryMethod()],
            $stub
        );

        return $this;
    }

    protected function getRepositoryMethod(): string
    {
        return $this->method;
    }

    protected function replaceStudlyRepository(string &$stub): static
    {
        $stub = str_replace(
            ['%STUDLY_REPOSITORY%'],
            [Str::studly($this->getRepository())],
            $stub
        );

        return $this;
    }

    protected function getRepository(): string
    {
        if (! $this->enableCmdQry) {
            return sprintf('%sRepo', $this->businessName);
        }

        if ($this->exeType === 'query') {
            $repository = sprintf('%sQryRepo', $this->businessName);
        } else {
            $repository = sprintf('%sRepo', $this->businessName);
        }

        return $repository;
    }

    protected function replaceCamelRepository(string &$stub): static
    {
        $stub = str_replace(
            ['%CAMEL_REPOSITORY%'],
            [Str::camel($this->getRepository())],
            $stub
        );

        return $this;
    }
}
