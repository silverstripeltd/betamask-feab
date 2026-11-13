<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Tests\Frontend\TopBar;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Betamask\Frontend\TopBar\TopBarInjector;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Dev\SapphireTest;

class TopBarInjectorTest extends SapphireTest
{

    /**
     * @var bool
     * @phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     */
    protected $usesDatabase = false;

    private const string PAGE = '<html><head><title>Page</title></head><body><p>Hello</p></body></html>';

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('provideResponses')]
    public function testCanInject(string $body, int $status, array $headers, bool $expected): void
    {
        $response = HTTPResponse::create($body, $status);

        foreach ($headers as $name => $value) {
            $response->addHeader($name, $value);
        }

        $this->assertSame($expected, TopBarInjector::singleton()->canInject($response));
    }

    /**
     * @return array<string, array{string, int, array<string, string>, bool}>
     */
    public static function provideResponses(): array
    {
        $html = ['Content-Type' => 'text/html; charset=utf-8'];

        return [
            'HTML page' => [self::PAGE, 200, $html, true],
            'error page' => [self::PAGE, 404, $html, true],
            'no content type' => [self::PAGE, 200, [], true],
            'HTML fragment' => ['<p>Fragment</p>', 200, $html, false],
            'JSON' => ['{"body":"</body>"}', 200, ['Content-Type' => 'application/json'], false],
            'download' => [self::PAGE, 200, $html + ['Content-Disposition' => 'attachment; filename=a.html'], false],
            'redirect' => [self::PAGE, 302, $html + ['Location' => '/elsewhere'], false],
        ];
    }

    public function testInjectsOffsetStylesheetAndLoaderOnly(): void
    {
        $response = TopBarInjector::singleton()->inject(
            HTTPResponse::create(self::PAGE),
            '/betamask-bar?edit=%2Fadmin&a="b"',
            false,
        );
        $body = (string) $response->getBody();

        $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="[^"]+betamask-offset\.css[^"]*" '
            . 'media="all" data-betamask-offset></head>#', $body);
        $this->assertMatchesRegularExpression('#<script type="module" src="[^"]+betamask-loader\.js[^"]*" '
            . 'data-betamask-bar="/betamask-bar\?edit=%2Fadmin&amp;a=&quot;b&quot;" '
            . 'data-betamask-collapsed="0"></script></body>#', $body);
        $this->assertStringContainsString('<title>Page</title>', $body);
        $this->assertStringContainsString('<p>Hello</p>', $body);
    }

    public function testCollapsedBarLeavesThePageInPlace(): void
    {
        $body = (string) TopBarInjector::singleton()
            ->inject(HTTPResponse::create(self::PAGE), '/betamask-bar', true)
            ->getBody();

        $this->assertStringContainsString('media="not all" data-betamask-offset', $body);
        $this->assertStringContainsString('data-betamask-collapsed="1"', $body);
    }

    public function testLoaderGoesBeforeTheLastClosingBodyTag(): void
    {
        $page = '<html><head></head><body><script>var s = "</body>";</script></BODY></html>';

        $body = (string) TopBarInjector::singleton()
            ->inject(HTTPResponse::create($page), '/betamask-bar', false)
            ->getBody();

        $this->assertStringContainsString('var s = "</body>";</script><script type="module"', $body);
        $this->assertStringEndsWith('</script></BODY></html>', $body);
    }

}
