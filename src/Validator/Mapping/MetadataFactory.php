<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Validator\Mapping;

use Redeye\GraphQLBundle\Validator\ValidationNode;
use Symfony\Component\Validator\Exception\NoSuchMetadataException;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;

class MetadataFactory implements MetadataFactoryInterface
{
    private array $metadataPool;

    public function __construct()
    {
        $this->metadataPool = [];
    }

    /**
     * @param mixed $object
     */
    public function getMetadataFor($object): ObjectMetadata
    {
        if ($object instanceof ValidationNode) {
            return $this->metadataPool[$object->getName()];
        }

        throw new NoSuchMetadataException();
    }

    /**
     * @param mixed $object
     */
    public function hasMetadataFor($object): bool
    {
        if ($object instanceof ValidationNode) {
            return isset($this->metadataPool[$object->getName()]);
        }

        return false;
    }

    public function addMetadata(ObjectMetadata $metadata): void
    {
        $this->metadataPool[$metadata->getClassName()] = $metadata;
    }
}
