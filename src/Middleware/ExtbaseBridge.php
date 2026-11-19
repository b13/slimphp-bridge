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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Core\Cache\Frontend\PhpFrontend;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScriptFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Core\Bootstrap;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageInformation;
use TYPO3\CMS\Frontend\Page\PageInformationCreationFailedException;
use TYPO3\CMS\Frontend\Page\PageInformationFactory;

/**
 * Prepares the request in order to use Extbase within a Slim Controller.
 *
 * Slim is booted right after the site has been resolved, so TYPO3 has neither
 * resolved a page nor calculated TypoScript at this point. Extbase however reads
 * TypoScript exclusively from the "frontend.typoscript" request attribute. This
 * middleware resolves the root page of the current site, builds the full
 * TypoScript for it (in the same way the TYPO3 Frontend rendering does) and
 * initializes Extbase with the enriched request.
 */
class ExtbaseBridge implements MiddlewareInterface
{
    public function __construct(
        private readonly PageInformationFactory $pageInformationFactory,
        private readonly FrontendTypoScriptFactory $frontendTypoScriptFactory,
        #[Autowire(service: 'cache.typoscript')]
        private readonly PhpFrontend $typoScriptCache,
        private readonly Bootstrap $extbaseBootstrap,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return $handler->handle($request);
        }

        // Slim routes are not bound to a page, use the root page of the site
        $request = $request->withAttribute('routing', new PageArguments($site->getRootPageId(), '0', []));

        try {
            $pageInformation = $this->pageInformationFactory->create($request);
        } catch (PageInformationCreationFailedException $e) {
            // e.g. root page not accessible or no TypoScript record found
            return $e->getResponse();
        }
        $request = $request->withAttribute('frontend.page.information', $pageInformation);

        // TYPO3 v13 still needs $GLOBALS['TSFE'], it has been removed with TYPO3 v14
        if (version_compare((new Typo3Version())->getVersion(), '14.0', '<')) {
            $request = $this->createGlobalTsfe($request, $pageInformation);
        }

        $request = $request->withAttribute(
            'frontend.typoscript',
            $this->createFrontendTypoScript($request, $site, $pageInformation)
        );

        $request = $this->extbaseBootstrap->initialize([
            'extensionName' => 'slimphp',
            'vendorName' => 'B13',
            'pluginName' => 'slimphp',
        ], $request);

        // Typoscript condition matcher, or LocalizationUtility, need to access the request globally
        $GLOBALS['TYPO3_REQUEST'] = $request;

        return $handler->handle($request);
    }

    /**
     * Same setup as TYPO3 v13 TypoScriptFrontendInitialization middleware.
     */
    protected function createGlobalTsfe(ServerRequestInterface $request, PageInformation $pageInformation): ServerRequestInterface
    {
        $controller = GeneralUtility::makeInstance(TypoScriptFrontendController::class);
        $controller->initializePageRenderer($request);
        $controller->initializeLanguageService($request);
        $controller->id = $pageInformation->getId();
        $controller->page = $pageInformation->getPageRecord();
        $controller->contentPid = $pageInformation->getContentFromPid();
        $controller->rootLine = $pageInformation->getRootLine();
        $controller->config['rootLine'] = $pageInformation->getLocalRootLine();

        $request = $request->withAttribute('frontend.controller', $controller);
        $GLOBALS['TSFE'] = $controller;

        return $request;
    }

    /**
     * Same calculation as TYPO3 PrepareTypoScriptFrontendRendering middleware, but
     * always with the full TypoScript setup, as Extbase needs "plugin." and "config.tx_extbase".
     */
    protected function createFrontendTypoScript(ServerRequestInterface $request, Site $site, PageInformation $pageInformation): FrontendTypoScript
    {
        $sysTemplateRows = $pageInformation->getSysTemplateRows();
        $fullRootLine = $pageInformation->getRootLine();
        ksort($fullRootLine);
        $conditionMatcherVariables = [
            'request' => $request,
            'pageId' => $pageInformation->getId(),
            'page' => $pageInformation->getPageRecord(),
            'fullRootLine' => $fullRootLine,
            'localRootLine' => $pageInformation->getLocalRootLine(),
            'site' => $site,
            'siteLanguage' => $request->getAttribute('language', $site->getDefaultLanguage()),
        ];
        if ($request->getAttribute('frontend.controller') !== null) {
            $conditionMatcherVariables['tsfe'] = $request->getAttribute('frontend.controller');
        }

        $frontendTypoScript = $this->frontendTypoScriptFactory->createSettingsAndSetupConditions(
            $site,
            $sysTemplateRows,
            $conditionMatcherVariables,
            $this->typoScriptCache
        );
        return $this->frontendTypoScriptFactory->createSetupConfigOrFullSetup(
            true,
            $frontendTypoScript,
            $site,
            $sysTemplateRows,
            $conditionMatcherVariables,
            '0',
            $this->typoScriptCache,
            $request
        );
    }
}
