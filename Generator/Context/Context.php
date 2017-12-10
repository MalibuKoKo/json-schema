<?php

namespace Jane\JsonSchema\Generator\Context;

use Jane\JsonSchema\Generator\File;
use Jane\JsonSchema\Registry;
use Jane\JsonSchema\Schema;

/**
 * Context when generating a library base on a Schema.
 */
class Context
{
    private $registry;

    private $files = [];

    private $variableScope;

    private $currentSchema;

    private $strict;

    public function __construct(Registry $registry, bool $strict = true)
    {
        $this->registry = $registry;
        $this->variableScope = new UniqueVariableScope();
        $this->strict = $strict;
    }

    public function isStrict(): bool
    {
        return $this->strict;
    }

    public function addFile(File $file): void
    {
        $this->files[] = $file;
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function getRegistry(): Registry
    {
        return $this->registry;
    }

    public function getCurrentSchema(): Schema
    {
        return $this->currentSchema;
    }

    public function setCurrentSchema(Schema $currentSchema): void
    {
        $this->currentSchema = $currentSchema;
    }

    /**
     * Refresh the unique variable scope for a context.
     */
    public function refreshScope(): void
    {
        $this->variableScope = new UniqueVariableScope();
    }

    public function getUniqueVariableName(string $prefix = 'var'): string
    {
        return $this->variableScope->getUniqueName($prefix);
    }
}
