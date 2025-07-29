<?php

namespace Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;

/**
 * @implements Rule<Class_>
 */
class NoObjectsInPropertiesConstraintRule implements Rule
{
    const MESSAGE_FOLDER_PATH = 'src/Messenger/Message/';
    const MESSAGE_ATTRIBUTE_PATH = 'App\\Validator\\Message\\NoObjectsInPropertiesConstraint';

    public function getNodeType(): string
    {
        return Node\Stmt\Class_::class;
    }

    /**
     * @throws ShouldNotHappenException
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $filePath = realpath($scope->getFile());
        if (
            $filePath === false ||
            !str_starts_with($filePath, realpath(self::MESSAGE_FOLDER_PATH)) ||
            !str_ends_with($filePath, 'Message.php')
        ) {
            return [];
        }

        $className = $node->namespacedName->toCodeString();
        $classReflection = new \ReflectionClass($className);

        while ($classReflection !== false) {
            foreach ($classReflection->getAttributes() as $attr) {
                if ($attr->getName() === self::MESSAGE_ATTRIBUTE_PATH) {
                    return [];
                }
            }

            $classReflection = $classReflection->getParentClass();
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Class %s must be annotated with #[%s]',
                $className,
                self::MESSAGE_ATTRIBUTE_PATH
            ))->build(),
        ];
    }
}
