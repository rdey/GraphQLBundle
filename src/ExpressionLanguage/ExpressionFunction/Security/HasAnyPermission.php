<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\Security;

use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction;
use Redeye\GraphQLBundle\Generator\TypeGenerator;

final class HasAnyPermission extends ExpressionFunction
{
    public function __construct()
    {
        parent::__construct(
            'hasAnyPermission',
            fn ($object, $permissions) => "$this->gqlServices->get('security')->hasAnyPermission($object, $permissions)",
            static fn (array $arguments, $object, $permissions) => $arguments[TypeGenerator::GRAPHQL_SERVICES]->get('security')->hasAnyPermission($object, $permissions)
        );
    }
}
