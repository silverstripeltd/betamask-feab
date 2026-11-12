<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Tests\Frontend\TopBar\Middleware;

use SilverStripe\Betamask\Frontend\TopBar\Middleware\InitTopBarMiddleware;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPCacheControlMiddleware;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

class InitTopBarMiddlewareTest extends SapphireTest
{

    /**
     * @var bool
     * @phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     */
    protected $usesDatabase = true;

    private int $delegateCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->delegateCalls = 0;
    }

    public function testWrapsPageNavigationWithoutRenderingThePage(): void
    {
        $this->logInFlaggedMember();

        $response = $this->process($this->createRequest('about-us', ['ref' => 'nav']));

        $this->assertSame(0, $this->delegateCalls, 'The page must only be rendered by the iframe request');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('1', $response->getHeader(InitTopBarMiddleware::RESPONSE_HEADER));
        $this->assertStringContainsString('text/html', (string) $response->getHeader('Content-Type'));
        $this->assertTrue(HTTPCacheControlMiddleware::singleton()->hasDirective('no-store'));

        $frameUrl = Director::absoluteURL('about-us') . '?ref=nav&amp;InlinePreview=1&amp;stage=Stage';
        $this->assertStringContainsString('src="' . $frameUrl . '"', (string) $response->getBody());
    }

    public function testPassesThroughForVisitorsWithoutTheBar(): void
    {
        $this->logOut();

        $response = $this->process($this->createRequest('about-us'));

        $this->assertSame(1, $this->delegateCalls);
        $this->assertSame('page', $response->getBody());
        $this->assertNull($response->getHeader(InitTopBarMiddleware::RESPONSE_HEADER));
    }

    public function testPassesThroughNonPageRequests(): void
    {
        $this->logInFlaggedMember();
        $request = $this->createRequest('about-us');
        $request->addHeader('Accept', 'application/json');

        $response = $this->process($request);

        $this->assertSame(1, $this->delegateCalls);
        $this->assertSame('page', $response->getBody());
    }

    public function testKeepsSameSiteRedirectsInsideTheFrame(): void
    {
        $this->logInFlaggedMember();
        $request = $this->createRequest('contact/Form', ['InlinePreview' => '1'], 'POST');
        $location = Director::absoluteURL('contact/finished') . '?stage=Stage#uff';

        $response = $this->process($request, HTTPResponse::create()->redirect($location));

        $this->assertSame(
            Director::absoluteURL('contact/finished') . '?stage=Stage&InlinePreview=1#uff',
            $response->getHeader('Location'),
        );
    }

    public function testLeavesExternalRedirectsAlone(): void
    {
        $this->logInFlaggedMember();
        $request = $this->createRequest('go', ['InlinePreview' => '1']);

        $response = $this->process($request, HTTPResponse::create()->redirect('https://example.org/elsewhere'));

        $this->assertSame('https://example.org/elsewhere', $response->getHeader('Location'));
    }

    public function testLeavesRedirectsOutsideTheFrameAlone(): void
    {
        $this->logInFlaggedMember();
        $request = $this->createRequest('contact/Form', [], 'POST');
        $location = Director::absoluteURL('contact/finished');

        $response = $this->process($request, HTTPResponse::create()->redirect($location));

        $this->assertSame($location, $response->getHeader('Location'));
    }

    private function process(HTTPRequest $request, ?HTTPResponse $pageResponse = null): HTTPResponse
    {
        $pageResponse ??= HTTPResponse::create('page', 200);

        return InitTopBarMiddleware::create()->process($request, function () use ($pageResponse): HTTPResponse {
            $this->delegateCalls += 1;

            return $pageResponse;
        });
    }

    /**
     * @param array<string, string> $getVars
     */
    private function createRequest(string $url, array $getVars = [], string $method = 'GET'): HTTPRequest
    {
        $request = new HTTPRequest($method, $url, $getVars);
        $request->addHeader('Accept', 'text/html');

        return $request;
    }

    private function logInFlaggedMember(): void
    {
        $member = Member::create([
            'Email' => 'flagged-editor@example.com',
            'FeatureFlag_BetamaskFrontend' => true,
        ]);
        $member->write();
        $this->logInAs($member);
    }

}
