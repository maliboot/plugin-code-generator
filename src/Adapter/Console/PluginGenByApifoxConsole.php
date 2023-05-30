<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use GuzzleHttp\Client;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Guzzle\HandlerStackFactory;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Codec\Json;
use Hyperf\Utils\Str;
use MaliBoot\PluginCodeGenerator\Adapter\Console\Traits\CodeGenEmptyImplTrait;
use MaliBoot\PluginCodeGenerator\Client\Constants\FileType;
use MaliBoot\Utils\File;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class PluginGenByApifoxConsole extends AbstractCodeGenConsole
{
    use CodeGenEmptyImplTrait;

    protected const COMMAND_TYPE_COMMAND = 'command';

    protected const COMMAND_TYPE_QUERY = 'query';

    protected array $queryHttpMethods = ['get', 'head', 'option'];

    protected string $plugin;

    protected bool $force = false;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, 'plugin:gen-by-apifox');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Create a new plugin api by apifox');
        $this->addArgument('plugin', InputArgument::REQUIRED, '插件名称');
        $this->addArgument('swagger-url', InputArgument::REQUIRED, 'apifox swagger json url');
        $this->addOption('url', null, InputOption::VALUE_OPTIONAL, '接口 url ');
        $this->addOption('force', null, InputOption::VALUE_OPTIONAL, '是否强制覆盖', false);
    }

    public function handle()
    {
        $swaggerUrl = $this->input->getArgument('swagger-url');
        $url = $this->input->getOption('url');
        $this->force = (bool) $this->input->getOption('force');
        $swaggerJson = $this->getOpenapiJson($swaggerUrl);

        $paths = Arr::get($swaggerJson, 'paths');
        if (empty($paths)) {
            $this->error(sprintf('swagger json 文件中的 path 为空'));
            return;
        }

        // 生成所有的 url
        if (empty($url)) {
            foreach ($paths as $path => $item) {
                $this->generatorUrl($path, $swaggerJson);
            }
            return;
        }

        if (! Arr::has($swaggerJson, 'paths.' . $url)) {
            $this->error(sprintf('不存在的url: %s', $url));
            return;
        }

        // 生成指定的url
        $this->generatorUrl($url, $swaggerJson);
    }

    protected function generatorUrl(string $url, array $swaggerJson): void
    {
        $httpMethods = Arr::get($swaggerJson, 'paths.' . $url);
        if (count($httpMethods) > 1) {
            foreach ($httpMethods as $httpMethod) {
                $this->generatorAll($httpMethod, $url, $swaggerJson);
            }
        } else {
            $this->generatorAll(Arr::first(array_keys($httpMethods)), $url, $swaggerJson);
        }
    }

    protected function getOpenapiJson(string $url): array
    {
        if (preg_match('/^(http:\\/\\/|https:\\/\\/).*$/', $url)) {
            $response = $this->getHttpClient()->get($url);
            if ($response->getStatusCode() !== 200) {
                throw new \InvalidArgumentException('获取json数据失败');
            }

            return Json::decode($response->getBody()->getContents());
        }

        $path = BASE_PATH . '/runtime/' . $url;
        if (! file_exists($path)) {
            throw new \InvalidArgumentException('文件不存在');
        }

        return Json::decode(file_get_contents($path));
    }

    protected function generatorAll(string $httpMethod, string $url, array $swaggerJson): void
    {
        $this->generatorDTO($httpMethod, $url, $swaggerJson);
        $this->generatorVO($httpMethod, $url, $swaggerJson);
        $this->generatorExecutor($httpMethod, $url, $swaggerJson);
        $this->generatorController($httpMethod, $url, $swaggerJson);
    }

    protected function generatorDTO(string $httpMethod, string $url, array $swaggerJson): void
    {
        $fields = $this->getDTOFieldsBySwaggerJson($httpMethod, $url, $swaggerJson);
        if (empty($fields)) {
            return;
        }

        $this->call('plugin:gen-command', [
            'plugin' => $this->getPluginName(),
            '--method' => $this->getMethodName($url),
            '--cmd-type' => $this->getCommandType($httpMethod),
            '--class' => $this->getClassNameByUrl($url),
            '--fields' => $fields,
            '--force' => $this->force,
        ]);
    }

    protected function generatorVO(string $httpMethod, string $url, array $swaggerJson): void
    {
        $fields = $this->getVOFieldsBySwaggerJson($httpMethod, $url, $swaggerJson);
        if (empty($fields)) {
            return;
        }

        $this->call('plugin:gen-vo', [
            'plugin' => $this->getPluginName(),
            '--class' => $this->getClassNameByUrl($url),
            '--fields' => $fields,
            '--force' => $this->force,
        ]);
    }

    protected function generatorExecutor(string $httpMethod, string $url, array $swaggerJson): void
    {
        $args = [
            'plugin' => $this->getPluginName(),
            '--method' => $this->getMethodName($url),
            '--platform' => $this->getPlatform($url),
            '--exe-type' => $this->getCommandType($httpMethod),
            '--empty' => true,
            '--class' => $this->getClassNameByUrl($url),
            '--force' => $this->force,
        ];

        if (count($this->getVOFieldsBySwaggerJson($httpMethod, $url, $swaggerJson)) > 0) {
            $args['--vo'] = $this->getVOClassName($this->getMethodName($url));
        }

        $this->call('plugin:gen-executor', $args);
    }

    protected function generatorController(string $httpMethod, string $url, array $swaggerJson): void
    {
        $pluginName = $this->getPluginName();
        $filePath = $this->getClassPath($pluginName, $this->getControllerClassName($url));

        $args = [
            'plugin' => $this->getPluginName(),
            '--platform' => $this->getPlatform($url),
            '--class' => $this->getShortControllerClassName($url),
            '--force' => $this->force,
        ];

        if (! File::exists($filePath)) {
            $newArgs = array_merge($args, [
                '--empty' => true,
            ]);
            $this->call('plugin:gen-controller', $newArgs);
        }

        $args = array_merge($args, [
            '--add-method' => true,
            '--api-url' => $url,
            '--api-http-method' => $httpMethod,
            '--api-name' => $this->getApiName($httpMethod, $url, $swaggerJson),
            '--api-response-type' => $this->getApiResponseType($httpMethod, $url, $swaggerJson),
        ]);

        $this->call('plugin:gen-controller', $args);
    }

    protected function getApiName(string $httpMethod, string $url, array $swaggerJson): string
    {
        return Arr::get($swaggerJson, sprintf('paths.%s.%s.summary', $url, $httpMethod));
    }

    protected function getVOClassName(string $businessName): string
    {
        $namespace = $this->getNamespaceByPath($this->getPath(FileType::CLIENT_VIEW_OBJECT));
        return sprintf('%s%sVO', $namespace, $businessName);
    }

    protected function getControllerClassName(string $url): string
    {
        $namespace = $this->getNamespaceByPath($this->getPath(FileType::ADAPTER . '_' . $this->getPlatform($url)));
        return sprintf('%s%sController', $namespace, $this->getShortControllerClassName($url));
    }

    protected function getShortControllerClassName(string $url): string
    {
        $urlList = explode('/', trim($url, '/'));
        return Str::studly($urlList[1]);
    }

    protected function getApiResponseType(string $httpMethod, string $url, array $swaggerJson): string
    {
        $schema = Arr::get($swaggerJson, sprintf('paths.%s.%s.responses.200.content.application/json.schema', $url, $httpMethod));
        $type = Arr::get($schema, 'type', 'object');
        if ($type === 'array') {
            return 'multi';
        }

        if (Arr::has($schema, 'properties.pageSize')) {
            return 'page';
        }

        return 'single';
    }

    protected function getDTOFieldsBySwaggerJson(string $httpMethod, string $url, array $swaggerJson): array
    {
        $fields = $properties = [];

        if ($httpMethod === 'post' || $httpMethod === 'put') {
            $properties = $this->getPostAndPutFields($httpMethod, $url, $swaggerJson);
        } elseif ($httpMethod === 'get' || $httpMethod === 'delete') {
            $properties = Arr::get($swaggerJson, sprintf('paths.%s.%s.parameters', $url, $httpMethod), []);
        }

        if (empty($properties)) {
            return $fields;
        }

        return $this->formatFields($httpMethod, $properties);
    }

    protected function getVOFieldsBySwaggerJson(string $httpMethod, string $url, array $swaggerJson): array
    {
        $fields = [];

        $properties = Arr::get($swaggerJson, sprintf('paths.%s.%s.responses.200.content.application/json.schema.properties', $url, $httpMethod), []);

        if (empty($properties)) {
            return $fields;
        }

        return $this->formatFields('post', $properties);
    }

    protected function getPostAndPutFields(string $httpMethod, string $url, array $swaggerJson): array
    {
        $applicationTypes = ['application/json', 'application/x-www-form-urlencoded', 'multipart/form-data'];

        foreach ($applicationTypes as $applicationType) {
            $properties = Arr::get($swaggerJson, sprintf('paths.%s.%s.requestBody.content.%s.schema.properties', $url, $httpMethod, $applicationType), []);
            if (! empty($properties)) {
                break;
            }
        }

        return $properties;
    }

    protected function formatFields(string $httpMethod, array $properties): array
    {
        $fields = [];
        if ($httpMethod === 'post') {
            foreach ($properties as $key => $value) {
                $fields[] = [
                    'name' => $key,
                    'type' => $this->formatFieldType($value['type']),
                    'desc' => $value['title'] ?? $value['description'] ?? '',
                ];
            }
        } elseif ($httpMethod === 'get') {
            foreach ($properties as $value) {
                $fields[] = [
                    'name' => $value['name'],
                    'type' => $this->formatFieldType($value['schema']['type']),
                    'desc' => $value['description'] ?? '',
                ];
            }
        }

        return $fields;
    }

    protected function formatFieldType(string $type): string
    {
        $newType = 'string';
        switch ($type) {
            case 'integer':
                $newType = 'int';
                break;
            case 'boolean':
                $newType = 'bool';
                break;
            case 'null':
            case 'any':
            case 'number':
                $newType = 'string';
                break;
            case 'object':
                throw new \Exception('暂不支持');
                break;
            default:
                $newType = $type;
                break;
        }

        return $newType;
    }

    protected function getPlatform(string $url): string
    {
        $urlList = explode('/', trim($url, '/'));
        return empty($urlList[0]) ? 'admin' : $urlList[0];
    }

    protected function getCommandType(string $httpMethod): string
    {
        if (in_array(Str::lower($httpMethod), $this->queryHttpMethods)) {
            return self::COMMAND_TYPE_QUERY;
        }

        return self::COMMAND_TYPE_COMMAND;
    }

    protected function getClassNameByUrl(string $url): string
    {
        return $this->getMethodName($url);
    }

    protected function getMethodName(string $url): string
    {
        $url = str_replace($this->getPlatform($url), '', trim($url, '/'));
        return Str::studly(str_replace('/', '_', trim($url, '/')));
    }

    private function getHttpClient(): Client
    {
        $factory = new HandlerStackFactory();
        $stack = $factory->create();
        return make(Client::class, [
            'config' => [
                'handler' => $stack,
            ],
        ]);
    }
}
