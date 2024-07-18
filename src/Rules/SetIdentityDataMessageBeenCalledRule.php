<?php

namespace Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

class SetIdentityDataMessageBeenCalledRule implements Rule
{
    private $collectedCalls = [];
    private $parentNames = [];

    public function getNodeType(): string
    {
        return Node\Expr\New_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->getType()) {
            return [];
        }

        $className = $node->class->__toString();
        if (str_contains($className, 'Messenger\Message')) {
            $this->parentNames = [];
            $reflection = new \ReflectionClass($className);
            $this->getParentNames($reflection);
            if (in_array('App\Messenger\Message\AbstractAuditedMessage', $this->parentNames)) {
                foreach ($node->args as $arg) {
                    $this->collectedCalls = [];
                    if (get_class($arg->value) === Node\Expr\MethodCall::class) {
                        $this->recursiceCheck($arg->value->getAttributes()['parent']->value);

                        $dispatchIndex = array_search('dispatch', $this->collectedCalls);
                        if ($dispatchIndex) {
                            if (!array_key_exists($dispatchIndex+1, $this->collectedCalls) || $this->collectedCalls[$dispatchIndex+1] !== 'setAuditIdentityData') {
                                return ['Messages that implements AbstractAuditedMessage should call setAuditIdentityData after dispatch'];
                            }
                        }
                    }
                }
            }

        }

        return [];
    }

    private function recursiceCheck($node)
    {
        if ($node->getAttributes()['parent']) {
            $this->recursiceCheck($node->getAttributes()['parent']);
        }
        if ($node->name->name) {
            $this->collectedCalls[] = $node->name->name;
        }
    }

    private function getParentNames(\ReflectionClass $reflection)
    {
        if ($reflection->getParentClass()) {
            $this->parentNames[] = $reflection->getParentClass()->getName();
            $this->getParentNames($reflection->getParentClass());
        }
        return;
    }


}