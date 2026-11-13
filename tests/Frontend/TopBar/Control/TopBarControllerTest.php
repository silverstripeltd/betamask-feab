<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Tests\Frontend\TopBar\Control;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Betamask\Frontend\TopBar\Control\TopBarController;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

class TopBarControllerTest extends SapphireTest
{

    /**
     * @var bool
     * @phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     */
    protected $usesDatabase = true;

    public function testRefusesVisitorsWithoutTheBar(): void
    {
        $this->logInMember(false);

        try {
            $this->request([]);
            $this->fail('The bar should not be served to members without it turned on');
        } catch (HTTPResponse_Exception $exception) {
            $this->assertSame(403, $exception->getResponse()->getStatusCode());
        }
    }

    public function testServesTheBarWithThePageDetails(): void
    {
        $this->logInMember(true);

        $response = $this->request(['edit' => '/admin/pages/edit/show/5', 'stage' => 'Stage']);
        $config = $this->getConfig($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('noindex', $response->getHeader('X-Robots-Tag'));
        $this->assertSame('/admin/pages/edit/show/5', $config['items'][0]['link']);
        $this->assertSame('Stage', $config['stage']);
    }

    #[DataProvider('provideUnsafeEditUrls')]
    public function testIgnoresEditUrlsOffThisSite(string $editUrl): void
    {
        $this->logInMember(true);

        $config = $this->getConfig($this->request(['edit' => $editUrl]));

        $this->assertNotSame($editUrl, $config['items'][0]['link']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideUnsafeEditUrls(): array
    {
        return [
            'script URL' => ['javascript:alert(1)'],
            'other site' => ['https://example.org/admin'],
            'protocol-relative' => ['//example.org/admin'],
        ];
    }

    public function testAcceptsAbsoluteEditUrlsOnThisSite(): void
    {
        $this->logInMember(true);
        $editUrl = Director::absoluteURL('admin/pages/edit/show/9');

        $config = $this->getConfig($this->request(['edit' => $editUrl]));

        $this->assertSame($editUrl, $config['items'][0]['link']);
    }

    public function testIgnoresUnknownStages(): void
    {
        $this->logInMember(true);

        $this->assertSame('', $this->getConfig($this->request(['stage' => 'Evil']))['stage']);
    }

    /**
     * @param array<string, string> $getVars
     */
    private function request(array $getVars): HTTPResponse
    {
        $request = new HTTPRequest('GET', TopBarController::ROUTE, $getVars);
        $controller = TopBarController::create();
        $controller->setRequest($request);

        return $controller->index($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function getConfig(HTTPResponse $response): array
    {
        preg_match('/data-config="([^"]*)"/', (string) $response->getBody(), $matches);

        return json_decode(html_entity_decode($matches[1] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
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
