<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Resolver;

use function is_array;

abstract class AbstractProxyResolver extends AbstractResolver
{
    /**
     * @param mixed $input
     *
     * @return mixed
     */
    public function resolve($input)
    {
        if (!is_array($input)) {
            $input = [$input];
        }

        $alias = $input[0] ?? '';
        $funcArgs = $input[1] ?? [];

        $solution = $this->getSolution($alias);

        if (null === $solution) {
            throw new UnresolvableException($this->unresolvableMessage($alias));
        }

        $options = $this->getSolutionOptions($alias);

        /** @var callable $func */
        $func = [$solution, $options['method']];

        return $func(...$funcArgs);
    }

    abstract protected function unresolvableMessage(string $alias): string;
}
