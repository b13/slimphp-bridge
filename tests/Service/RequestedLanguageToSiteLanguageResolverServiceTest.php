<?php

declare(strict_types=1);
namespace B13\SlimPhp\Tests\Service;

/*
 * This file is part of TYPO3 CMS-based extension "SlimPHP Bridge" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use B13\SlimPhp\Service\RequestedLanguageToSiteLanguageResolverService;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;

class RequestedLanguageToSiteLanguageResolverServiceTest extends TestCase
{
    /**
     * @test
     */
    public function acceptLanguageHeaderIsRetrievedCorrectly(): void
    {
        $subject = new RequestedLanguageToSiteLanguageResolverService();
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

        $request = $request->withHeader('Accept-language', 'de,fr;q=0.7,en;q=0.5');
        self::assertSame($site->getLanguageById(1), $subject($request));

        $request = $request->withHeader('Accept-language', 'en,fr;q=0.7');
        self::assertSame($site->getLanguageById(0), $subject($request));

        $request = $request->withHeader('Accept-language', 'de-DE');
        self::assertSame($site->getLanguageById(0), $subject($request));
    }
}
