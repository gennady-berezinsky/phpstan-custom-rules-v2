<?php

namespace Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\ClassMethod;
use PHPStan\Rules\Rule;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class StaticMethodInVoterRule implements Rule
{
    /**
     * Node\Stmt\ClassMethod::class - оголошення методу в класі
     */
    public function getNodeType(): string
    {
        return Node\Stmt\ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     * @param Scope $scope
     * @return array|string[]
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // перевіряєм чи є в перентах Воутер
        $isVoter = in_array(Voter::class, $scope->getClassReflection()->getParentClassesNames(), true);
        if (!$isVoter) {
            return [];
        }
        $isStatic = $node->isStatic();
        if ($isStatic) {
            return ['Static methods in Voters are forbidden'];
        }
        return [];
    }
}