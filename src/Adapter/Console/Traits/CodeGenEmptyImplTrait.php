<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console\Traits;

trait CodeGenEmptyImplTrait
{
    protected function getStub(): string
    {
        return '';
    }

    protected function getInheritance(): string
    {
        return '';
    }

    protected function getUses(): array
    {
        return [];
    }

    protected function getFileType(): string
    {
        return '';
    }
}
