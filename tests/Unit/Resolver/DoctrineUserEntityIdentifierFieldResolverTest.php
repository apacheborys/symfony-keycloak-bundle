<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Unit\Resolver;

use Apacheborys\SymfonyKeycloakBridgeBundle\Resolver\DoctrineUserEntityIdentifierFieldResolver;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\Doctrine\TestManagerRegistry;
use Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\LocalUser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DoctrineUserEntityIdentifierFieldResolverTest extends TestCase
{
    public function testResolvesDoctrineScalarIdentifierField(): void
    {
        $resolver = new DoctrineUserEntityIdentifierFieldResolver(
            new TestManagerRegistry([
                LocalUser::class => 'id',
            ])
        );

        self::assertSame('id', $resolver->resolve(LocalUser::class));
    }

    public function testRejectsEntityWithoutDoctrineMetadata(): void
    {
        $resolver = new DoctrineUserEntityIdentifierFieldResolver(new TestManagerRegistry([]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Doctrine metadata for configured user entity');
        $resolver->resolve(LocalUser::class);
    }

    public function testRejectsCompositeDoctrineIdentifier(): void
    {
        $resolver = new DoctrineUserEntityIdentifierFieldResolver(
            new TestManagerRegistry([
                LocalUser::class => ['id', 'localIdentifier'],
            ])
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('uses a composite Doctrine identifier');
        $resolver->resolve(LocalUser::class);
    }

    public function testRejectsAssociationDoctrineIdentifier(): void
    {
        $resolver = new DoctrineUserEntityIdentifierFieldResolver(
            new TestManagerRegistry([
                LocalUser::class => [
                    'identifier_fields' => ['id'],
                    'association_names' => ['id'],
                    'field_names' => [],
                ],
            ])
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('uses Doctrine association identifier');
        $resolver->resolve(LocalUser::class);
    }
}
