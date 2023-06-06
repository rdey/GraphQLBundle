<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Functional\App\IsolatedResolver;

use Redeye\GraphQLBundle\Definition\Resolver\QueryInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

final class EchoQuery implements QueryInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function display(string $message): string
    {
        // @phpstan-ignore-next-line
        return $this->container->getParameter('echo.prefix').$message;
    }
}
