# Quick Start

This is the shortest realistic path from Symfony user entity to working Keycloak integration.

The idea is:

1. configure only the required bundle fields
2. let Doctrine tell the bundle which local field is the canonical user identifier
3. bootstrap that identifier as a Keycloak user-profile attribute once
4. use `KeycloakServiceInterface` for create, update, and delete operations

## Prerequisites

- your user entity is managed by Doctrine
- your user entity implements `Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface`
- Symfony container already exposes:
  `Doctrine\Persistence\ManagerRegistry`,
  `Psr\Http\Client\ClientInterface`,
  `Psr\Http\Message\RequestFactoryInterface`,
  `Psr\Http\Message\StreamFactoryInterface`

## 1. Minimal Bundle Config

```yaml
# config/packages/keycloak_bridge.yaml
keycloak_bridge:
  base_url: '%env(KEYCLOAK_BASE_URL)%'
  client_realm: '%env(KEYCLOAK_CLIENT_REALM)%'
  client_id: '%env(KEYCLOAK_CLIENT_ID)%'
  client_secret: '%env(KEYCLOAK_CLIENT_SECRET)%'
  user_entities:
    App\Entity\User:
      realm: '%env(KEYCLOAK_USERS_REALM)%'
```

What you are not configuring here on purpose:

- no `user_identifier_field`
- no `attributes_map`
- no `mapper`
- no role prefix/suffix

The bundle fills the gap automatically:

- Doctrine resolves the entity identifier field
- that identifier becomes a Keycloak attribute automatically
- the same identifier is expected in JWT payload automatically
- the default mapper is used automatically

If your Doctrine identifier field is `id`, then the default Keycloak attribute name is also `id`.
If your identifier field is `uuid`, the default Keycloak attribute name becomes `uuid`.

```mermaid
flowchart TD
    A[Minimal keycloak_bridge config] --> B[Doctrine resolves entity identifier]
    B --> C[Bridge builds UserEntityConfig]
    C --> D[LocalEntityMapper projects identifier into Keycloak attributes]
    C --> E[KeycloakJwtAuthenticator expects the same claim in JWT]
```

## 2. Bootstrap the Identifier Attribute in Keycloak

The bridge does not call Keycloak bootstrap operations automatically.
This is deliberate: Keycloak schema and protocol-mapper changes should be explicit.

The usual pattern is to run this once during deployment, migration, or bootstrap.

```php
<?php

declare(strict_types=1);

namespace App\Keycloak;

use Apacheborys\SymfonyKeycloakBridgeBundle\Service\KeycloakBootstrapper;
use App\Entity\User;

final readonly class KeycloakSetup
{
    public function __construct(
        private KeycloakBootstrapper $keycloakBootstrapper,
    ) {
    }

    public function bootstrap(): void
    {
        $this->keycloakBootstrapper->ensureUserIdentifierAttribute(User::class);
    }
}
```

Notes:

- the bridge resolves realm, attribute name, display name, and JWT claim from bundle configuration
- if your Doctrine identifier field is not `id`, the bridge still resolves it automatically
- if you customized identifier mapping through `attributes_map`, the bootstrapper uses that configuration too

## 3. Create, Update, and Delete Users

Once the attribute is bootstrapped, the service API is intentionally small.

```php
<?php

declare(strict_types=1);

namespace App\Keycloak;

use App\Entity\User;
use Apacheborys\KeycloakPhpClient\DTO\PasswordDto;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceInterface;

final readonly class KeycloakUserLifecycle
{
    public function __construct(
        private KeycloakServiceInterface $keycloak,
    ) {
    }

    public function create(User $user, string $plainPassword): void
    {
        $this->keycloak->createUser(
            localUser: $user,
            passwordDto: new PasswordDto(plainPassword: $plainPassword),
        );
    }

    public function update(User $before, User $after): void
    {
        $this->keycloak->updateUser(
            oldUserVersion: $before,
            newUserVersion: $after,
        );
    }

    public function delete(User $user): void
    {
        $this->keycloak->deleteUser(user: $user);
    }
}
```

The default bridge behavior here is:

- `createUser()` resolves realm from `user_entities`
- `createUser()` sends mapped attributes automatically, including the Doctrine identifier
- `updateUser()` computes the diff between old and new local user versions
- `deleteUser()` uses the local entity ID as the Keycloak user ID reference

```mermaid
sequenceDiagram
    autonumber
    participant App as Symfony App
    participant Bridge as Bridge Mapper
    participant Bootstrap as KeycloakBootstrapper
    participant Client as keycloak-php-client
    participant KC as Keycloak

    App->>Bootstrap: ensureUserIdentifierAttribute(User::class)
    Bootstrap->>KC: create/update user-profile attribute + JWT mapper

    App->>Client: createUser(user, password)
    Client->>Bridge: map entity -> CreateUserProfileDto
    Bridge->>KC: create user

    App->>Client: updateUser(before, after)
    Client->>Bridge: map diff -> UpdateUserDto
    Bridge->>KC: update user

    App->>Client: deleteUser(user)
    Client->>Bridge: map entity -> DeleteUserDto
    Bridge->>KC: delete user
```

## 4. Optional Next Steps

If you want more than the default happy path:

- [Configuration Guide](configuration.md)
  for `attributes_map`, role prefix/suffix, custom mapper classes, and infrastructure services
- [Security Guide](security.md)
  for JWT authentication inside Symfony Security
