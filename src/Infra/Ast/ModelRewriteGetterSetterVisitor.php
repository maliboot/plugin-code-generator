<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Infra\Ast;

use Hyperf\Utils\Str;
use PhpParser\Node;

class ModelRewriteGetterSetterVisitor extends \Hyperf\Database\Commands\Ast\ModelRewriteGetterSetterVisitor
{
    /**
     * @return Node\Stmt\ClassMethod[]
     */
    protected function buildGetterAndSetter(): array
    {
        $stmts = [];
        foreach ($this->data->getColumns() as $column) {
            if ($name = $column['column_name'] ?? null) {
                $getter = getter($name);
                if (! in_array($getter, $this->getters)) {
                    $stmts[] = $this->createGetter($getter, Str::camel($name));
                }
                $setter = setter($name);
                if (! in_array($setter, $this->setters)) {
                    $stmts[] = $this->createSetter($setter, Str::camel($name));
                }
            }
        }

        return $stmts;
    }
}
