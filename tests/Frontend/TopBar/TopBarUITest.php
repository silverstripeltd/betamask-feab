<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Tests\Frontend\TopBar;

use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Betamask\Frontend\TopBar\TopBarUI;
use SilverStripe\Betamask\Tests\Frontend\TopBar\TopBarUITest\KioskExtension;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Environment;
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

        $this->assertFalse(
            $this->createTopBar($this->createRequest('about-us', ['InlinePreview' => '1']))->isEnabled(),
        );
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

    public function testFrameStageDefaultsToConfiguredStage(): void
    {
        $this->assertSame('Stage', $this->createTopBar($this->createRequest('about-us'))->getFrameStage());
    }

    public function testFrameStageRespectsRequestedStage(): void
    {
        $topBar = $this->createTopBar($this->createRequest('about-us', ['stage' => 'Live']));

        $this->assertNull($topBar->getFrameStage());
    }

    public function testFrameStageCanBeTurnedOff(): void
    {
        TopBarUI::config()->set('default_stage', '');

        $this->assertNull($this->createTopBar($this->createRequest('about-us'))->getFrameStage());
    }

    public function testEnvironmentLabelUsesConfiguredMap(): void
    {
        TopBarUI::config()->set('environments', [Director::get_environment_type() => 'local']);

        $this->assertSame('local', TopBarUI::create()->getEnvironmentLabel());
    }

    public function testUnmappedEnvironmentFallsBackToUatOrTest(): void
    {
        TopBarUI::config()->set('environments', []);
        $previous = Environment::getEnv('CL_ENVIRONMENT');

        try {
            Environment::setEnv('CL_ENVIRONMENT', 'client-uat');
            $this->assertSame('uat', TopBarUI::create()->getEnvironmentLabel());

            Environment::setEnv('CL_ENVIRONMENT', 'client-test');
            $this->assertSame('test', TopBarUI::create()->getEnvironmentLabel());
        } finally {
            Environment::setEnv('CL_ENVIRONMENT', $previous === false ? '' : $previous);
        }
    }

    public function testInterfaceRendersEscapedConfigAndFrame(): void
    {
        $this->logInAs($this->createMember(true, ['FirstName' => '"><script>alert(1)</script>', 'Surname' => '']));

        $html = $this->createTopBar($this->createRequest('about-us'))
            ->setIframeUrl('http://example.test/about-us?InlinePreview=1&stage=Stage')
            ->getInterface();

        $this->assertStringContainsString('id="betamaskui-topbar"', $html);
        $this->assertStringContainsString('src="http://example.test/about-us?InlinePreview=1&amp;stage=Stage"', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('sandbox=', $html);
        $this->assertMatchesRegularExpression('/data-config="\{&quot;logo&quot;/', $html);
    }

    public function testInterfaceRequiresIframeUrl(): void
    {
        $this->expectException(DomainException::class);

        TopBarUI::create()->getInterface();
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
