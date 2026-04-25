<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Resolver;

use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;

final readonly class DoctrineUserEntityIdentifierFieldResolver implements UserEntityIdentifierFieldResolverInterface
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
    ) {
    }

    #[\Override]
    public function resolve(string $className): string
    {
        $manager = $this->managerRegistry->getManagerForClass($className);
        if ($manager === null) {
            throw new InvalidArgumentException(
                sprintf(
                    'Doctrine metadata for configured user entity "%s" was not found. '
                    . 'The Keycloak bridge can resolve user identifiers only for Doctrine-managed entities.',
                    $className,
                )
            );
        }

        $metadata = $manager->getClassMetadata($className);
        $identifierFieldNames = $metadata->getIdentifierFieldNames();

        if ($identifierFieldNames === []) {
            throw new InvalidArgumentException(
                sprintf(
                    'Doctrine metadata for configured user entity "%s" does not define an identifier field.',
                    $className,
                )
            );
        }

        if (count($identifierFieldNames) > 1) {
            throw new InvalidArgumentException(
                sprintf(
                    'Configured user entity "%s" uses a composite Doctrine identifier, '
                    . 'which is not supported by the Keycloak bridge.',
                    $className,
                )
            );
        }

        $identifierFieldName = $identifierFieldNames[0];
        if ($metadata->hasAssociation($identifierFieldName)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Configured user entity "%s" uses Doctrine association identifier "%s", '
                    . 'which is not supported by the Keycloak bridge.',
                    $className,
                    $identifierFieldName,
                )
            );
        }

        if (!$metadata->hasField($identifierFieldName)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Configured user entity "%s" must use a scalar Doctrine field as its identifier. '
                    . 'Resolved identifier "%s" is not a mapped field.',
                    $className,
                    $identifierFieldName,
                )
            );
        }

        return $identifierFieldName;
    }
}
