<?php

declare(strict_types=1);
namespace B13\SlimPhp\Middleware;

/**
 * This file is part of TYPO3 CMS-based extension "SlimPHP Bridge" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use B13\SlimPhp\Service\RequestedLanguageToSiteLanguageResolverService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Automatically select a language based on the Accept Language HTTP header
 */
class PreferredClientLanguageSelector implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $language = (new RequestedLanguageToSiteLanguageResolverService())($request);
        $request = $request->withAttribute('language', $language);
        $GLOBALS['TYPO3_REQUEST'] = $request;
        return $handler->handle($request);
    }
}
