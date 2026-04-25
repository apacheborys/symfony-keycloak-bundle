<?php

declare(strict_types=1);

namespace Apacheborys\SymfonyKeycloakBridgeBundle\Tests\Stub\Doctrine;

use BadMethodCallException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use InvalidArgumentException;
use stdClass;

final class TestManagerRegistry implements ManagerRegistry
{
    /** @var array<class-string, TestClassMetadata> */
    private array $metadataByClass;

    private readonly ObjectManager $manager;

    /**
     * @param array<class-string, string|list<string>|array{
     *  identifier_fields: list<string>,
     *  association_names?: list<string>,
     *  field_names?: list<string>
     * }> $metadataConfigByClass
     */
    public function __construct(array $metadataConfigByClass)
    {
        $metadataByClass = [];
        foreach ($metadataConfigByClass as $className => $metadataConfig) {
            [$identifierFields, $fieldNames, $associationNames] = $this->normalizeMetadataConfig($metadataConfig);

            $metadataByClass[$className] = new TestClassMetadata(
                className: $className,
                identifierFields: $identifierFields,
                fieldNames: $fieldNames,
                associationNames: $associationNames,
            );
        }

        $this->metadataByClass = $metadataByClass;
        $this->manager = $this->buildObjectManager();
    }

    #[\Override]
    public function getDefaultConnectionName(): string
    {
        return 'default';
    }

    #[\Override]
    public function getConnection(?string $name = null): object
    {
        return new stdClass();
    }

    #[\Override]
    public function getConnections(): array
    {
        return ['default' => $this->getConnection()];
    }

    #[\Override]
    public function getConnectionNames(): array
    {
        return ['default' => 'default_connection'];
    }

    #[\Override]
    public function getDefaultManagerName(): string
    {
        return 'default';
    }

    #[\Override]
    public function getManager(?string $name = null): ObjectManager
    {
        return $this->manager;
    }

    #[\Override]
    public function getManagers(): array
    {
        return ['default' => $this->manager];
    }

    #[\Override]
    public function resetManager(?string $name = null): ObjectManager
    {
        return $this->manager;
    }

    #[\Override]
    public function getManagerNames(): array
    {
        return ['default' => 'default_manager'];
    }

    #[\Override]
    public function getRepository(string $persistentObject, ?string $persistentManagerName = null): ObjectRepository
    {
        return $this->manager->getRepository($persistentObject);
    }

    #[\Override]
    public function getManagerForClass(string $class): ?ObjectManager
    {
        return isset($this->metadataByClass[$class]) ? $this->manager : null;
    }

    /**
     * @param string|list<string>|array{
     *  identifier_fields: list<string>,
     *  association_names?: list<string>,
     *  field_names?: list<string>
     * } $metadataConfig
     * @return array{list<string>, list<string>, list<string>}
     */
    private function normalizeMetadataConfig(string|array $metadataConfig): array
    {
        if (is_string($metadataConfig)) {
            return [[$metadataConfig], [$metadataConfig], []];
        }

        if (array_is_list($metadataConfig)) {
            /** @var list<string> $metadataConfig */
            return [$metadataConfig, $metadataConfig, []];
        }

        /** @var array{
         *  identifier_fields: list<string>,
         *  association_names?: list<string>,
         *  field_names?: list<string>
         * } $metadataConfig
         */
        $identifierFields = $metadataConfig['identifier_fields'];
        $associationNames = $metadataConfig['association_names'] ?? [];
        $fieldNames = $metadataConfig['field_names'] ?? array_values(array_diff($identifierFields, $associationNames));

        return [$identifierFields, $fieldNames, $associationNames];
    }

    /**
     * @return ObjectManager
     */
    private function buildObjectManager(): ObjectManager
    {
        $metadataByClass = $this->metadataByClass;

        /**
         * @var ClassMetadataFactory<ClassMetadata<object>> $metadataFactory
         */
        $metadataFactory = new
        /**
         * @implements ClassMetadataFactory<ClassMetadata<object>>
         */
        class ($metadataByClass) implements ClassMetadataFactory {
            /**
             * @param array<class-string, TestClassMetadata> $metadataByClass
             */
            public function __construct(
                private array $metadataByClass,
            ) {
            }

            /**
             * @return list<ClassMetadata<object>>
             */
            public function getAllMetadata(): array
            {
                return array_values($this->metadataByClass);
            }

            /**
             * @return ClassMetadata<object>
             */
            public function getMetadataFor(string $className): ClassMetadata
            {
                if (!isset($this->metadataByClass[$className])) {
                    throw new InvalidArgumentException(
                        sprintf('Metadata for "%s" is not configured.', $className)
                    );
                }

                return $this->metadataByClass[$className];
            }

            public function hasMetadataFor(string $className): bool
            {
                return isset($this->metadataByClass[$className]);
            }

            /**
             * @param ClassMetadata<object> $class
             */
            public function setMetadataFor(string $className, ClassMetadata $class): void
            {
                if (!$class instanceof TestClassMetadata) {
                    throw new InvalidArgumentException('Only TestClassMetadata instances are supported.');
                }

                $this->metadataByClass[$className] = $class;
            }

            public function isTransient(string $className): bool
            {
                return !isset($this->metadataByClass[$className]);
            }
        };

        return new class ($metadataByClass, $metadataFactory) implements ObjectManager {
            /**
             * @param array<class-string, TestClassMetadata> $metadataByClass
             * @param ClassMetadataFactory<ClassMetadata<object>> $metadataFactory
             */
            public function __construct(
                private array $metadataByClass,
                private readonly ClassMetadataFactory $metadataFactory,
            ) {
            }

            public function find(string $className, mixed $id): ?object
            {
                throw new BadMethodCallException('Not implemented for tests.');
            }

            public function persist(object $object): void
            {
                throw new BadMethodCallException('Not implemented for tests.');
            }

            public function remove(object $object): void
            {
                throw new BadMethodCallException('Not implemented for tests.');
            }

            public function clear(): void
            {
            }

            public function detach(object $object): void
            {
            }

            public function refresh(object $object): void
            {
                throw new BadMethodCallException('Not implemented for tests.');
            }

            public function flush(): void
            {
                throw new BadMethodCallException('Not implemented for tests.');
            }

            /**
             * @return ObjectRepository<object>
             */
            public function getRepository(string $className): ObjectRepository
            {
                /** @var ObjectRepository<object> $repository */
                $repository = new
                /**
                 * @implements ObjectRepository<object>
                 */
                class ($className) implements ObjectRepository {
                    /**
                     * @param class-string $className
                     */
                    public function __construct(
                        private readonly string $className,
                    ) {
                    }

                    public function find(mixed $id): ?object
                    {
                        return null;
                    }

                    public function findAll(): array
                    {
                        return [];
                    }

                    public function findBy(
                        array $criteria,
                        ?array $orderBy = null,
                        ?int $limit = null,
                        ?int $offset = null,
                    ): array {
                        return [];
                    }

                    public function findOneBy(array $criteria): ?object
                    {
                        return null;
                    }

                    public function getClassName(): string
                    {
                        return $this->className;
                    }
                };

                return $repository;
            }

            /**
             * @return ClassMetadata<object>
             */
            public function getClassMetadata(string $className): ClassMetadata
            {
                if (!isset($this->metadataByClass[$className])) {
                    throw new InvalidArgumentException(sprintf('Metadata for "%s" is not configured.', $className));
                }

                return $this->metadataByClass[$className];
            }

            /**
             * @return ClassMetadataFactory<ClassMetadata<object>>
             */
            public function getMetadataFactory(): ClassMetadataFactory
            {
                return $this->metadataFactory;
            }

            public function initializeObject(object $obj): void
            {
            }

            public function isUninitializedObject(mixed $value): bool
            {
                return false;
            }

            public function contains(object $object): bool
            {
                return false;
            }
        };
    }
}
