<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Tests\Frontend\TopBar;

use SilverStripe\Betamask\Frontend\TopBar\TopBarRenderer;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

class TopBarRendererTest extends SapphireTest
{

    /**
     * @var bool
     * @phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     */
    protected $usesDatabase = true;

    public function testConfigDescribesThePage(): void
    {
        $config = $this->decodeConfig('/admin/pages/edit/show/5', 'Stage', true);

        $this->assertSame('Stage', $config['stage']);
        $this->assertTrue($config['collapsed']);
        $this->assertSame('/admin/pages/edit/show/5', $config['items'][0]['link']);
        $this->assertSame('_top', $config['items'][0]['target']);
    }

    public function testDocumentHasNoFrameOrSandboxAndEscapesConfig(): void
    {
        $member = Member::create(['FirstName' => '"><script>alert(1)</script>', 'Email' => 'xss@example.com']);
        $member->write();
        $this->logInAs($member);

        $html = TopBarRenderer::singleton()->render('', '', false);

        $this->assertStringContainsString('id="betamaskui-topbar"', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertMatchesRegularExpression('/data-config="\{&quot;logo&quot;/', $html);
    }

    public function testEnvironmentLabelUsesConfiguredMap(): void
    {
        TopBarRenderer::config()->set('environments', [Director::get_environment_type() => 'local']);

        $this->assertSame('local', TopBarRenderer::singleton()->getEnvironmentLabel());
    }

    public function testUnmappedEnvironmentFallsBackToUatOrTest(): void
    {
        TopBarRenderer::config()->set('environments', []);
        $previous = Environment::getEnv('CL_ENVIRONMENT');

        try {
            Environment::setEnv('CL_ENVIRONMENT', 'client-uat');
            $this->assertSame('uat', TopBarRenderer::singleton()->getEnvironmentLabel());

            Environment::setEnv('CL_ENVIRONMENT', 'client-test');
            $this->assertSame('test', TopBarRenderer::singleton()->getEnvironmentLabel());
        } finally {
            Environment::setEnv('CL_ENVIRONMENT', $previous === false ? '' : $previous);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeConfig(string $editUrl, string $stage, bool $collapsed): array
    {
        $json = TopBarRenderer::singleton()->getConfigJson($editUrl, $stage, $collapsed);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

}
