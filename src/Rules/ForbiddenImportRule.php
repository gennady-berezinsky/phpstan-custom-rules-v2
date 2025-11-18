<?php

namespace Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

class ForbiddenImportRule implements Rule
{
    private array $disallowedNamespaces = [
        'ParaTest',
        'Deployer',
        'Doctrine\Bundle\FixturesBundle',
        'PhpCsFixer',
        'Hennadybb\PhpStan\CustomRules',
        'NunoMaduro\PhpInsights',
        'PhpStan\ExtensionInstaller',
        'PHPStan\Doctrine',
        'PHPStan\Symfony',
        'PHPUnit',
        'Rector',
        'Symfony\Component\BrowserKit',
        'Symfony\Bundle\DebugBundle',
        'Symfony\Bundle\MakerBundle',
        'Symfony\Bridge\PhpUnit',
        'Symfony\Requirements',
        'Symfony\Bundle\WebProfilerBundle',
        'Nelmio\ApiDocBundle',
    ];

    public function getNodeType(): string
    {
        return Node\Stmt\UseUse::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $filename = $scope->getFile();
        if (str_contains($filename, '/tests/')) {
            return [];
        }
        if (str_contains($filename, '/src/DataFixtures/')) {
            return [];
        }

        $importedName = $node->name->toString() . '\\';

        foreach ($this->disallowedNamespaces as $disallowedNs) {
            if (str_starts_with($importedName, $disallowedNs)) {
                $cleanDisallowedNs = rtrim($disallowedNs, '\\');
                $cleanImportedName = rtrim($importedName, '\\');

                $message = sprintf(
                    'Importing from disallowed namespace "%s" is forbidden. Found import: "%s".',
                    $cleanDisallowedNs,
                    $cleanImportedName
                );

                return [
                    RuleErrorBuilder::message($message)
                        ->line($node->getLine())
                        ->build(),
                ];
            }
        }

        return [];
    }

}