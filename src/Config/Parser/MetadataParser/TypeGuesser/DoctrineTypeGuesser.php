<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Config\Parser\MetadataParser\TypeGuesser;

use Doctrine\ORM\Mapping\Annotation as MappingAnnotation;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use Redeye\GraphQLBundle\Config\Parser\AnnotationParser;
use Redeye\GraphQLBundle\Config\Parser\MetadataParser\ClassesTypesMap;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Reflector;

class DoctrineTypeGuesser extends TypeGuesser
{
    protected array $doctrineMapping = [];

    public function __construct(ClassesTypesMap $map, array $doctrineMapping = [])
    {
        parent::__construct($map);
        $this->doctrineMapping = $doctrineMapping;
    }

    public function getName(): string
    {
        return 'Doctrine annotations ';
    }

    public function supports(Reflector $reflector): bool
    {
        return $reflector instanceof ReflectionProperty;
    }

    /**
     * @param ReflectionProperty $reflector
     */
    public function guessType(ReflectionClass $reflectionClass, Reflector $reflector, array $filterGraphQLTypes = []): ?string
    {
        if (!class_exists(Column::class)) {
            throw new TypeGuessingException(sprintf('You must install doctrine/orm package to use this type guesser.'));
        }

        if (!$reflector instanceof ReflectionProperty) {
            throw new TypeGuessingException('Doctrine type guesser only apply to properties.');
        }

        /** @var Column|null $columnAnnotation */
        $columnAnnotation = $this->getAnnotation($reflector, Column::class);

        if (null !== $columnAnnotation) {
            $type = $this->resolveTypeFromDoctrineType($columnAnnotation->type ?: 'string');
            $nullable = $columnAnnotation->nullable;
            if ($type) {
                return $nullable ? $type : sprintf('%s!', $type);
            } else {
                throw new TypeGuessingException(sprintf('Unable to auto-guess GraphQL type from Doctrine type "%s"', $columnAnnotation->type));
            }
        }

        $associationAnnotations = [
            OneToMany::class => true,
            OneToOne::class => false,
            ManyToMany::class => true,
            ManyToOne::class => false,
        ];

        foreach ($associationAnnotations as $associationClass => $isMultiple) {
            /** @var OneToMany|OneToOne|ManyToMany|ManyToOne|null $associationAnnotation */
            $associationAnnotation = $this->getAnnotation($reflector, $associationClass);
            if (null !== $associationAnnotation) {
                $target = $this->fullyQualifiedClassName($associationAnnotation->targetEntity, $reflectionClass->getNamespaceName());
                $type = $this->map->resolveType($target, ['type']);

                if ($type) {
                    $isMultiple = $associationAnnotations[get_class($associationAnnotation)];
                    if ($isMultiple) {
                        return sprintf('[%s]!', $type);
                    } else {
                        $isNullable = false;
                        /** @var JoinColumn|null $joinColumn */
                        $joinColumn = $this->getAnnotation($reflector, JoinColumn::class);
                        if (null !== $joinColumn) {
                            $isNullable = $joinColumn->nullable;
                        }

                        return sprintf('%s%s', $type, $isNullable ? '' : '!');
                    }
                } else {
                    throw new TypeGuessingException(sprintf('Unable to auto-guess GraphQL type from Doctrine target class "%s" (check if the target class is a GraphQL type itself (with a @Metadata\Type metadata).', $target));
                }
            }
        }
        throw new TypeGuessingException(sprintf('No Doctrine ORM annotation found.'));
    }

    private function getAnnotation(Reflector $reflector, string $annotationClass): ?MappingAnnotation
    {
        $reader = AnnotationParser::getAnnotationReader();
        $annotations = [];
        switch (true) {
            case $reflector instanceof ReflectionClass: $annotations = $reader->getClassAnnotations($reflector); break;
            case $reflector instanceof ReflectionMethod: $annotations = $reader->getMethodAnnotations($reflector); break;
            case $reflector instanceof ReflectionProperty: $annotations = $reader->getPropertyAnnotations($reflector); break;
        }
        foreach ($annotations as $annotation) {
            if ($annotation instanceof $annotationClass) {
                /** @var MappingAnnotation $annotation */
                return $annotation;
            }
        }

        return null;
    }

    /**
     * Resolve a FQN from classname and namespace.
     *
     * @internal
     */
    public function fullyQualifiedClassName(string $className, string $namespace): string
    {
        if (!str_contains($className, '\\') && $namespace) {
            return $namespace.'\\'.$className;
        }

        return $className;
    }

    /**
     * Resolve a GraphQLType from a doctrine type.
     */
    private function resolveTypeFromDoctrineType(string $doctrineType): ?string
    {
        if (isset($this->doctrineMapping[$doctrineType])) {
            return $this->doctrineMapping[$doctrineType];
        }

        switch ($doctrineType) {
            case 'integer':
            case 'smallint':
            case 'bigint':
                return 'Int';
            case 'string':
            case 'text':
                return 'String';
            case 'bool':
            case 'boolean':
                return 'Boolean';
            case 'float':
            case 'decimal':
                return 'Float';
            default:
                return null;
        }
    }
}
