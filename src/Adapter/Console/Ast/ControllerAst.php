<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console\Ast;

use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;

class ControllerAst
{
    private Parser $astParser;

    private PrettyPrinterAbstract $printer;

    public function __construct()
    {
        $parserFactory = new ParserFactory();
        $this->astParser = $parserFactory->create(ParserFactory::ONLY_PHP7);
        $this->printer = new Standard();
    }

    public function parse(string $code): ?array
    {
        return $this->astParser->parse($code);
    }

    public function addMethod(string $className, array $stmts, array $dataParams)
    {
        $traverser = new NodeTraverser();
        $visitorMetadata = new ControllerVisitorMetadata($className);
        $methodData = new ControllerMethodData(...$dataParams);
        $visitor = new ControllerRewriteAddMethodVisitor($visitorMetadata, $methodData);
        $traverser->addVisitor($visitor);
        $modifiedStmts = $traverser->traverse($stmts);
        return $this->printer->prettyPrintFile($modifiedStmts);
    }
}
