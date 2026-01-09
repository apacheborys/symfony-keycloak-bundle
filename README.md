# Symfony Keycloak Bridge Bundle

This bundle wires `apacheborys/keycloak-php-client` into Symfony and exposes its services via DI.

## Install (local dev)

```bash
composer require apacheborys/symfony-keycloak-bundle
```

Composer is configured to use the local path repository `../keycloak-php-client`. Replace that with a VCS repository when you publish.

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
  client_id: '%env(KEYCLOAK_CLIENT_ID)%'
  client_secret: '%env(KEYCLOAK_CLIENT_SECRET)%'
  http_client_service: 'http_client' # optional PSR-18 client service id
```

If `http_client_service` is omitted, the underlying client will be discovered via `php-http/discovery`. Make sure a PSR-18 client is installed in your app.

## Services

You can autowire these interfaces:

- `Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface`
- `Apacheborys\KeycloakPhpClient\Service\KeycloakServiceInterface`
