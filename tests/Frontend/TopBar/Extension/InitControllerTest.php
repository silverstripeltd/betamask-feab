<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Tests\Frontend\TopBar\Extension;

use SilverStripe\Betamask\Tests\Frontend\TopBar\Extension\InitControllerTest\EditableController;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;
use SilverStripe\View\Requirements;

class InitControllerTest extends SapphireTest
{

    /**
     * @var bool
     * @phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     */
    protected $usesDatabase = true;

    private const string INLINE_SCRIPT = 'betamask-inline.js';

    protected function setUp(): void
    {
        parent::setUp();

        Requirements::clear();
    }

    public function testLoadsScriptAndEscapedEditLinkInsideTheFrame(): void
    {
        $this->logInMember(true);

        $this->runInitHook(EditableController::create(), ['InlinePreview' => '1']);

        $this->assertTrue($this->hasInlineScript());
        $this->assertStringContainsString(
            '<meta name="betamask-page-url" content="/admin/pages/edit/show/5?a=1&amp;b=&quot;2&quot;">',
            $this->getHeadTags(),
        );
    }

    public function testRecognisesTheFrameFromFetchMetadata(): void
    {
        $this->logInMember(true);

        $this->runInitHook(EditableController::create(), [], ['Sec-Fetch-Dest' => 'iframe']);

        $this->assertTrue($this->hasInlineScript());
    }

    public function testSkipsTheEditLinkWhenControllerHasNoRecord(): void
    {
        $this->logInMember(true);

        $this->runInitHook(Controller::create(), ['InlinePreview' => '1']);

        $this->assertTrue($this->hasInlineScript());
        $this->assertStringNotContainsString('betamask-page-url', $this->getHeadTags());
    }

    public function testDoesNothingOutsideTheFrame(): void
    {
        $this->logInMember(true);

        $this->runInitHook(EditableController::create());

        $this->assertFalse($this->hasInlineScript());
    }

    public function testDoesNothingInTheCmsPreview(): void
    {
        $this->logInMember(true);

        $this->runInitHook(EditableController::create(), ['CMSPreview' => '1'], ['Sec-Fetch-Dest' => 'iframe']);

        $this->assertFalse($this->hasInlineScript());
    }

    public function testDoesNothingForMembersWithoutTheFlag(): void
    {
        $this->logInMember(false);

        $this->runInitHook(EditableController::create(), ['InlinePreview' => '1']);

        $this->assertFalse($this->hasInlineScript());
    }

    /**
     * @param array<string, string> $getVars
     * @param array<string, string> $headers
     */
    private function runInitHook(Controller $controller, array $getVars = [], array $headers = []): void
    {
        $request = new HTTPRequest('GET', 'page', $getVars);

        foreach ($headers as $name => $value) {
            $request->addHeader($name, $value);
        }

        $controller->setRequest($request);
        $controller->extend('onBeforeInit');
    }

    private function hasInlineScript(): bool
    {
        foreach (array_keys(Requirements::backend()->getJavascript()) as $file) {
            if (str_contains((string) $file, self::INLINE_SCRIPT)) {
                return true;
            }
        }

        return false;
    }

    private function getHeadTags(): string
    {
        return implode("\n", Requirements::backend()->getCustomHeadTags());
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
