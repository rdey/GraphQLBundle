<?php

declare(strict_types=1);

namespace Overblog\GraphQLBundle\Tests\Definition\Type;

use Exception;
use Generator;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use Overblog\GraphQLBundle\Definition\Type\CustomScalarType;
use Overblog\GraphQLBundle\Tests\Functional\App\Type\YearScalarType;
use PHPUnit\Framework\TestCase;
use stdClass;
use function sprintf;
use function uniqid;

final class CustomScalarTypeTest extends TestCase
{
    public function testScalarTypeConfig(): void
    {
        $this->assertScalarTypeConfig(new YearScalarType());
        $this->assertScalarTypeConfig(fn () => new YearScalarType());
    }

    public function testWithoutScalarTypeConfig(): void
    {
        $genericFunc = fn ($value) => $value;
        /** @phpstan-ignore-next-line */
        $type = new CustomScalarType([
            'serialize' => $genericFunc,
            'parseValue' => $genericFunc,
            'parseLiteral' => $genericFunc,
        ]);

        foreach (['serialize', 'parseValue', 'parseLiteral'] as $field) {
            $value = new ScalarTypeDefinitionNode([]);
            $this->assertSame($value, $type->$field($value));
        }
    }

    /**
     * @param mixed  $scalarType
     * @param string $got
     *
     * @dataProvider invalidScalarTypeProvider
     */
    public function testAssertValidWithInvalidScalarType($scalarType, $got): void
    {
        $this->expectException(InvariantViolation::class);
        $name = uniqid('custom');
        $this->expectExceptionMessage(sprintf(
            '%s must provide a valid "scalarType" instance of %s but got: %s',
            $name,
            ScalarType::class,
            $got
        ));
        $type = new CustomScalarType(['name' => $name, 'scalarType' => $scalarType, 'serialize' => fn (mixed $input): mixed => '']);
        $type->assertValid();
    }

    public function invalidScalarTypeProvider(): Generator
    {
        yield [false, 'false'];
        yield [new stdClass(), 'instance of stdClass'];
        yield [
            fn () => false,
            'false',
        ];
        yield [
            fn () => new stdClass(),
            'instance of stdClass',
        ];
    }

    /**
     * @param mixed $scalarType
     *
     * @throws Exception
     */
    private function assertScalarTypeConfig($scalarType): void
    {
        $type = new CustomScalarType([
            'scalarType' => $scalarType,
            'serialize' => fn () => 'serialize',
            'parseValue' => fn () => 'parseValue',
            'parseLiteral' => fn () => 'parseLiteral',
        ]);

        $this->assertSame('50 AC', $type->serialize(50));
        $this->assertSame(50, $type->parseValue('50 AC'));
        $this->assertSame(50, $type->parseLiteral(new StringValueNode(['value' => '50 AC'])));
    }
}
