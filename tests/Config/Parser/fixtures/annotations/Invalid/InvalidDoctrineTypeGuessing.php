<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Config\Parser\fixtures\annotations\Invalid;

use Doctrine\ORM\Mapping as ORM;
use Redeye\GraphQLBundle\Annotation as GQL;

/**
 * @GQL\Type
 */
#[GQL\Type]
class InvalidDoctrineTypeGuessing
{
    /**
     * @ORM\Column(type="invalidType")
     * @GQL\Field
     *
     * @var mixed
     */
    #[GQL\Field]
    protected $myRelation;
}
