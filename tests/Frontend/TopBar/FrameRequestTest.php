<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Tests\Frontend\TopBar;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Betamask\Frontend\TopBar\FrameRequest;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;

class FrameRequestTest extends SapphireTest
{

    /**
     * @var bool
     * @phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     */
    protected $usesDatabase = false;

    private const array NAVIGATION_HEADERS = [
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Sec-Fetch-Dest' => 'document',
        'Sec-Fetch-Mode' => 'navigate',
    ];

    public function testIframeFetchDestinationMarksRequestAsFramed(): void
    {
        $request = $this->createRequest('about-us', [], ['Sec-Fetch-Dest' => 'iframe']);

        $this->assertTrue(FrameRequest::isFramed($request));
        $this->assertFalse(FrameRequest::isPageNavigation($request));
    }

    public function testCmsPreviewIsNotAPageNavigation(): void
    {
        $request = $this->createRequest('about-us', ['CMSPreview' => '1'], ['Accept' => 'text/html']);

        $this->assertTrue(FrameRequest::isCmsPreview($request));
        $this->assertFalse(FrameRequest::isPageNavigation($request));
    }

    public function testDocumentRequestIsNotFramed(): void
    {
        $request = $this->createRequest('about-us', [], ['Sec-Fetch-Dest' => 'document']);

        $this->assertFalse(FrameRequest::isFramed($request));
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('provideNavigationRequests')]
    public function testIsPageNavigation(string $url, array $headers, bool $expected): void
    {
        $this->assertSame($expected, FrameRequest::isPageNavigation($this->createRequest($url, [], $headers)));
    }

    /**
     * @return array<string, array{string, array<string, string>, bool}>
     */
    public static function provideNavigationRequests(): array
    {
        $navigation = self::NAVIGATION_HEADERS;

        return [
            'browser navigation' => ['about-us', $navigation, true],
            'home page' => ['', $navigation, true],
            'navigation without fetch metadata' => ['about-us', ['Accept' => 'text/html'], true],
            'no headers at all' => ['about-us', [], true],
            'fetch() call' => [
                'about-us',
                ['Accept' => '*/*', 'Sec-Fetch-Dest' => 'empty', 'Sec-Fetch-Mode' => 'cors'],
                false,
            ],
            'JSON request' => ['about-us', ['Accept' => 'application/json'], false],
            'wildcard accept' => ['about-us', ['Accept' => '*/*'], false],
            'image sub-resource' => ['about-us', ['Accept' => 'image/*', 'Sec-Fetch-Dest' => 'image'], false],
            'iframe navigation' => ['about-us', ['Accept' => 'text/html', 'Sec-Fetch-Dest' => 'iframe'], false],
            'XML file' => ['sitemap.xml', $navigation, false],
            'nested file' => ['assets/Uploads/report.pdf', $navigation, false],
        ];
    }

    /**
     * @param array<string, string> $getVars
     * @param array<string, string> $headers
     */
    private function createRequest(string $url, array $getVars = [], array $headers = []): HTTPRequest
    {
        $request = new HTTPRequest('GET', $url, $getVars);

        foreach ($headers as $name => $value) {
            $request->addHeader($name, $value);
        }

        return $request;
    }

}
