<?php

namespace Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

class AuditedCommandRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Stmt\ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {

        if (!$node->getType()) {
            return [];
        }
        // не перевіряєм хелсчекери
        if (in_array('App\Command\Request\BaseHealthCheckCommand', $scope->getClassReflection()->getParentClassesNames())) {
            return [];
        }

        // для команд які пишуть аудит - викликати відповідний метод
        if (in_array('App\Command\AbstractAuditedCommand', $scope->getClassReflection()->getParentClassesNames())) {
            if ($node->name->name === 'execute') {
                $collectedMethodCalls = [];
                foreach ($node->stmts as $value) {
                    if ($value instanceof Node\Stmt\Expression) {
                        if($value->expr->name->name) {
                            $collectedMethodCalls[] = $value->expr->name->name;
                        }
                    }
                }


                if (!in_array('setAuditIdentityData', $collectedMethodCalls)) {
                    return ['Commands that writes audit should implement AbstractAuditedCommand and call setAuditIdentityData in execute'];
                }
                return [];
            }
        }

        if ($node->name->name === 'execute') {
            $collectedMethodCalls = [];
            foreach ($node->stmts as $value) {
                if ($value instanceof Node\Stmt\Expression) {
                    if($value->expr->name->name) {
                        $collectedMethodCalls[] = $value->expr->name->name;
                    }
                }
            }

            if (!in_array('disableAuditListener', $collectedMethodCalls)) {
                return ['Commands that not implements AbstractAuditedCommand must disable audit listener with disableAuditListener'];
            }
            return [];
        }



        return [];
    }

}