<?php

declare(strict_types=1);

namespace MaliBoot\PluginCodeGenerator\Adapter\Console\Ast;

use Hyperf\Collection\Arr;

class ControllerMethodData
{
    public function __construct(
        protected string $executor,
        protected string $auth,
        protected string $path,
        protected array $httpMethods,
        protected string $name,
        protected string $method,
        protected string $command,
        protected string $viewObject,
        protected string $apiResponseType
    ) {
    }

    public function getExecutor(): string
    {
        return $this->executor;
    }

    public function getExecutorBaseName(): string
    {
        $executorArr = explode('\\', trim($this->executor, '\\'));
        return Arr::last($executorArr);
    }

    public function getAuth(): string
    {
        return $this->auth;
    }

    public function setAuth(string $auth): self
    {
        $this->auth = $auth;
        return $this;
    }

    public function setExecutor(string $executor): self
    {
        $this->executor = $executor;
        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getHttpMethods(): array
    {
        return $this->httpMethods;
    }

    /**
     * @param array $httpMethod
     */
    public function setHttpMethods(array $httpMethods): self
    {
        $this->httpMethods = $httpMethods;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getMethodBaseName(): string
    {
        $methodArr = explode('\\', trim($this->method, '\\'));
        return Arr::last($methodArr);
    }

    public function setMethod(string $method): self
    {
        $this->method = $method;
        return $this;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getCommandBaseName(): string
    {
        $commandArr = explode('\\', trim($this->command, '\\'));
        return Arr::last($commandArr);
    }

    public function setCommand(string $command): self
    {
        $this->command = $command;
        return $this;
    }

    public function getViewObject(): string
    {
        return $this->viewObject;
    }

    public function getViewObjectBaseName(): string
    {
        $viewObjectArr = explode('\\', trim($this->viewObject, '\\'));
        return Arr::last($viewObjectArr);
    }

    public function setViewObject(string $viewObject): self
    {
        $this->viewObject = $viewObject;
        return $this;
    }

    public function getApiResponseType(): string
    {
        return $this->apiResponseType;
    }

    public function getApiResponseTypeBaseName(): string
    {
        $apiResponseTypeArr = explode('\\', trim($this->apiResponseType, '\\'));
        return Arr::last($apiResponseTypeArr);
    }

    public function setApiResponseType(string $apiResponseType): self
    {
        $this->apiResponseType = $apiResponseType;
        return $this;
    }
}
