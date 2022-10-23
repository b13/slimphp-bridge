<?php

declare(strict_types=1);
namespace B13\SlimPhp\Tests\Middleware;

/*
 * This file is part of TYPO3 CMS-based extension "SlimPHP Bridge" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use B13\SlimPhp\Middleware\PreferredClientLanguageSelector;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

class PreferredClientLanguageSelectorTest extends TestCase
{
    /**
     * @test
     */
    public function requestIsProperlyEnriched(): void
    {
        $subject = new PreferredClientLanguageSelector();
        $dummyHandler = new class() implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response($request->getAttribute('language') ? 200 : 404);
            }
        };
        $site = new Site('test', 23, [
            'base' => '/',
            'languages' => [
                0 => [
                    'languageId' => 0,
                    'base' => '/en/',
                    'iso-639-1' => 'en',
                    'locale' => 'en_US',
                ],
                1 => [
                    'languageId' => 1,
                    'base' => '/fr/',
                    'iso-639-1' => 'fr',
                    'locale' => 'fr_FR',
                ],
            ],
        ]);
        $request = new ServerRequest('GET', 'https://www.example.com');
        $request = $request->withAttribute('site', $site);
        $response = $subject->process($request, $dummyHandler);
        self::assertEquals($response->getStatusCode(), 200);

        $request = $request->withAttribute('site', null);
        $response = $subject->process($request, $dummyHandler);
        self::assertEquals($response->getStatusCode(), 404);
    }
}
