<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Functional\App\Type;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use ReturnTypeWillChange;
use function sprintf;
use function str_replace;

class YearScalarType extends ScalarType
{
    /**
     * {@inheritdoc}
     */
    #[ReturnTypeWillChange]
    public function serialize($value)
    {
        return sprintf('%s AC', $value);
    }

    /**
     * {@inheritdoc}
     */
    #[ReturnTypeWillChange]
    public function parseValue($value)
    {
        return (int) str_replace(' AC', '', $value);
    }

    /**
     * {@inheritdoc}
     */
    #[ReturnTypeWillChange]
    public function parseLiteral($valueNode, array $variables = null)
    {
        if (!$valueNode instanceof StringValueNode) {
            throw new Error('Query error: Can only parse strings got: '.$valueNode->kind, $valueNode);
        }

        return (int) str_replace(' AC', '', $valueNode->value);
    }
}
