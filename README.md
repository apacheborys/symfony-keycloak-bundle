# Symfony Keycloak Bridge Bundle

This bundle wires `apacheborys/keycloak-php-client` into Symfony and exposes its services via DI.

## Install (local dev)

```bash
composer require apacheborys/symfony-keycloak-bundle
```

## Enable the bundle

```php
// config/bundles.php
return [
    Apacheborys\SymfonyKeycloakBridgeBundle\KeycloakBridgeBundle::class => ['all' => true],
];
```

## Configuration

```yaml
# config/packages/keycloak_bridge.yaml
keycloak_bridge:
  base_url: '%env(KEYCLOAK_BASE_URL)%'
  client_realm: '%env(KEYCLOAK_CLIENT_REALM)%'
  client_id: '%env(KEYCLOAK_CLIENT_ID)%'
  client_secret: '%env(KEYCLOAK_CLIENT_SECRET)%'
  http_client_service: 'http_client' # PSR-18 client service id
  request_factory_service: 'psr17.request_factory' # PSR-17 request factory id
  stream_factory_service: 'psr17.stream_factory' # PSR-17 stream factory id
  cache_pool: 'cache.app' # optional PSR-6 cache pool id
  logger_service: 'logger' # optional PSR-3 logger service id
  allow_role_creation: false # allow creating missing roles in Keycloak during sync
  realm_list_ttl: 3600
  user_entities:
    App\Entity\User:
      realm: '%env(KEYCLOAK_USERS_REALM)%'
      role_prefix: 'payment.' # optional
      role_suffix: '.svc' # optional
      mapper: Apacheborys\SymfonyKeycloakBridgeBundle\Mapper\LocalEntityMapper # optional
```

If you omit any of the service IDs, the bundle will rely on container aliases for the corresponding PSR interfaces.
Make sure your app provides PSR-18 + PSR-17 implementations (and PSR-6 cache if you enable caching).

## Services

You can autowire these interfaces:

- `Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface`
- `Apacheborys\KeycloakPhpClient\Service\KeycloakServiceInterface`
- `Apacheborys\KeycloakPhpClient\Service\KeycloakJwtVerificationServiceInterface`
- `Apacheborys\KeycloakPhpClient\Service\KeycloakOidcAuthenticationServiceInterface`
- `Apacheborys\KeycloakPhpClient\Service\KeycloakUserManagementServiceInterface`
- `Apacheborys\KeycloakPhpClient\Service\KeycloakRealmServiceInterface`
- `Apacheborys\SymfonyKeycloakBridgeBundle\Security\KeycloakJwtAuthenticator`

User mappers must implement `Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface`
and are tagged as `keycloak.local_user_mapper`. The bundled `LocalEntityMapper` is wired when
`user_entities` is configured.

Per-entity mapper selection is supported via `user_entities.<Entity>.mapper`:
- defaults to `Apacheborys\SymfonyKeycloakBridgeBundle\Mapper\LocalEntityMapper`
- can point to your custom mapper service class for specific entities

`LocalEntityMapper` supports:
- `getRealm`
- `prepareLocalUserForKeycloakUserCreation`
- `prepareLocalUserForKeycloakLoginUser`
- `prepareLocalUserForKeycloakUserDeletion`
- `prepareLocalUserDiffForKeycloakUserUpdate`

`prepareLocalUserForKeycloakLoginUser` builds `OidcTokenRequestDto` using:
- entity realm from `user_entities`
- `client_id` and `client_secret` from bundle config
- local user username + provided plain password

Role synchronization mapping is also built in:
- local Symfony role names are projected to `RoleDto`
- optional `role_prefix` and `role_suffix` are applied before projecting roles to Keycloak
- existing Keycloak roles are reused by name
- unknown roles become lightweight `RoleDto` objects and can be auto-created when
  `allow_role_creation: true`

If your login flow needs custom fields/scope/grant behavior, point `user_entities.<Entity>.mapper`
to your mapper class. The bundle will tag it as `keycloak.local_user_mapper` automatically.
If your mapper needs custom constructor arguments, define it as a Symfony service explicitly.

## Security Authenticator

The bundle provides `Apacheborys\SymfonyKeycloakBridgeBundle\Security\KeycloakJwtAuthenticator`.
It:

- reads bearer JWT from `Authorization` header
- checks token `iss` matches configured Keycloak `base_url`
- verifies signature and temporal claims via `KeycloakJwtVerificationServiceInterface`
- converts Keycloak realm/resource roles into Symfony user roles

Example firewall setup:

```yaml
# config/packages/security.yaml
security:
  firewalls:
    api:
      stateless: true
      custom_authenticators:
        - Apacheborys\SymfonyKeycloakBridgeBundle\Security\KeycloakJwtAuthenticator
```

## Development

```bash
composer install
composer check
```

To enable git hooks:

```bash
git config core.hooksPath .githooks
```
