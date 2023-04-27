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
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Core\Bootstrap;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Sets up TSFE and Extbase, in order to use Extbase within a Slim Controller
 */
class ExtbaseBridge implements MiddlewareInterface
{
    private string $typo3Version = '';
    private Context $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->typo3Version = (string)(new \TYPO3\CMS\Core\Information\Typo3Version());
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return $handler->handle($request);
        }

        if (!isset($GLOBALS['TSFE']) || !$GLOBALS['TSFE'] instanceof TypoScriptFrontendController) {
            $request = $this->createGlobalTsfe($site, $request);
        } else {
            $GLOBALS['TSFE']->id = $site->getRootPageId();
        }

        $request = $this->bootFrontend($request);
        $this->bootExtbase($request);

        return $handler->handle($request);
    }

    protected function createGlobalTsfe(Site $site, ServerRequestInterface $request): ServerRequestInterface
    {
        if (version_compare($this->typo3Version, '11.5', '>=')) {
            $controller = GeneralUtility::makeInstance(
                TypoScriptFrontendController::class,
                $this->context,
                $site,
                $request->getAttribute('language', $site->getDefaultLanguage()),
                new PageArguments($site->getRootPageId(), '0', []),
                $request->getAttribute('frontend.user')
            );

            $controller->determineId($request);

            $request = $request->withAttribute('frontend.controller', $controller);

            // Make TSFE globally available
            // @todo deprecate $GLOBALS['TSFE'] once TSFE is retrieved from the
            //       PSR-7 request attribute frontend.controller throughout TYPO3 core
            $GLOBALS['TSFE'] = $controller;
        } else {
            $GLOBALS['TSFE'] = GeneralUtility::makeInstance(
                TypoScriptFrontendController::class,
                null,
                $site,
                $request->getAttribute('language'),
                null,
                $request->getAttribute('frontend.user')
            );
        }

        return $request;
    }

    protected function bootFrontend(ServerRequestInterface $request): ServerRequestInterface
    {
        if (version_compare($this->typo3Version, '12.2', '>=')) {
            // Run Frontend TypoScript
            $request = $GLOBALS['TSFE']->getFromCache($request);
        } elseif (version_compare($this->typo3Version, '11.5', '>=')) {
            // nothing to do, TSFE is already ready
        } else {
            $GLOBALS['TSFE']->fetch_the_id($request);
            $GLOBALS['TSFE']->getConfigArray($request);
            $GLOBALS['TSFE']->settingLanguage($request);
            $GLOBALS['TSFE']->newCObj();
        }
        return $request;
    }

    protected function bootExtbase(ServerRequestInterface $request): void
    {
        GeneralUtility::makeInstance(Bootstrap::class)->initialize([
            'extensionName' => 'slimphp',
            'vendorName' => 'B13',
            'pluginName' => 'slimphp',
        ], $request);
    }
}
