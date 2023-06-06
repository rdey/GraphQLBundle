<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Relay\Node;

use Redeye\GraphQLBundle\Definition\Builder\MappingInterface;
use function is_string;
use function substr;
use function var_export;

final class GlobalIdFieldDefinition implements MappingInterface
{
    public function toMappingDefinition(array $config): array
    {
        $typeName = isset($config['typeName']) && is_string($config['typeName']) ? var_export($config['typeName'], true) : 'null';
        $idFetcher = isset($config['idFetcher']) && is_string($config['idFetcher']) ? $this->cleanIdFetcher($config['idFetcher']) : 'null';

        return [
            'description' => 'The ID of an object',
            'type' => 'ID!',
            'resolve' => "@=query('relay_globalid_field', value, info, $idFetcher, $typeName)",
        ];
    }

    private function cleanIdFetcher(string $idFetcher): string
    {
        $cleanIdFetcher = $idFetcher;

        if (str_starts_with($idFetcher, '@=')) {
            $cleanIdFetcher = substr($idFetcher, 2);
        }

        return $cleanIdFetcher;
    }
}
