<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Tests\Frontend\TopBar;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Betamask\Frontend\TopBar\TopBarUI;
use SilverStripe\Betamask\Tests\Frontend\TopBar\TopBarUITest\KioskExtension;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

class TopBarUITest extends SapphireTest
{

    /**
     * @var bool
     * @phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     */
    protected $usesDatabase = true;

    public function testEnabledForFlaggedMemberOnPageNavigation(): void
    {
        $this->logInAs($this->createMember(true));

        $this->assertTrue($this->createTopBar($this->createRequest('about-us'))->isEnabled());
    }

    public function testDisabledWhenLoggedOut(): void
    {
        $this->logOut();

        $this->assertFalse($this->createTopBar($this->createRequest('about-us'))->isEnabled());
    }

    public function testDisabledForMemberWithoutTheFlag(): void
    {
        $this->logInAs($this->createMember(false));

        $this->assertFalse($this->createTopBar($this->createRequest('about-us'))->isEnabled());
    }

    public function testDisabledForNonGetRequests(): void
    {
        $this->logInAs($this->createMember(true));

        $this->assertFalse($this->createTopBar($this->createRequest('about-us', [], 'POST'))->isEnabled());
    }

    public function testDisabledForAjaxRequests(): void
    {
        $this->logInAs($this->createMember(true));
        $request = $this->createRequest('about-us');
        $request->addHeader('X-Requested-With', 'XMLHttpRequest');

        $this->assertFalse($this->createTopBar($request)->isEnabled());
    }

    public function testDisabledInsideTheFrame(): void
    {
        $this->logInAs($this->createMember(true));

        $request = $this->createRequest('about-us');
        $request->addHeader('Sec-Fetch-Dest', 'iframe');

        $this->assertFalse($this->createTopBar($request)->isEnabled());
    }

    #[DataProvider('provideExcludedUrls')]
    public function testDisabledOnExcludedUrls(string $url): void
    {
        $this->logInAs($this->createMember(true));

        $this->assertFalse($this->createTopBar($this->createRequest($url))->isEnabled());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideExcludedUrls(): array
    {
        return [
            'admin' => ['admin/pages'],
            'login' => ['Security/login'],
            'logout' => ['Security/logout'],
            'lost password' => ['Security/lostpassword'],
            'change password' => ['Security/changepassword'],
            'dev tools' => ['dev/build'],
            'the bar itself' => ['betamask-bar'],
            'assets' => ['assets/Uploads'],
            'case-insensitive' => ['SECURITY/Login'],
        ];
    }

    public function testExcludedUrlsMatchWholeSegments(): void
    {
        $this->logInAs($this->createMember(true));

        $this->assertTrue($this->createTopBar($this->createRequest('developers'))->isEnabled());
        $this->assertTrue($this->createTopBar($this->createRequest('administration-team'))->isEnabled());
    }

    public function testConfiguredUrlsAreExcluded(): void
    {
        TopBarUI::config()->merge('disable_on_url', ['/shop/checkout/']);
        $this->logInAs($this->createMember(true));

        $this->assertFalse($this->createTopBar($this->createRequest('shop/checkout/payment'))->isEnabled());
        $this->assertTrue($this->createTopBar($this->createRequest('shop'))->isEnabled());
    }

    public function testExtensionsCanTurnTheBarOff(): void
    {
        TopBarUI::add_extension(KioskExtension::class);
        $this->logInAs($this->createMember(true));

        try {
            $this->assertFalse($this->createTopBar($this->createRequest('kiosk/screen-1'))->isEnabled());
            $this->assertTrue($this->createTopBar($this->createRequest('about-us'))->isEnabled());
        } finally {
            TopBarUI::remove_extension(KioskExtension::class);
        }
    }

    public function testCollapsedStateComesFromTheCookie(): void
    {
        $topBar = TopBarUI::create();

        Cookie::force_expiry(TopBarUI::COLLAPSED_COOKIE);
        $this->assertFalse($topBar->isCollapsed());

        Cookie::set(TopBarUI::COLLAPSED_COOKIE, '1');
        $this->assertTrue($topBar->isCollapsed());
    }

    public function testBarUrlCarriesThePageDetails(): void
    {
        $url = TopBarUI::create()->getBarUrl('/admin/pages/edit/show/5', 'Stage');

        $this->assertMatchesRegularExpression(
            '#betamask-bar/?\?edit=%2Fadmin%2Fpages%2Fedit%2Fshow%2F5&stage=Stage$#',
            $url,
        );
        $this->assertMatchesRegularExpression('#betamask-bar/?$#', TopBarUI::create()->getBarUrl('', ''));
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

    private function createTopBar(HTTPRequest $request): TopBarUI
    {
        return TopBarUI::create()->setRequest($request);
    }

    /**
     * @param array<string, string> $fields
     */
    private function createMember(bool $flagged, array $fields = []): Member
    {
        $member = Member::create(array_merge([
            'FirstName' => 'Test',
            'Surname' => 'Editor',
            'Email' => uniqid('editor', true) . '@example.com',
            'FeatureFlag_BetamaskFrontend' => $flagged,
        ], $fields));
        $member->write();

        return $member;
    }

}
