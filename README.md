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
  realm_list_ttl: 3600
  user_entities:
    App\Entity\User:
      realm: '%env(KEYCLOAK_USERS_REALM)%'
```

If you omit any of the service IDs, the bundle will rely on container aliases for the corresponding PSR interfaces.
Make sure your app provides PSR-18 + PSR-17 implementations (and PSR-6 cache if you enable caching).

## Services

You can autowire these interfaces:

- `Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface`
- `Apacheborys\KeycloakPhpClient\Service\KeycloakServiceInterface`

User mappers must implement `Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface`
and are tagged as `keycloak.local_user_mapper`. The bundled `LocalEntityMapper` is wired when
`user_entities` is configured.

`LocalEntityMapper` supports:
- `prepareLocalUserForKeycloakUserCreation`
- `prepareLocalUserForKeycloakLoginUser`
- `prepareLocalUserForKeycloakUserDeletion`

`prepareLocalUserForKeycloakLoginUser` builds `OidcTokenRequestDto` using:
- entity realm from `user_entities`
- `client_id` and `client_secret` from bundle config
- local user username + provided plain password

If your login flow needs custom fields/scope/grant behavior, register your own mapper
implementation and tag it as `keycloak.local_user_mapper`.

## Development

```bash
composer install
composer check
```

To enable git hooks:

```bash
git config core.hooksPath .githooks
```
