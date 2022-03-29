# SlimPHP - TYPO3 Bridge Extension

Boot up a SlimPHP application within a PSR-15 middleware of the TYPO3 Frontend
Request.

There are two things you need for this:

1. Create your endpoints with a SlimPHP `RequestResponseArgs` strategy in PHP
2. Configure your endpoints in your site configuration file

Clear caches and you should be good to go.

## Description

TYPO3 v9 offers a flexible way to hook into the Frontend Rendering process
and do something completely different - thanks to PSR-7 and PSR-15.

SlimPHP is also based on the PSR standards and is a perfect fit if a TYPO3
developer needs to integrate a proper API via e.g. REST.

This small wrapper extension helps to get going very quickly when needing
a small API layer for arbitrary endpoints. It is not meant to become a fully
headless solution for TYPO3 as a CMS.

However, a TYPO3 PHP developer should be familiar with starting off
really quickly to handle custom endpoints without having to write TypoScript.

## Installation

Install it via `composer req b13/slimphp-bridge` (currently composer-only as some PHP dependencies
are needed).

Activate the extension in the backend of TYPO3. 

## Configuration

Then adapt your site configuration to add custom routes.

The `type: slim` entry enables a SlimPHP Application. The current Site object
is then available in the request object.

````yml
routes:
  - route: '/api'
    type: 'slim'
    # add middlewares for the whole application. Convenient for any error handling or adding Preflight checks (OPTIONS)
    middlewares:
      - 'B13\MyExtension\Middleware\PreflightCheck'
    groups:
    - route: '/v1'
      middlewares:
        # enable this if you don't manage your languages via the URL endpoint + the base site handling.
        - 'B13\SlimPhp\Middleware\PreferredClientLanguageSelector'
        # enable this if you need extbase in your custom setup
        - 'B13\SlimPhp\Middleware\ExtbaseBridge'
      routes:
        # load a file
        - methods: [any]
          route: '/schema.json'
          file: 'EXT:myextension/Resources/Private/Api/schema_v1.json'
          contentType: 'application/json'
        - methods: [get]
          route: '/article'
          callback: B13\MyExtension\Controller\LoadArticlesController
        - methods: [get]
          route: '/customer'
          callback: B13\MyExtension\Controller\CustomerController:fetchAll
          middlewares: [B13\MyExtension\Middleware\JwtAuthentication]
        - methods: [get]
          route: '/customer/{id}'
          callback: B13\MyExtension\Controller\Api\CustomerController:fetch
          middlewares: [B13\MyExtension\Middleware\JwtAuthentication]
        - methods: [put]
          route: '/customer/{id}'
          callback: B13\MyExtension\Controller\Api\CustomerController:update
          middlewares: [B13\MyExtension\Middleware\JwtAuthentication]
````

The configuration is similar to what you can do with SlimPHP and with TYPO3, and your controllers just follow
the `RequestResponseArgs` strategy pattern in SlimPHP.

Once you create your endpoints (callbacks), clear your caches and you can run your installation directly.

__TYPO3 10.4__: If you wan't to use DI in your callbacks, you will have to make them public in the DI configuration:

```yaml
services:
  B13\MyExtension\Controller\Api\CustomerController:
    public: true
```

Currently, the extension ships with Tobias Nyholm's PSR implementation, as this provides proper PSR-17 factories.

### Caveats

Every time you change your configuration, ensure to clear the TYPO3 core caches.

## ToDo

- More documentation to get started and define all options available
- More Tests
- More flexibility with the routing parameters
- Proper error handling

## License

As TYPO3 Core, this extension is licensed under GPL2 or later. See the LICENSE file for more details.

## Authors & Maintenance
This extension was initially created for a customer project by Benni Mack for [b13, Stuttgart](https://b13.com).

[Find more TYPO3 extensions we have developed](https://b13.com/useful-typo3-extensions-from-b13-to-you) that help us deliver value in client projects. As part of the way we work, we focus on testing and best practices to ensure long-term performance, reliability, and results in all our code.
