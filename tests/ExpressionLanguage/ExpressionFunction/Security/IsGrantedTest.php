<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\ExpressionLanguage\ExpressionFunction\Security;

use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\Security\IsGranted;
use Redeye\GraphQLBundle\Generator\TypeGenerator;
use Redeye\GraphQLBundle\Tests\ExpressionLanguage\TestCase;

class IsGrantedTest extends TestCase
{
    protected function getFunctions()
    {
        return [new IsGranted()];
    }

    public function testEvaluator(): void
    {
        $security = $this->getSecurityIsGrantedWithExpectation(
            $this->matchesRegularExpression('/^ROLE_(USER|ADMIN)$/'),
            $this->any()
        );
        $gqlServices = $this->createGraphQLServices(['security' => $security]);

        $this->assertTrue(
            $this->expressionLanguage->evaluate('isGranted("ROLE_USER")', [TypeGenerator::GRAPHQL_SERVICES => $gqlServices])
        );
    }

    public function testIsGranted(): void
    {
        $this->assertExpressionCompile('isGranted("ROLE_ADMIN")', 'ROLE_ADMIN');
    }
}
