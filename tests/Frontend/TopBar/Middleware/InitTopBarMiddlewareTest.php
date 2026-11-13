<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Tests\Frontend\TopBar\Middleware;

use SilverStripe\Betamask\Frontend\TopBar\Middleware\InitTopBarMiddleware;
use SilverStripe\Betamask\Frontend\TopBar\TopBarContext;
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

    private const string PAGE = '<html><head></head><body><form method="post"></form></body></html>';

    protected function setUp(): void
    {
        parent::setUp();

        TopBarContext::reset();
    }

    public function testAddsTheBarToThePageForFlaggedMembers(): void
    {
        $this->logInMember(true);
        TopBarContext::singleton()->setEditUrl('/admin/pages/edit/show/5')->setStage('Stage');

        $response = $this->process($this->createRequest('about-us'));
        $body = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('<form method="post"></form>', $body);
        $this->assertStringContainsString('betamask-loader.js', $body);
        $this->assertStringContainsString('edit=%2Fadmin%2Fpages%2Fedit%2Fshow%2F5&amp;stage=Stage', $body);
        $this->assertTrue(HTTPCacheControlMiddleware::singleton()->hasDirective('no-store'));
    }

    public function testKeepsThePageStatusCode(): void
    {
        $this->logInMember(true);

        $response = $this->process($this->createRequest('missing'), HTTPResponse::create(self::PAGE, 404));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('betamask-loader.js', (string) $response->getBody());
    }

    public function testLeavesPagesAloneForOtherVisitors(): void
    {
        $this->logInMember(false);

        $response = $this->process($this->createRequest('about-us'));

        $this->assertSame(self::PAGE, $response->getBody());
    }

    public function testLeavesNonPageRequestsAlone(): void
    {
        $this->logInMember(true);
        $request = $this->createRequest('about-us');
        $request->addHeader('Accept', 'application/json');

        $this->assertSame(self::PAGE, $this->process($request)->getBody());
    }

    public function testLeavesNonHtmlResponsesAlone(): void
    {
        $this->logInMember(true);
        $json = HTTPResponse::create('{"a":"</body>"}')->addHeader('Content-Type', 'application/json');

        $this->assertSame('{"a":"</body>"}', $this->process($this->createRequest('api'), $json)->getBody());
    }

    private function process(HTTPRequest $request, ?HTTPResponse $pageResponse = null): HTTPResponse
    {
        $pageResponse ??= HTTPResponse::create(self::PAGE, 200);

        return InitTopBarMiddleware::create()->process($request, static fn (): HTTPResponse => $pageResponse);
    }

    private function createRequest(string $url): HTTPRequest
    {
        $request = new HTTPRequest('GET', $url);
        $request->addHeader('Accept', 'text/html');

        return $request;
    }

    private function logInMember(bool $flagged): void
    {
        $member = Member::create([
            'Email' => uniqid('editor', true) . '@example.com',
            'FeatureFlag_BetamaskFrontend' => $flagged,
        ]);
        $member->write();
        $this->logInAs($member);
    }

}
