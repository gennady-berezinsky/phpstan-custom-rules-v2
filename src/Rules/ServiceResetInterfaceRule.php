<?php

namespace Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Class_>
 *
 * Enforces that any App\Service\ class with mutable state properties:
 *   1. implements Symfony\Contracts\Service\ResetInterface
 *   2. resets every such property inside reset()
 *
 * Messenger workers are singletons — state left in properties bleeds across
 * messages and causes tenant leaks.
 *
 * A property is considered "state" when it is non-static, non-readonly,
 * has no #[Required] attribute, is not a DI dependency, and is actually
 * written to in a regular method (not __construct / #[Required] setter).
 */
class ServiceResetInterfaceRule implements Rule
{
    private const RESET_INTERFACE = 'Symfony\Contracts\Service\ResetInterface';

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

        if (!str_starts_with($className, 'App\\Service\\')) {
            return [];
        }

        if (str_starts_with($className, 'App\\Service\\Fixtures\\')) {
            return [];
        }

        if (str_contains($className, '\\Model\\')) {
            return [];
        }

        if (str_contains($className, '\\Response\\')) {
            return [];
        }

        if (!$this->reflectionProvider->hasClass($className)) {
            return [];
        }

        $deps = $this->findDependencyProperties($node);
        $stateProps = $this->findStateProperties($node, $deps);

        if (empty($stateProps)) {
            return [];
        }

        $classReflection = $this->reflectionProvider->getClass($className);

        if (!$classReflection->implementsInterface(self::RESET_INTERFACE)) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'Service %s has mutable state properties (%s) but does not implement ResetInterface.'
                    .' Messenger workers are singletons — uncleared state causes tenant leaks between messages.',
                    $className,
                    $this->formatProps($stateProps),
                ))
                    ->identifier('ServiceResetInterfaceRule')
                    ->build(),
            ];
        }

        $resetMethod = $this->findMethod($node, 'reset');
        if ($resetMethod === null) {
            return [];
        }

        $notReset = array_values(array_diff($stateProps, $this->findAssignedProperties($resetMethod)));
        if (empty($notReset)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Service %s reset() does not reset state properties: %s.',
                $className,
                $this->formatProps($notReset),
            ))
                ->identifier('ServiceResetInterfaceRule')
                ->build(),
        ];
    }

    /**
     * Returns property names wired by DI: constructor-promoted params,
     * properties assigned in __construct, and properties assigned in #[Required] setters.
     *
     * @return list<string>
     */
    private function findDependencyProperties(Class_ $node): array
    {
        $deps = [];

        foreach ($node->stmts as $stmt) {
            if (!($stmt instanceof ClassMethod)) {
                continue;
            }

            if ($stmt->name->name === '__construct') {
                foreach ($stmt->params as $param) {
                    if ($param->flags !== 0 && $param->var instanceof Node\Expr\Variable) {
                        $deps[] = (string) $param->var->name;
                    }
                }
                $deps = array_merge($deps, $this->findAssignedProperties($stmt));
                continue;
            }

            if ($this->hasRequiredAttribute($stmt)) {
                $deps = array_merge($deps, $this->findAssignedProperties($stmt));
            }
        }

        return array_unique($deps);
    }

    /**
     * @param list<string> $deps
     * @return list<string>
     */
    private function findStateProperties(Class_ $node, array $deps): array
    {
        $writtenInMethods = $this->findPropertiesWrittenInRegularMethods($node);
        $state = [];

        foreach ($node->stmts as $stmt) {
            if (!($stmt instanceof Property) || $stmt->isStatic() || $stmt->isReadonly()) {
                continue;
            }
            if ($this->hasRequiredAttribute($stmt)) {
                continue;
            }

            foreach ($stmt->props as $prop) {
                $name = $prop->name->name;
                if (!in_array($name, $deps, true) && in_array($name, $writtenInMethods, true)) {
                    $state[] = $name;
                }
            }
        }

        return $state;
    }

    /** @return list<string> */
    private function findPropertiesWrittenInRegularMethods(Class_ $node): array
    {
        $written = [];

        foreach ($node->stmts as $stmt) {
            if (!($stmt instanceof ClassMethod)) {
                continue;
            }
            if ($stmt->name->name === '__construct' || $this->hasRequiredAttribute($stmt)) {
                continue;
            }
            $written = array_merge($written, $this->findAssignedProperties($stmt));
        }

        return array_unique($written);
    }

    /** @return list<string> */
    private function findAssignedProperties(ClassMethod $method): array
    {
        $props = [];

        foreach ($method->stmts ?? [] as $stmt) {
            if ($stmt instanceof Node\Stmt\Unset_) {
                foreach ($stmt->vars as $var) {
                    if ($var instanceof Node\Expr\PropertyFetch
                        && $var->var instanceof Node\Expr\Variable
                        && $var->var->name === 'this'
                        && $var->name instanceof Node\Identifier
                    ) {
                        $props[] = $var->name->name;
                    }
                }
                continue;
            }

            if (!($stmt instanceof Node\Stmt\Expression)
                || !($stmt->expr instanceof Node\Expr\Assign)
                || !($stmt->expr->var instanceof Node\Expr\PropertyFetch)
                || !($stmt->expr->var->var instanceof Node\Expr\Variable)
                || $stmt->expr->var->var->name !== 'this'
                || !($stmt->expr->var->name instanceof Node\Identifier)
            ) {
                continue;
            }

            $props[] = $stmt->expr->var->name->name;
        }

        return $props;
    }

    private function findMethod(Class_ $node, string $name): ?ClassMethod
    {
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof ClassMethod && $stmt->name->name === $name) {
                return $stmt;
            }
        }

        return null;
    }

    private function hasRequiredAttribute(ClassMethod|Property $node): bool
    {
        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                $name = $attr->name->toString();
                if ($name === 'Required' || str_ends_with($name, '\\Required')) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param list<string> $props */
    private function formatProps(array $props): string
    {
        return implode(', ', array_map(static fn (string $p) => '$'.$p, $props));
    }
}
