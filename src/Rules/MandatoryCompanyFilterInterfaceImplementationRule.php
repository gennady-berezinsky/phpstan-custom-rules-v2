<?php

namespace Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node\Stmt\Class_>
 */
class MandatoryCompanyFilterInterfaceImplementationRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Stmt\Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->getType()) {
            return [];
        }

        $hasInterfase = false;
        $declaredClassParts = $node->namespacedName->parts;
        if (in_array('Entity', $declaredClassParts) && in_array('App', $declaredClassParts)) {
            if ($node->getProperty('company')) {
                $reflection = new \ReflectionClass($node->namespacedName->toCodeString());

                foreach ($reflection->getInterfaceNames() as $intrf) {
                    if (str_contains($intrf, 'CompanyObjectInterface') || str_contains($intrf, 'CompanyUserInterface')) {
                        $hasInterfase = true;
                        break;
                    }
                }

                if (!$hasInterfase) {
                    return [
                        RuleErrorBuilder::message('Entities with property $company should implement CompanyObjectInterface or CompanyUserInterface. Check this Entity and implement interface or add error to ignore.')
                            ->identifier('CustomRule.MandatoryCompanyFilterInterfaceImplementationRule')
                            ->build()
                    ];
                }
            }
        }

        return [];
    }

}