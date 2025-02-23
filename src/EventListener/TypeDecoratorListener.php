<?php

declare(strict_types=1);

namespace Overblog\GraphQLBundle\EventListener;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use InvalidArgumentException;
use Overblog\GraphQLBundle\Definition\ArgumentFactory;
use Overblog\GraphQLBundle\Definition\Type\CustomScalarType;
use Overblog\GraphQLBundle\Event\TypeLoadedEvent;
use Overblog\GraphQLBundle\Resolver\ResolverMapInterface;
use Overblog\GraphQLBundle\Resolver\ResolverMaps;
use Traversable;
use function array_diff;
use function count;
use function current;
use function implode;
use function is_callable;
use function sprintf;
use function substr;

final class TypeDecoratorListener
{
    private ArgumentFactory $argumentFactory;
    private array $schemaResolverMaps = [];

    public function __construct(ArgumentFactory $argumentFactory)
    {
        $this->argumentFactory = $argumentFactory;
    }

    public function addSchemaResolverMaps(string $schemaName, array $resolverMaps): void
    {
        $this->schemaResolverMaps[$schemaName] = 1 === count($resolverMaps) ? current($resolverMaps) : new ResolverMaps($resolverMaps);
    }

    public function onTypeLoaded(TypeLoadedEvent $event): void
    {
        if (!empty($this->schemaResolverMaps[$event->getSchemaName()])) {
            $this->decorateType($event->getType(), $this->schemaResolverMaps[$event->getSchemaName()]);
        }
    }

    public function decorateType(Type $type, ResolverMapInterface $resolverMap): void
    {
        if ($type instanceof ObjectType) {
            $this->decorateObjectType($type, $resolverMap);
        } elseif ($type instanceof InterfaceType || $type instanceof UnionType) {
            $this->decorateInterfaceOrUnionType($type, $resolverMap);
        } elseif ($type instanceof EnumType) {
            $this->decorateEnumType($type, $resolverMap);
        } elseif ($type instanceof CustomScalarType) {
            $this->decorateCustomScalarType($type, $resolverMap);
        } else {
            $covered = $resolverMap->covered($type instanceof NamedType ? $type->name : null);
            if (!empty($covered)) {
                throw new InvalidArgumentException(
                    sprintf(
                        '"%s".{"%s"} defined in resolverMap, but type is not managed by TypeDecorator.',
                        $type instanceof NamedType ? $type->name : get_class($type),
                        implode('", "', $covered)
                    )
                );
            }
        }
    }

    private function decorateObjectType(ObjectType $type, ResolverMapInterface $resolverMap): void
    {
        $fieldsResolved = [];
        foreach ($resolverMap->covered($type->name) as $fieldName) {
            if (ResolverMapInterface::IS_TYPEOF === $fieldName) {
                $this->configTypeMapping($type, ResolverMapInterface::IS_TYPEOF, $resolverMap);
            } elseif (ResolverMapInterface::RESOLVE_FIELD === $fieldName) {
                $resolveFieldFn = $this->argumentFactory->wrapResolverArgs($resolverMap->resolve($type->name, ResolverMapInterface::RESOLVE_FIELD));
                $type->config['resolveField'] = $resolveFieldFn;
                $type->resolveFieldFn = $resolveFieldFn;
            } else {
                $fieldsResolved[] = $fieldName;
            }
        }
        $this->decorateObjectTypeFields($type, $fieldsResolved, $resolverMap);
    }

    private function decorateInterfaceOrUnionType(InterfaceType|UnionType $type, ResolverMapInterface $resolverMap): void
    {
        $this->configTypeMapping($type, ResolverMapInterface::RESOLVE_TYPE, $resolverMap);
        $covered = $resolverMap->covered($type->name);
        if (!empty($covered)) {
            $unknownFields = array_diff($covered, [ResolverMapInterface::RESOLVE_TYPE]);
            if (!empty($unknownFields)) {
                throw new InvalidArgumentException(
                    sprintf(
                        '"%s".{"%s"} defined in resolverMap, but only "%s::RESOLVE_TYPE" is allowed.',
                        $type->name,
                        implode('", "', $unknownFields),
                        ResolverMapInterface::class
                    )
                );
            }
        }
    }

    private function decorateCustomScalarType(CustomScalarType $type, ResolverMapInterface $resolverMap): void
    {
        static $allowedFields = [
            ResolverMapInterface::SCALAR_TYPE,
            ResolverMapInterface::SERIALIZE,
            ResolverMapInterface::PARSE_VALUE,
            ResolverMapInterface::PARSE_LITERAL,
        ];

        foreach ($allowedFields as $fieldName) {
            $this->configTypeMapping($type, $fieldName, $resolverMap);
        }

        $unknownFields = array_diff($resolverMap->covered($type->name), $allowedFields);
        if (!empty($unknownFields)) {
            throw new InvalidArgumentException(
                sprintf(
                    '"%s".{"%s"} defined in resolverMap, but only "%s::{%s}" is allowed.',
                    $type->name,
                    implode('", "', $unknownFields),
                    ResolverMapInterface::class,
                    implode(', ', ['SCALAR_TYPE', 'SERIALIZE', 'PARSE_VALUE', 'PARSE_LITERAL'])
                )
            );
        }
    }

    private function decorateEnumType(EnumType $type, ResolverMapInterface $resolverMap): void
    {
        $values = $type->config['values'];

        $decoratedValues = function () use ($type, $resolverMap, $values) {
            if (is_callable($values)) {
                $values = $values();
            }

            // Convert a Generator to an array so that can modify it (by reference)
            // and return the new array.
            $values = $values instanceof Traversable ? iterator_to_array($values) : (array) $values;

            $fieldNames = [];
            foreach ($values as $key => &$value) {
                $fieldName = $value['name'] ?? $key;
                if ($resolverMap->isResolvable($type->name, $fieldName)) {
                    $value['value'] = $resolverMap->resolve($type->name, $fieldName);
                }
                $fieldNames[] = $fieldName;
            }
            $unknownFields = array_diff($resolverMap->covered($type->name), $fieldNames);
            if (!empty($unknownFields)) {
                throw new InvalidArgumentException(
                    sprintf(
                        '"%s".{"%s"} defined in resolverMap, was defined in resolvers, but enum is not in schema.',
                        $type->name,
                        implode('", "', $unknownFields)
                    )
                );
            }

            return $values;
        };

        /** @phpstan-ignore-next-line see https://github.com/webonyx/graphql-php/issues/1041  */
        $type->config['values'] = is_callable($values) ? $decoratedValues : $decoratedValues();
    }

    private function decorateObjectTypeFields(ObjectType $type, array $fieldsResolved, ResolverMapInterface $resolverMap): void
    {
        $fields = $type->config['fields'];

        $decoratedFields = function () use ($fields, $type, $fieldsResolved, $resolverMap) {
            if (is_callable($fields)) {
                $fields = $fields();
            }

            // Convert a Generator to an array so that can modify it (by reference)
            // and return the new array.
            $fields = $fields instanceof Traversable ? iterator_to_array($fields) : (array) $fields;

            $fieldNames = [];
            foreach ($fields as $key => &$field) {
                $fieldName = $field['name'] ?? $key;

                if ($resolverMap->isResolvable($type->name, $fieldName)) {
                    $field['resolve'] = $this->argumentFactory->wrapResolverArgs($resolverMap->resolve($type->name, $fieldName));
                }

                $fieldNames[] = $fieldName;
            }

            $unknownFields = array_diff($fieldsResolved, $fieldNames);
            if (!empty($unknownFields)) {
                throw new InvalidArgumentException(
                    sprintf('"%s".{"%s"} defined in resolverMap, but not in schema.', $type->name, implode('", "', $unknownFields))
                );
            }

            return $fields;
        };

        $type->config['fields'] = is_callable($fields) ? $decoratedFields : $decoratedFields();
    }

    private function configTypeMapping(ObjectType|InterfaceType|UnionType|CustomScalarType $type, string $fieldName, ResolverMapInterface $resolverMap): void
    {
        if ($resolverMap->isResolvable($type->name, $fieldName)) {
            /** @phpstan-ignore-next-line */
            $type->config[substr($fieldName, 2)] = $resolverMap->resolve($type->name, $fieldName);
        }
    }
}
