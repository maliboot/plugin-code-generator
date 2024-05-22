<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Contract\ContainerInterface;
use Hyperf\Stringable\Str;
use MaliBoot\ApiAnnotation\ApiMultiResponse;
use MaliBoot\ApiAnnotation\ApiPageResponse;
use MaliBoot\ApiAnnotation\ApiSingleResponse;
use MaliBoot\PluginCodeGenerator\Adapter\Console\Ast\ControllerAst;
use MaliBoot\PluginCodeGenerator\Client\Constants\FileType;
use MaliBoot\Utils\File;
use Symfony\Component\Console\Input\InputOption;

class PluginGenControllerConsole extends AbstractCodeGenConsole
{
    protected ?string $pluginName;

    protected ?string $table;

    protected ?string $cnName;

    protected ?string $platform;

    protected bool $addMethod = false;

    protected string $apiUrl;

    protected string $apiHttpMethod;

    protected string $apiName;

    protected string $apiResponseType;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, 'plugin:gen-controller');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Create a new plugin controller');
        $this->defaultConfigure();
        $this->addOption('cn-name', null, InputOption::VALUE_OPTIONAL, '中文业务名称');
        $this->addOption('platform', null, InputOption::VALUE_OPTIONAL, '平台', 'admin');
        $this->addOption('empty', null, InputOption::VALUE_OPTIONAL, '是否为空');

        $this->addOption('add-method', null, InputOption::VALUE_OPTIONAL, '添加方法');
        $this->addOption('api-url', null, InputOption::VALUE_OPTIONAL, '接口路径');
        $this->addOption('api-http-method', null, InputOption::VALUE_OPTIONAL, '接口 http 方法');
        $this->addOption('api-name', null, InputOption::VALUE_OPTIONAL, '接口名称');
        $this->addOption('api-response-type', null, InputOption::VALUE_OPTIONAL, '响应类型');
        $this->addOption('enable-query-command', null, InputOption::VALUE_OPTIONAL, '是否支持读写分离架构', 'false');
    }

    public function handle()
    {
        $this->pluginName = $this->getPluginName();
        $this->table = $this->input->getArgument('table');
        $this->cnName = $this->input->getOption('cn-name');
        $this->platform = $this->input->getOption('platform');

        $this->addMethod = (bool) $this->input->getOption('add-method');
        $this->apiUrl = (string) $this->input->getOption('api-url');
        $this->apiHttpMethod = (string) $this->input->getOption('api-http-method');
        $this->apiName = (string) $this->input->getOption('api-name');
        $this->apiResponseType = (string) $this->input->getOption('api-response-type');
        $this->enableCmdQry = $this->input->getOption('enable-query-command') === 'true';

        $className = $this->input->getOption('class');
        $this->businessName = $this->getBusinessName();
        $option = $this->initOption();

        $this->generator($this->pluginName, $this->table, $option, $className);
    }

    /**
     * 生成代码
     */
    public function generator(string $pluginName, string $table, CodeGenOption $option, ?string $className = null, array $fields = []): void
    {
        $className = $this->getClassName($pluginName, $table, $option, $className);
        $generatorFilePath = $this->getClassPath($pluginName, $className);

        $force = $fix = false;
        if (! $this->addMethod) {
            $code = $this->buildClass($pluginName, $table, $className, $option, $fields);
        } else {
            $code = File::get($generatorFilePath);
            $ast = new ControllerAst();
            $stmts = $ast->parse($code);
            $code = $ast->addMethod($className, $stmts, $this->getMethodDataParams());
            $force = $fix = true;
        }

        $this->generatorFile($generatorFilePath, $code, $force);
        $this->clearGitignore($generatorFilePath);

        if ($fix) {
            $this->fixFile($generatorFilePath);
        }
    }

    protected function buildClass(string $pluginName, string $table, string $className, CodeGenOption $option, array $fields = []): string
    {
        $stub = parent::buildClass($pluginName, $table, $className, $option, $fields);
        $this->replacePlatform($stub, $className);

        return $stub;
    }

    protected function replacePlatform(string &$stub, string $className): static
    {
        $stub = str_replace(
            ['%PLATFORM%'],
            [$this->getPlatform()],
            $stub
        );

        return $this;
    }

    protected function getPlatform(): string
    {
        return trim($this->platform);
    }

    protected function getStub(): string
    {
        if ($this->input->getOption('empty')) {
            return File::get(__DIR__ . '/stubs/controller-empty.stub');
        }

        return File::get(__DIR__ . '/stubs/controller.stub');
    }

    protected function getInheritance(): string
    {
        return 'AbstractController';
    }

    protected function getUses(): array
    {
        $uses = [
            'MaliBoot\\Di\\Annotation\\Inject',
            'MaliBoot\\Cola\\Adapter\\AbstractController',
            'MaliBoot\\Dto\\IdVO',
            'MaliBoot\\Dto\\PageVO',
            'MaliBoot\\Dto\\MultiVO',
            'MaliBoot\\Dto\\EmptyVO',
            'MaliBoot\\ApiAnnotation\\ApiGroup',
            'MaliBoot\\ApiAnnotation\\ApiVersion',
            'MaliBoot\\ApiAnnotation\\ApiController',
            'MaliBoot\\ApiAnnotation\\ApiMapping',
            'MaliBoot\\ApiAnnotation\\ApiRequest',
            'MaliBoot\\ApiAnnotation\\ApiQuery',
            'MaliBoot\\ApiAnnotation\\ApiSingleResponse',
            'MaliBoot\\ApiAnnotation\\ApiMultiResponse',
            'MaliBoot\\ApiAnnotation\\ApiPageResponse',
            'MaliBoot\\Auth\\Annotation\\Auth',
        ];

        if (! $this->input->getOption('empty')) {
            $this->addVOUses($uses)->addCmdUses($uses)->addExecutorUses($uses);
        }

        return $uses;
    }

    protected function addVOUses(array &$uses): static
    {
        $namespace = $this->getNamespaceByPath($this->getPath(FileType::CLIENT_VIEW_OBJECT));
        $uses[] = sprintf('%s%sVO', $namespace, $this->businessName);

        return $this;
    }

    protected function addExecutorUses(array &$uses): static
    {
        $curds = ['ListByPageQry', 'CreateCmd', 'UpdateCmd', 'DeleteCmd', 'GetByIdQry'];
        $studlyName = $this->getStudlyName($this->table);

        foreach ($curds as $curd) {
            if (in_array($curd, ['ListByPageQry', 'GetByIdQry'])) {
                $fileType = FileType::APP_EXECUTOR_QUERY;
            } else {
                $fileType = FileType::APP_EXECUTOR_COMMAND;
            }

            if ($this->platform === 'admin') {
                $fileType .= '_' . Str::lower($this->platform);
            }

            if (! $this->enableCmdQry) {
                $fileType = FileType::CLIENT_DTO;
                $curd = str_replace(['Qry', 'Cmd'], ['DTO', 'DTO'], $curd);
            }

            $namespace = $this->getNamespaceByPath($this->getPath($fileType));
            $uses[] = sprintf('%s%s%sExe', $namespace, $studlyName, $curd);
        }

        return $this;
    }

    protected function getFileType(): string
    {
        switch ($this->platform) {
            case 'web':
                $fileType = FileType::ADAPTER_WEB;
                break;
            case 'wap':
                $fileType = FileType::ADAPTER_WAP;
                break;
            case 'mobile':
                $fileType = FileType::ADAPTER_MOBILE;
                break;
            case 'admin':
                $fileType = FileType::ADAPTER_ADMIN;
                break;
            case 'pda':
                $fileType = FileType::ADAPTER_PDA;
                break;
            default:
                $fileType = FileType::ADAPTER_WEB;
                break;
        }
        return $fileType;
    }

    protected function getClassSuffix(): string
    {
        return 'Controller';
    }

    private function getMethodDataParams(): array
    {
        $pathArr = explode('/', trim($this->apiUrl, '/'));
        $platform = Str::lower($pathArr[0]);
        $businessName = Str::studly($pathArr[1] . '_' . $pathArr[2]);
        $methodName = Str::camel($pathArr[1] . '_' . $pathArr[2]);

        $executorName = $this->getFullClassName(
            $businessName,
            $this->getExecutorFileTypeByHttpMethod($this->apiHttpMethod),
            'Exe'
        );

        $commandName = $this->getFullClassName(
            $businessName,
            $this->getCommandFileTypeByHttpMethod($this->apiHttpMethod),
            ''
        );

        $viewObject = $this->getFullClassName(
            $businessName,
            FileType::CLIENT_VIEW_OBJECT,
            'VO'
        );

        return [
            $executorName,
            $platform,
            $this->apiUrl,
            [$this->apiHttpMethod],
            $this->apiName,
            $methodName,
            $commandName,
            $viewObject,
            $this->getResponseType($this->apiResponseType),
        ];
    }

    private function getExecutorFileTypeByHttpMethod(string $httpMethod): string
    {
        if (in_array(Str::lower($httpMethod), ['get'])) {
            return FileType::APP_EXECUTOR_QUERY;
        }

        return FileType::APP_EXECUTOR_COMMAND;
    }

    private function getCommandFileTypeByHttpMethod(string $httpMethod): string
    {
        if (in_array(Str::lower($httpMethod), ['get'])) {
            return FileType::CLIENT_DTO_QUERY;
        }

        return FileType::CLIENT_DTO_COMMAND;
    }

    private function getFullClassName(string $businessName, string $fileType, string $suffix): string
    {
        $namespace = $this->getNamespaceByPath($this->getPath($fileType));
        return sprintf('%s%s%s%s', $namespace, Str::studly($businessName), Str::studly($this->getShortCommandType($fileType)), $suffix);
    }

    private function getShortCommandType(string $commandType): string
    {
        if ($commandType === FileType::CLIENT_VIEW_OBJECT) {
            return '';
        }
        if ($commandType === FileType::APP_EXECUTOR_COMMAND || $commandType === FileType::CLIENT_DTO_COMMAND) {
            return 'cmd';
        }
        return 'qry';
    }

    private function getResponseType(string $responseType): string
    {
        switch ($responseType) {
            case 'multi':
                return ApiMultiResponse::class;
            case 'page':
                return ApiPageResponse::class;
            default:
                return ApiSingleResponse::class;
        }
    }
}
