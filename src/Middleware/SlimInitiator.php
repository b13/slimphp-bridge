<?php
declare(strict_types=1);
namespace B13\SlimPhp\Middleware;

/*
 * This file is part of TYPO3 CMS-based extension "SlimPHP Bridge" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Handlers\Strategies\RequestResponseArgs;
use Slim\Routing\RouteCollectorProxy;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Checks the site configuration if there are any routes configured that Slim could handle.
 * The main key is the "type: slim" in your site configuration "routes" option.
 *
 * It then executes SlimPHP for the given main route (multiple independent Slim applications can be set up).
 *
 * Slim Routing is cached, in the same folder as core caches.
 */
class SlimInitiator implements MiddlewareInterface
{
    private string $typo3Version = '';

    public function __construct()
    {
        if (class_exists(\TYPO3\CMS\Core\Information\Typo3Version::class)) {
            $this->typo3Version = (string)(new \TYPO3\CMS\Core\Information\Typo3Version());
        } else {
            // todo: Remove when 10.4 compatibility is dropped
            $this->typo3Version = TYPO3_version;
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var SiteInterface $site */
        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return $handler->handle($request);
        }

        try {
            $routes = $site->getAttribute('routes');
        } catch (\InvalidArgumentException $e) {
            // No routes found in the site configuration
            return $handler->handle($request);
        }

        // No routes configured in the site configuration
        if (empty($routes)) {
            return $handler->handle($request);
        }

        foreach ($routes as $config) {
            $type = $config['type'] ?? '';
            if ($type !== 'slim') {
                continue;
            }

            $prefix = $config['route'] ?? '/';
            if (strpos($request->getUri()->getPath(), $prefix) !== 0) {
                continue;
            }

            if (version_compare($this->typo3Version, '10.4', '>=')) {
                AppFactory::setContainer(GeneralUtility::getContainer());
            }

            $app = AppFactory::create();
            $app->setBasePath($prefix);

            if (!empty($config['middlewares'])) {
                foreach (array_reverse($config['middlewares']) as $middleware) {
                    $app->add($middleware);
                }
            }
            $this->setUpRouteCollector($app);
            $this->populateRoutes($app, $config);

            // Typoscript condition matcher, or LocalizationUtility, need to access the request globally
            $GLOBALS['TYPO3_REQUEST'] = $request;

            // We do not call $app->run() but $app->handle()
            return $app->handle($request);
        }
        // nothing found, continue with TYPO3
        return $handler->handle($request);
    }

    protected function populateRoutes(RouteCollectorProxy $collector, array $routes): void
    {
        $initiator = $this;
        foreach ($routes['groups'] ?? [] as $groupDetails) {
            $route = $collector->group($groupDetails['route'], function(RouteCollectorProxy $group) use ($collector, $groupDetails, $initiator) {
                $initiator->populateRoutes($group, $groupDetails);
            });
            if (!empty($groupDetails['middlewares'])) {
                foreach (array_reverse($groupDetails['middlewares']) as $middleware) {
                    $route->add($middleware);
                }
            }
        }
        foreach ($routes['routes'] ?? [] as $details) {
            if ($details['file']) {
                $route = $collector->get($details['route'], function(ServerRequestInterface $request, ResponseInterface $response) use ($details) {
                    $filename = GeneralUtility::getFileAbsFileName($details['file']);
                    $response->getBody()->write(file_get_contents($filename));
                    return $response;
                });
            } else {
                $methods = array_map(function($method) {
                        return strtoupper($method);
                    },
                    $details['methods']
                );
                $route = $collector->map($methods, $details['route'], $details['callback']);
            }
            if (!empty($details['name'])) {
                $route->setName($details['name']);
            }
            if (!empty($details['middlewares'])) {
                foreach (array_reverse($details['middlewares']) as $middleware) {
                    $route->add($middleware);
                }
            }
        }
    }

    protected function setUpRouteCollector(App $app): void
    {
        if (version_compare($this->typo3Version, '10.4', '>=')) {
            $cacheFolder = Environment::getVarPath() . '/cache/code/core';
            $siteConfigurationCacheFile = $cacheFolder . '/sites-configuration.php';
        } else {
            $cacheFolder = Environment::getVarPath() . '/cache/code/cache_core';
            $siteConfigurationCacheFile = $cacheFolder . '/site-configuration.php';
        }
        $routeCollector = $app->getRouteCollector();
        // Ensure to always use RequestResponseArgs strategy
        $routeCollector->setDefaultInvocationStrategy(new RequestResponseArgs());
        // A little hack to find the right "mtime"
        $cacheFile = $cacheFolder . '/slim.routes.' . str_replace('/', '_', $app->getBasePath());
        if (file_exists($siteConfigurationCacheFile)) {
            $cacheFile .= '.' . filemtime($siteConfigurationCacheFile);
        }
        $cacheFile .=  '.php';
        $routeCollector->setCacheFile($cacheFile);
    }
}
