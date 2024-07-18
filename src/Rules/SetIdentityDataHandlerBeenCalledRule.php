<?php

namespace Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

class SetIdentityDataHandlerBeenCalledRule implements Rule
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

        if (!in_array('App\Messenger\MessageHandler\AbstractAuditedMessageHandler', $scope->getClassReflection()->getParentClassesNames())) {
            return [];
        }

        if ($node->name->name === '__invoke') {
            $collectedMethodCalls = [];
            foreach ($node->stmts as $value) {
                if ($value instanceof Node\Stmt\Expression) {
                    if($value->expr->name->name) {
                        $collectedMethodCalls[] = $value->expr->name->name;
                    }
                }
            }


            if (!in_array('setAuditIdentityData', $collectedMethodCalls)) {
                return ['Messages that extends AbstractAuditedMessageHandler should call setAuditIdentityData in __invoke'];
            }
        }

        return [];
    }
}