<?php

namespace Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;

class CommandParentClassRule implements Rule
{
    private ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!isset($node->namespacedName)) {
            return [];
        }

        $className = $node->namespacedName->toString();
        if (!str_starts_with($className, 'App\\Command\\')) {
            return [];
        }

        if ($className === 'App\\Command\\AbstractCommand') {
            return [];
        }

        if (!$this->reflectionProvider->hasClass($className)) {
            return [];
        }

        $classReflection = $this->reflectionProvider->getClass($className);

        if (!$classReflection->isSubclassOf('App\\Command\\AbstractCommand')) {
            return [
                sprintf('Class %s must extend App\Command\AbstractCommand.', $className)
            ];
        }

        return [];
    }
}