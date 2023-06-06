<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\DependencyInjection\Compiler;

use Redeye\GraphQLBundle\EventListener\TypeDecoratorListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use function array_keys;
use function array_merge;
use function implode;
use function krsort;
use function sprintf;

final class ResolverMapTaggedServiceMappingPass implements CompilerPassInterface
{
    private const SERVICE_TAG = 'redeye_graphql.resolver_map';

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        $resolverMapsSortedBySchema = [];
        $typeDecoratorListenerDefinition = $container->getDefinition(TypeDecoratorListener::class);

        /** @var array $resolverMapsBySchemas */
        $resolverMapsBySchemas = $container->getParameter('redeye_graphql.resolver_maps');

        foreach ($container->findTaggedServiceIds(self::SERVICE_TAG, true) as $serviceId => $tags) {
            foreach ($tags as $tag) {
                if (!isset($tag['schema'])) {
                    throw new RuntimeException(sprintf('The "schema" attribute on the "redeye_graphql.resolver_map" tag of the "%s" service is required.', $serviceId));
                }

                if (!isset($resolverMapsBySchemas[$tag['schema']])) {
                    throw new RuntimeException(sprintf('Service "%s" is invalid: schema "%s" specified on the tag "%s" does not exist (known ones are: "%s").', $serviceId, $tag['schema'], self::SERVICE_TAG, implode('", "', array_keys($resolverMapsBySchemas))));
                }

                $resolverMapsBySchemas[$tag['schema']][$serviceId] = $tag['priority'] ?? ($resolverMapsBySchemas[$tag['schema']][$serviceId] ?? 0);
            }
        }

        foreach ($resolverMapsBySchemas as $schema => $resolverMaps) {
            foreach ($resolverMaps as $resolverMap => $priority) {
                $resolverMapsSortedBySchema[$schema][$priority][] = $resolverMap;
            }
        }

        foreach ($resolverMapsSortedBySchema as $schema => $resolverMaps) {
            krsort($resolverMaps);

            $resolverMaps = array_merge(...$resolverMaps);

            foreach ($resolverMaps as $index => $resolverMap) {
                $resolverMaps[$index] = new Reference($resolverMap);
            }

            $typeDecoratorListenerDefinition->addMethodCall('addSchemaResolverMaps', [
                $schema,
                $resolverMaps,
            ]);
        }

        $container->getParameterBag()->remove('redeye_graphql.resolver_maps');
    }
}
