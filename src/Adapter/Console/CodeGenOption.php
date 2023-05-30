<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console;

class CodeGenOption
{
    public const PROPERTY_SNAKE_CASE = 0;

    public const PROPERTY_CAMEL_CASE = 1;

    protected ?string $pool = null;

    protected ?string $path = null;

    protected ?string $prefix = null;

    protected ?string $inheritance = null;

    protected array $uses = [];

    protected array $tableMapping = [];

    protected int $propertyCase = self::PROPERTY_SNAKE_CASE;

    public function getPool(): string
    {
        return $this->pool;
    }

    public function setPool(string $pool): static
    {
        $this->pool = $pool;
        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;
        return $this;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function setPrefix(string $prefix): static
    {
        $this->prefix = $prefix;
        return $this;
    }

    public function getInheritance(): string
    {
        return $this->inheritance;
    }

    public function setInheritance(string $inheritance): static
    {
        $this->inheritance = $inheritance;
        return $this;
    }

    public function getUses(): array
    {
        return $this->uses;
    }

    public function setUses(array $uses): static
    {
        $this->uses = $uses;
        return $this;
    }

    public function getTableMapping(): array
    {
        return $this->tableMapping;
    }

    public function setTableMapping(array $tableMapping): static
    {
        foreach ($tableMapping as $item) {
            [$key, $name] = explode(':', $item);
            $this->tableMapping[$key] = $name;
        }

        return $this;
    }

    public function isCamelCase(): bool
    {
        return $this->propertyCase === self::PROPERTY_CAMEL_CASE;
    }

    public function setPropertyCase($propertyCase): static
    {
        $this->propertyCase = (int) $propertyCase;
        return $this;
    }
}
