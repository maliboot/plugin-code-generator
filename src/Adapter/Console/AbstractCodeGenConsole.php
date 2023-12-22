<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Database\ConnectionResolverInterface;
use Hyperf\Database\Schema\Builder;
use Hyperf\Stringable\Str;
use MaliBoot\PluginCodeGenerator\Client\Constants\FileType;
use MaliBoot\Utils\CodeGen\Plugin;
use MaliBoot\Utils\File;
use MaliBoot\Utils\Process\Process;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

abstract class AbstractCodeGenConsole extends HyperfCommand
{
    protected ?ConfigInterface $config = null;

    protected ?string $table;

    protected ?string $businessName;

    protected array $buildGetterSetterFileTypes = [
        FileType::DOMAIN_MODEL_VALUE_OBJECT,
        FileType::DOMAIN_MODEL_ENTITY,
        FileType::DOMAIN_MODEL_AGGREGATE,
        FileType::CLIENT_VIEW_OBJECT,
        FileType::CLIENT_DTO_COMMAND,
        FileType::CLIENT_DTO_QUERY,
        FileType::INFRA_DATA_OBJECT,
    ];

    protected array $suffixList = [
        'Controller', 'Rpc', 'QryExe', 'CmdExe', 'QryRepo', 'CmdRepo', 'Repo',
        'QryService', 'DomainService', 'Service', 'DO', 'VO', 'DO', 'Cmd', 'Qry',
    ];

    protected array $filterColumns = ['deleted_at'];

    public function __construct(protected ContainerInterface $container, string $name = null)
    {
        parent::__construct($name);
        $this->config = $this->container->get(ConfigInterface::class);
    }

    /**
     * 生成代码
     */
    public function generator(string $pluginName, string $table, CodeGenOption $option, ?string $className = null, array $fields = []): void
    {
        $className = $this->getClassName($pluginName, $table, $option, $className);
        $generatorFilePath = $this->getClassPath($pluginName, $className);
        $codeContent = $this->buildClass($pluginName, $table, $className, $option, $fields);
        $this->generatorFile($generatorFilePath, $codeContent);
        $this->clearGitignore($generatorFilePath);
    }

    protected function getClassName(string $pluginName, string $table, CodeGenOption $option, ?string $className = null)
    {
        if (empty($className)) {
            $className = $option->getTableMapping()[$table] ?? Str::studly(Str::singular($table));
        }

        $plugin = new Plugin($pluginName);
        return $plugin->namespace($option->getPath()) . $className . $this->getClassSuffix();
    }

    protected function getPath(string $fileType): string
    {
        if ($this->input->hasOption('path')
            && $result = $this->input->getOption('path')
        ) {
            if ($result !== null) {
                return $result;
            }
        }

        $pluginConfig = $this->config->get('plugin', []);
        $fullPath = sprintf(
            '%s/%s/%s',
            data_get($pluginConfig, 'paths.base_path', 'plugin'),
            $this->getPluginName(),
            data_get($pluginConfig, 'paths.generator.' . $fileType . '.path')
        );

        return str_replace(BASE_PATH . '/', '', $fullPath);
    }

    protected function getOption(string $name, string $key, string $pool = 'default', $default = null)
    {
        $result = $this->input->getOption($name);
        $nonInput = null;

        if (in_array($name, ['force-casts', 'refresh-fillable', 'with-comments', 'with-ide'])) {
            $nonInput = false;
        }
        if (in_array($name, ['table-mapping', 'ignore-tables', 'visitors'])) {
            $nonInput = [];
        }

        if ($result === $nonInput) {
            $result = $this->config->get("databases.{$pool}.{$key}", $default);
        }

        return $result;
    }

    protected function getPluginName(): string
    {
        $pluginName = $this->input->getArgument('plugin');
        return str_replace('_', '-', Str::kebab($pluginName));
    }

    protected function getClassSuffix(): string
    {
        return '';
    }

    protected function getClassPath(string $pluginName, string $className)
    {
        $plugin = new Plugin($pluginName);
        return $this->getPathPrefix($pluginName) . '/' . $plugin->path($className);
    }

    protected function getPathPrefix(string $pluginName): string
    {
        return rtrim(config('plugin.paths.base_path', 'plugin'), '/') . '/' . $pluginName;
    }

    /**
     * 使用给定类名称生成类.
     */
    protected function buildClass(string $pluginName, string $table, string $className, CodeGenOption $option, array $fields = []): string
    {
        $stub = $this->getStub();

        $shortClassName = $this->getShortClassName($className);

        $this->replaceCopyright($stub)
            ->replaceNamespace($stub, $className)
            ->replaceInterface($stub, $table, $option, $shortClassName)
            ->replaceInheritance($stub, $option->getInheritance())
            ->replaceUses($stub, $option->getUses())
            ->replaceClass($stub, $className)
            ->replaceStudlyName($stub, $table, $shortClassName)
            ->replaceCamelName($stub, $table, $shortClassName)
            ->replaceCnName($stub, $table, $shortClassName);

        if (in_array($this->getFileType(), $this->buildGetterSetterFileTypes)) {
            $this->replaceProperties($stub, $table, $option, $fields)
                ->replaceGetterAndSetter($stub, $table, $option, $fields);
        }

        return $stub;
    }

    abstract protected function getStub(): string;

    protected function getShortClassName(string $className): string
    {
        $className = str_replace($this->getNamespace($className) . '\\', '', $className);
        return str_replace($this->suffixList, '', $className);
    }

    /**
     * 获取给定类的完整命名空间，不带类名.
     */
    protected function getNamespace(string $className): string
    {
        return trim(implode('\\', array_slice(explode('\\', $className), 0, -1)), '\\');
    }

    /**
     * 替换给定模板的版权信息.
     */
    protected function replaceCnName(string &$stub, string $table, ?string $shortClassName = null): static
    {
        $stub = str_replace(
            ['%CN_NAME%'],
            [$this->getCnName($table, $shortClassName)],
            $stub
        );

        return $this;
    }

    protected function getCnName(string $table, ?string $shortClassName = null): string
    {
        if (empty($this->cnName)) {
            return $this->getStudlyName($table, $shortClassName);
        }

        return $this->cnName;
    }

    protected function getStudlyName(string $table, ?string $shortClassName = null): string
    {
        if (empty($shortClassName)) {
            $dbPrefix = $this->getDbPrefix();
            return Str::studly(str_replace($dbPrefix, '', Str::singular($table)));
        }

        return Str::studly($shortClassName);
    }

    /**
     * 替换给定模板的版权信息.
     */
    protected function replaceCamelName(string &$stub, string $table, ?string $shortClassName = null): static
    {
        $stub = str_replace(
            ['%CAMEL_NAME%'],
            [$this->getCamelName($table, $shortClassName)],
            $stub
        );

        return $this;
    }

    protected function getCamelName(string $table, ?string $shortClassName = null): string
    {
        if (empty($shortClassName)) {
            $dbPrefix = $this->getDbPrefix();
            return Str::camel(str_replace($dbPrefix, '', Str::singular($table)));
        }

        return Str::camel($shortClassName);
    }

    /**
     * 替换给定模板的版权信息.
     */
    protected function replaceStudlyName(string &$stub, string $table, ?string $shortClassName = null): static
    {
        $stub = str_replace(
            ['%STUDLY_NAME%'],
            [$this->getStudlyName($table, $shortClassName)],
            $stub
        );

        return $this;
    }

    /**
     * 替换给定模板的类名.
     */
    protected function replaceClass(string &$stub, string $className): static
    {
        $class = str_replace($this->getNamespace($className) . '\\', '', $className);
        $stub = str_replace('%CLASS%', $class, $stub);

        return $this;
    }

    protected function replaceUses(string &$stub, array $uses): static
    {
        $usesStr = '';
        if (! empty($uses)) {
            $length = count($uses);
            foreach ($uses as $index => $use) {
                $usesStr .= "use {$use};";
                if ($length > 1 && $index !== $length - 1) {
                    $usesStr .= "\n";
                }
            }
        }

        $stub = str_replace(
            ['%USES%'],
            [$usesStr],
            $stub
        );

        return $this;
    }

    protected function replaceInheritance(string &$stub, string $inheritance): static
    {
        $stub = str_replace(
            ['%INHERITANCE%'],
            [$inheritance],
            $stub
        );

        return $this;
    }

    /**
     * 替换给定模板的命名空间.
     */
    protected function replaceInterface(string &$stub, string $table, CodeGenOption $option, ?string $shortClassName = null): static
    {
        $stub = str_replace(
            ['%INTERFACE%'],
            [$this->getInterface($table, $shortClassName)],
            $stub
        );

        return $this;
    }

    /**
     * 获取给定类的接口.
     */
    protected function getInterface(string $table, ?string $shortClassName = null): string
    {
        return '';
    }

    /**
     * 替换给定模板的命名空间.
     */
    protected function replaceNamespace(string &$stub, string $className): static
    {
        $stub = str_replace(
            ['%NAMESPACE%'],
            [$this->getNamespace($className)],
            $stub
        );

        return $this;
    }

    /**
     * 替换给定模板的版权信息.
     */
    protected function replaceCopyright(string &$stub): static
    {
        $stub = str_replace(
            ['%COPYRIGHT%'],
            [$this->getCopyright()],
            $stub
        );

        return $this;
    }

    protected function getCopyright(): string
    {
        return config('plugin.copyright', '');
    }

    abstract protected function getInheritance(): string;

    abstract protected function getUses(): array;

    abstract protected function getFileType(): string;

    /**
     * 替换给定模板的getter setter.
     */
    protected function replaceGetterAndSetter(string &$stub, string $table, CodeGenOption $option, array $fields = []): static
    {
        $stub = str_replace(
            ['%GETTER_SETTER%'],
            [$this->getGetterAndSetter($table, $option, $fields)],
            $stub
        );

        return $this;
    }

    protected function getGetterAndSetter(string $table, CodeGenOption $option, array $fields = []): string
    {
        return $this->buildGetterAndSetter($table, $option, $fields);
    }

    protected function buildGetterAndSetter(string $table, CodeGenOption $option, array $fields = []): string
    {
        $methodCode = '';
        if (empty($fields)) {
            $fields = $this->getFieldsByTable($table, $option);
        }
        foreach ($fields as $field) {
            $property = $this->getProperty($field);
            if (in_array($field['name'], $this->filterColumns)) {
                continue;
            }
            $methodCode .= $this->createGetter(getter($property[0]), lcfirst($property[0]), $property[1], $property[2]);
            $methodCode .= $this->createSetter(setter($property[0]), lcfirst($property[0]), $property[1], $property[2]);
        }

        $methodCode .= ' *';

        return $methodCode;
    }

    protected function getFieldsByTable(string $table, CodeGenOption $option)
    {
        $builder = $this->getSchemaBuilder($option->getPool());
        $table = Str::replaceFirst($option->getPrefix(), '', $table);
        $fields = $this->formatColumns($builder->getColumnTypeListing($table));
        if (empty($fields)) {
            return $fields;
        }

        $newFields = [];
        foreach ($fields as $key => $value) {
            $newFields[$key] = [
                'name' => $value['column_name'],
                'type' => $value['data_type'],
                'desc' => $value['column_comment'],
            ];

            if (! empty($value['cast'])) {
                $newFields[$key]['cast'] = $value['cast'];
            }
        }

        return $newFields;
    }

    protected function getSchemaBuilder(string $poolName): Builder
    {
        $connection = make(ConnectionResolverInterface::class)->connection($poolName);
        return $connection->getSchemaBuilder();
    }

    /**
     * Format column's key to lower case.
     */
    protected function formatColumns(array $fields): array
    {
        return array_map(function ($item) {
            return array_change_key_case($item, CASE_LOWER);
        }, $fields);
    }

    protected function getProperty($field): array
    {
        $name = Str::camel($field['name']);

        $type = $this->formatPropertyType($field['type'], $field['cast'] ?? null);

        $comment = $field['desc'] ?? '';

        return [$name, $type, $comment];
    }

    protected function formatPropertyType(string $type, ?string $cast): ?string
    {
        if (! isset($cast)) {
            $cast = $this->formatDatabaseType($type) ?? 'string';
        }

        switch ($cast) {
            case 'integer':
                return 'int';
            case 'date':
            case 'datetime':
                return '\Carbon\Carbon';
            case 'json':
                return 'array';
        }

        return $cast;
    }

    protected function formatDatabaseType(string $type): ?string
    {
        switch ($type) {
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'bigint':
                return 'integer';
            case 'bool':
            case 'boolean':
                return 'boolean';
            default:
                return null;
        }
    }

    protected function createGetter(string $method, string $name, string $type, string $comment): string
    {
        empty($comment) && $comment = '...';
        return sprintf(' * @method %s %s() %s', $type, $method, $comment) . "\n";
    }

    protected function createSetter(string $method, string $name, string $type, string $comment): string
    {
        empty($comment) && $comment = '...';
        return sprintf(' * @method self %s(%s $%s) %s', $method, $type, $name, $comment) . "\n";
    }

    /**
     * 替换给定模板的属性信息.
     */
    protected function replaceProperties(string &$stub, string $table, CodeGenOption $option, array $fields = []): static
    {
        $stub = str_replace(
            ['%PROPERTIES%'],
            [$this->getProperties($table, $option, $fields)],
            $stub
        );

        return $this;
    }

    protected function getProperties(string $table, CodeGenOption $option, array $fields = []): string
    {
        return $this->buildProperties($table, $option, $fields);
    }

    protected function buildProperties(string $table, CodeGenOption $option, $fields = [])
    {
        $fileType = $this->getFileType();

        $propertyCode = '';
        if (empty($fields)) {
            $fields = $this->getFieldsByTable($table, $option);
        }
        $fieldLength = count($fields);
        foreach ($fields as $key => $field) {
            if (! isset($field['data_type']) && isset($field['type'])) {
                $field['data_type'] = $field['type'];
            }

            $property = $this->getProperty($field);
            if (in_array($field['name'], $this->filterColumns)) {
                --$fieldLength;
                continue;
            }
            $propertyOpenApiType = $this->getOpenApiType($property[1]);

            if ($fileType === FileType::INFRA_DATA_OBJECT) {
                $propertyCode .= sprintf(
                    "    #[Column(name: \"%s\", type: \"%s\", desc: \"%s\")]\n",
                    $field['name'],
                    $this->formatPropertyType($field['data_type'], $field['cast'] ?? null),
                    $property[2],
                );
            } else {
                $propertyCode .= sprintf("    #[Field(name: \"%s\", type: \"%s\", desc: \"%s\")]\n", $property[0], $propertyOpenApiType, $property[2]);
            }

            $propertyCode .= sprintf('    private %s $%s;', $property[1], $property[0]);

            if ($key !== $fieldLength - 1) {
                $propertyCode .= "\n\n";
            }
        }

        return $propertyCode;
    }

    protected function getOpenApiType(string $type): string
    {
        switch ($type) {
            case 'int':
                $type = 'integer';
                break;
        }

        return $type;
    }

    /**
     * 生成文件.
     */
    protected function generatorFile(string $path, string $codeContent, bool $force = false): bool
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0755, true, true);
        }

        if ($this->input->getOption('force')) {
            $force = (bool) $this->input->getOption('force');
        }

        if (File::exists($path) && ! $force) {
            $this->warn(sprintf('文件已存在：%s', $path));
            return false;
        }

        File::put($path, $codeContent);
        $this->info(sprintf('文件创建成功：%s', $path));
        return true;
    }

    /**
     * 清理 gitignore 文件.
     */
    protected function clearGitignore(string $path): void
    {
        if (! File::exists($path)) {
            return;
        }

        $dir = File::dirname($path);
        if (File::exists($dir . '/.gitignore')) {
            File::delete($dir . '/.gitignore');
        }
    }

    protected function initOption()
    {
        $table = $this->input->getArgument('table');
        $pool = $this->input->getOption('pool');

        $option = new CodeGenOption();
        $option->setPool($pool)
            ->setPath($this->getPath($this->getFileType()))
            ->setPrefix($this->getOption('prefix', 'prefix', $pool, ''))
            ->setInheritance($this->getInheritance())
            ->setUses($this->getUses());

        if (in_array($this->getFileType(), $this->buildGetterSetterFileTypes)) {
            $option->setTableMapping($this->getOption('table-mapping', 'commands.gen:model.table_mapping', $pool, []))
                ->setPropertyCase($this->getOption('property-case', 'commands.gen:model.property_case', $pool));
        }

        return $option;
    }

    protected function getBusinessName(): string
    {
        if (! empty($businessName = $this->input->getOption('name'))) {
            return str::studly($businessName);
        }
        if (! empty($className = $this->input->getOption('class'))) {
            return $this->getBusinessNameByClassName($className);
        }
        if (! empty($table = $this->input->getArgument('table'))) {
            return $this->getStudlyName($table);
        }
        return '';
    }

    protected function getBusinessNameByClassName(string $className): string
    {
        return str::studly(str_replace($this->suffixList, '', $className));
    }

    protected function defaultConfigure()
    {
        $this->addArgument('plugin', InputArgument::REQUIRED, '插件名称');
        $this->addArgument('table', InputArgument::OPTIONAL, '表名称', '');

        $this->addOption('class', null, InputOption::VALUE_OPTIONAL, '类名称');
        $this->addOption('name', null, InputOption::VALUE_OPTIONAL, '业务名称');
        $this->addOption('pool', null, InputOption::VALUE_OPTIONAL, '数据库连接池', 'default');
        $this->addOption('path', null, InputOption::VALUE_OPTIONAL, '生成文件的路径');
        $this->addOption('prefix', null, InputOption::VALUE_OPTIONAL, '数据库前缀', '');
        $this->addOption('force', null, InputOption::VALUE_OPTIONAL, '是否强制覆盖', false);
    }

    protected function tableConfigure()
    {
        $this->addOption('table-mapping', 'M', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, '表映射关系');
        $this->addOption('property-case', null, InputOption::VALUE_OPTIONAL, '要使用哪个属性案例，0：蛇形案例，1：骆驼案例。');
    }

    protected function fixFile(string $filePath): void
    {
        $binFilePath = BASE_PATH . '/vendor/bin/php-cs-fixer';
        if (! File::exists($binFilePath) || ! File::exists($filePath)) {
            return;
        }

        Process::run(sprintf('%s %s %s', $binFilePath, 'fix', $filePath));
    }

    protected function getNamespaceByPath(string $path): string
    {
        $plugin = new Plugin($this->getPluginName());
        return $plugin->namespace($path);
    }

    private function getDbPrefix(): string
    {
        $config = $this->container->get(ConfigInterface::class);
        return $config->get('database.default.prefix', '');
    }
}
