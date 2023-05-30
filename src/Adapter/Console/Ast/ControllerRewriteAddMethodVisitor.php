<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console\Ast;

use Hyperf\Utils\Str;
use PhpParser\Builder;
use PhpParser\BuilderFactory;
use PhpParser\BuilderHelpers;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class ControllerRewriteAddMethodVisitor extends NodeVisitorAbstract
{
    public function __construct(
        protected ControllerVisitorMetadata $controllerVisitorMetadata,
        protected ControllerMethodData $controllerMethodData
    ) {
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Use_ && $node->type === 1) {
            foreach ($node->uses as $use) {
                $useStr = join('\\', $use->name->parts);
                if ($useStr === trim($this->controllerMethodData->getExecutor(), '\\')) {
                    $this->controllerVisitorMetadata->hasExecutorNamespaceUse = true;
                } elseif ($useStr === trim($this->controllerMethodData->getCommand(), '\\')) {
                    $this->controllerVisitorMetadata->hasCommandNamespaceUse = true;
                } elseif ($useStr === trim($this->controllerMethodData->getViewObject(), '\\')) {
                    $this->controllerVisitorMetadata->hasViewObjectNamespaceUse = true;
                }
            }
        }

        if ($node instanceof Node\Stmt\Property) {
            $this->controllerVisitorMetadata->hasProperty = true;
            if (in_array($this->controllerMethodData->getExecutorBaseName(), $node->type->parts)) {
                $this->controllerVisitorMetadata->hasExecutorProperty = true;
            }
        }

        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->controllerVisitorMetadata->hasClassMethod = true;
            if ($node->name->toString() === $this->controllerMethodData->getMethodBaseName()) {
                $this->controllerVisitorMetadata->hasAddMethod = true;
            }
        }
        return null;
    }

    public function leaveNode(Node $node)
    {
        $namespaceUseStmts = [];
        if (! $this->controllerVisitorMetadata->hasExecutorNamespaceUse) {
            $namespaceUseStmts[] = $this->buildNamespaceUse($this->controllerMethodData->getExecutor());
        }

        if (! $this->controllerVisitorMetadata->hasCommandNamespaceUse) {
            $namespaceUseStmts[] = $this->buildNamespaceUse($this->controllerMethodData->getCommand());
        }

        if (! $this->controllerVisitorMetadata->hasViewObjectNamespaceUse) {
            $namespaceUseStmts[] = $this->buildNamespaceUse($this->controllerMethodData->getViewObject());
        }

        if (! empty($namespaceUseStmts) && $node instanceof Node\Stmt\Namespace_) {
            $stmts = $node->stmts;
            $node->stmts = $this->insertNamespaceUseStmts($stmts, $namespaceUseStmts);
        }

        if (! $this->controllerVisitorMetadata->hasExecutorProperty && $node instanceof Node\Stmt\Class_) {
            $stmts = $node->stmts;
            $propertyStmt = $this->buildProperty();
            $node->stmts = $this->insertPropertyStmts($stmts, [$propertyStmt]);
        }

        if (! $this->controllerVisitorMetadata->hasAddMethod && $node instanceof Node\Stmt\Class_) {
            $classMethodStmt = $this->buildClassMethod();
            $node->stmts = array_merge($node->stmts, [$classMethodStmt]);
        }

        return null;
    }

    protected function buildNamespaceUse(string $use): Node\Stmt\Use_
    {
        $useNamespace = new Builder\Use_($use, Node\Stmt\Use_::TYPE_NORMAL);
        return $useNamespace->getNode();
    }

    protected function buildProperty(): Node\Stmt\Property
    {
        $propertyName = Str::camel($this->controllerMethodData->getExecutorBaseName());
        $propertyType = $this->controllerMethodData->getExecutorBaseName();
        $property = (new Builder\Property($propertyName))
            ->makeProtected()
            ->setType($propertyType)
            ->addAttribute(new Node\Attribute(BuilderHelpers::normalizeName('Inject')));

        return $property->getNode();
    }

    protected function buildClassMethod(): Node\Stmt\ClassMethod
    {
        $executorName = $this->controllerMethodData->getExecutorBaseName();
        $methodName = $this->controllerMethodData->getMethodBaseName();
        $commandName = $this->controllerMethodData->getCommandBaseName();
        $viewObjectName = $this->controllerMethodData->getViewObjectBaseName();
        $apiResponseName = $this->controllerMethodData->getApiResponseTypeBaseName();
        $builderFactory = new BuilderFactory();
        $method = (new Builder\Method($methodName))
            ->makePublic()
            ->setReturnType($viewObjectName)
            ->addParam(
                (new Builder\Param(Str::camel($commandName)))
                    ->setType($commandName)
            )
            ->addAttribute($builderFactory->attribute('Auth', ['value' => $this->controllerMethodData->getAuth()]))
            ->addAttribute(
                $builderFactory->attribute(
                    'ApiMapping',
                    [
                        'path' => $this->controllerMethodData->getPath(),
                        'methods' => $this->controllerMethodData->getHttpMethods(),
                        'name' => $this->controllerMethodData->getName(),
                    ]
                )
            )
            ->addAttribute(
                $builderFactory->attribute(
                    'ApiRequest',
                    ['value' => new Node\Expr\ClassConstFetch(new Node\Name($commandName), 'class')]
                )
            )
            ->addAttribute(
                $builderFactory->attribute(
                    $apiResponseName,
                    ['value' => new Node\Expr\ClassConstFetch(new Node\Name($viewObjectName), 'class')]
                )
            )
            ->addStmts([
                new Node\Stmt\Return_(
                    new Node\Expr\MethodCall(
                        new Node\Expr\PropertyFetch(
                            new Node\Expr\Variable('this'),
                            Str::camel($executorName)
                        ),
                        'execute',
                        [new Node\Arg(new Node\Expr\Variable(Str::camel($commandName)))]
                    )
                ),
            ]);

        return $method->getNode();
    }

    protected function insertNamespaceUseStmts(array $stmts, array $insertStmts): array
    {
        $startIndex = 0;
        foreach ($stmts as $key => $stmt) {
            if (! $stmt instanceof Node\Stmt\Use_) {
                $startIndex = $key;
                break;
            }
        }

        array_splice($stmts, $startIndex, 0, $insertStmts);
        return $stmts;
    }

    protected function insertPropertyStmts(array $stmts, array $insertStmts): array
    {
        $startIndex = 0;
        foreach ($stmts as $key => $stmt) {
            if ($stmt instanceof Node\Stmt\ClassMethod) {
                $startIndex = $key;
                break;
            }
        }

        array_splice($stmts, $startIndex, 0, $insertStmts);
        return $stmts;
    }
}
