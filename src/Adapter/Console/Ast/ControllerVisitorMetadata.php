<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console\Ast;

class ControllerVisitorMetadata
{
    public bool $hasExecutorNamespaceUse = false;

    public bool $hasCommandNamespaceUse = false;

    public bool $hasViewObjectNamespaceUse = false;

    public bool $hasProperty = false;

    public bool $hasClassMethod = false;

    public bool $hasExecutorProperty = false;

    public bool $hasAddMethod = false;

    public bool $hasPath = false;

    public function __construct(public string $className)
    {
    }
}
