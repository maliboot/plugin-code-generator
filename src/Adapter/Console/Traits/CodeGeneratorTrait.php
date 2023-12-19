<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console\Traits;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Stringable\Str;
use MaliBoot\Dto\AbstractCommand;
use MaliBoot\PluginCodeGenerator\Client\ViewObject\TemplateVO;
use MaliBoot\Utils\File;

trait CodeGeneratorTrait
{
    /**
     * 预览代码
     */
    public function preview(object $customVariable, TemplateVO $templateVO): string
    {
        return $this->getCodeContent($customVariable, $templateVO);
    }

    /**
     * 生成代码
     */
    public function generator(object $customVariable, TemplateVO $templateVO): void
    {
        $codeContent = $this->getCodeContent($customVariable, $templateVO);
        $this->generatorFile($templateVO->getGeneratorFilePath(), $codeContent);
    }

    /**
     * 获取代码内容.
     */
    /**
     * @param AbstractCommand $customVariable 模板自定义变量
     * @param TemplateVO $templateVO 模板公共变量
     */
    protected function getCodeContent(object $customVariable, TemplateVO $templateVO): string
    {
        $data = array_merge($templateVO->toArray(), $customVariable->toArray());
        return $this->render($templateVO->getTemplateFilePath(), $data);
    }

    /**
     * 获取业务名称.
     */
    protected function getBusinessNameByTableName(string $tableName): string
    {
        $config = $this->container->get(ConfigInterface::class);
        $dbPrefix = $config->get('database.default.prefix', '');
        return Str::studly(str_replace($dbPrefix, '', $tableName));
    }

    /**
     * @return bool
     */
    protected function generatorFile(string $generatorFilePath, string $codeContent, bool $force = false)
    {
        $dir = dirname($generatorFilePath);
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0755, true, true);
        }

        if (File::exists($generatorFilePath) && ! $force) {
            return false;
        }

        File::put($generatorFilePath, $codeContent);
        return true;
    }

    /**
     * 渲染视图.
     * @param string $viewFile
     * @param null|array $data
     */
    private function render($viewFile, $data = null): string
    {
        if (is_array($data)) {
            extract($data, EXTR_PREFIX_SAME, 'data');
        }

        ob_start();
        ob_implicit_flush(false);
        require $viewFile;
        return ob_get_clean();
    }
}
