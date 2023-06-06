<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\GraphQL;

use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction;

/**
 * @deprecated since 0.14 and will be removed in 1.0. Use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\GraphQL\Query instead.
 */
final class Resolver extends ExpressionFunction
{
    public function __construct($name = 'resolver')
    {
        parent::__construct(
            $name,
            function (string $alias, string $args = '[]') {
                @trigger_error(
                    "The 'resolver' expression function is deprecated since 0.14 and will be removed in 1.0. Use 'query' instead. For more info visit: https://github.com/redeye/GraphQLBundle/issues/775",
                    E_USER_DEPRECATED
                );

                return "$this->gqlServices->query($alias, ...$args)";
            }
        );
    }
}
