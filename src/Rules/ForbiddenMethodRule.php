<?php

namespace Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

class ForbiddenMethodRule implements Rule
{

    private $forbiddenMethods = [
        'dump',
        'die',
        'dd',
        'var_dump',
    ];

    private $checkNodeTypes = [
        'Expr_FuncCall',
        'Expr_Exit'
    ];

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!in_array($node->getType(), $this->checkNodeTypes, true)) {
            return [];
        }
        if ($node instanceof Node\Expr\FuncCall) {
            $funcName = $node->name;
            if ($funcName instanceof Node\Name) {
                $stringName = $funcName->toString();
                if (in_array($stringName, $this->forbiddenMethods, true)) {
                    return ["Use of $stringName is forbidden"];
                }
            }
        }

        if ($node instanceof Node\Expr\Exit_) {
            return ["Use of exit statements is forbidden"];
        }

        return [];
    }
}