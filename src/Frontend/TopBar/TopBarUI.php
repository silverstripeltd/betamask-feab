<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Frontend\TopBar;

use DomainException;
use JsonException;
use SilverStripe\Admin\AdminRootController;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\FeatureFlag\FeatureFlag as FeatureFlagRegistry;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\TemplateEngine\SSTemplateEngine;
use SilverStripe\View\ViewLayerData;

/**
 * Decides whether a request gets the front-end top bar, and renders the wrapper
 * document that shows the bar above the real page loaded in an iframe.
 *
 * Extensions can implement updateIsEnabled(bool &$enabled) to turn the bar off
 * for further requests; getRequest() exposes the request being considered.
 * The menu itself is built, and extended, by TopBarMenu.
 *
 * @phpcs:disable SlevomatCodingStandard.Exceptions.DisallowNonCapturingCatch.DisallowedNonCapturingCatch
 */
class TopBarUI
{

    use Configurable;
    use Extensible;
    use Injectable;

    public const string TEMPLATE = 'SilverStripe/Betamask/Frontend/TopBar/TopBar';

    private const string LOGO_RESOURCE = 'silverstripeltd/betamask-bao: client/dist/images/silverstripe-logo.svg';

    private const string ENV_UAT = 'uat';

    private const string ENV_TEST = 'test';

    /**
     * Map of Silverstripe environment types to the label shown in the bar.
     * Environment types not listed here resolve to "uat" or "test".
     *
     * @config
     * @var array<string, string>
     */
    private static array $environments = [
        'dev' => 'dev',
        'live' => 'prod',
    ];

    /**
     * Location of the application favicon, as a module resource reference.
     * Set to an empty string to omit the favicon.
     *
     * @config
     */
    private static string $favicon = 'themes/app/dist/images/favicon/favicon-32x32.png';

    /**
     * Reading stage used for the framed page when the request does not choose one.
     * Set to an empty string to show whatever stage the request resolves to.
     *
     * @config
     */
    private static string $default_stage = 'Stage';

    /**
     * URL prefixes, relative to the site base URL, that never get the bar.
     * The admin and Security login/logout/lost-password URLs are always excluded.
     *
     * @config
     * @var string[]
     */
    private static array $disable_on_url = [
        'assets/',
        'dev/',
        'health/check',
        'Security/changepassword',
    ];

    protected ?HTTPRequest $request = null;

    protected string $iframeUrl = '';

    public function setRequest(HTTPRequest $request): self
    {
        $this->request = $request;

        return $this;
    }

    /**
     * The request being considered, for extensions that vary the bar per URL
     */
    public function getRequest(): ?HTTPRequest
    {
        return $this->request;
    }

    public function setIframeUrl(string $url): self
    {
        $this->iframeUrl = $url;

        return $this;
    }

    /**
     * Whether the current request should be replaced by the top bar wrapper
     */
    public function isEnabled(): bool
    {
        if (!$this->isEligibleRequest()) {
            return false;
        }

        $enabled = Security::getCurrentUser() instanceof Member
            && FeatureFlagRegistry::checkFeatureFlag(FeatureFlag::NAME);
        $this->extend('updateIsEnabled', $enabled);

        return $enabled;
    }

    /**
     * Reading stage to request for the framed page, or null to leave it unchanged
     */
    public function getFrameStage(): ?string
    {
        $stage = (string) self::config()->get('default_stage');
        $requested = $this->request instanceof HTTPRequest && $this->request->getVar('stage');

        return $stage === '' || $requested
            ? null
            : $stage;
    }

    /**
     * Label of the current environment, shown next to the application name
     */
    public function getEnvironmentLabel(): string
    {
        $labels = (array) self::config()->get('environments');
        $type = Director::get_environment_type();

        if (array_key_exists($type, $labels)) {
            return (string) $labels[$type];
        }

        $platformEnvironment = Environment::getEnv('CWP_ENVIRONMENT') . ' ' . Environment::getEnv('CL_ENVIRONMENT');

        return str_contains(strtolower($platformEnvironment), self::ENV_UAT)
            ? self::ENV_UAT
            : self::ENV_TEST;
    }

    /**
     * Properties for the Vue top bar, encoded as JSON
     */
    public function getConfigJson(): string
    {
        $menu = TopBarMenu::create();
        $config = [
            'logo' => (string) ModuleResourceLoader::resourceURL(self::LOGO_RESOURCE),
            'logoUrl' => $menu->getAdminUrl('pages'),
            'siteUrl' => Director::absoluteBaseURL(),
            'appName' => $this->getAppName(),
            'appEnv' => $this->getEnvironmentLabel(),
            'items' => $menu->getItems(),
        ];

        try {
            return json_encode($config, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (JsonException) {
            return '{}';
        }
    }

    /**
     * Full HTML document showing the bar with the framed page below it
     *
     * @throws DomainException when no iframe URL has been set
     */
    public function getInterface(): string
    {
        if (!$this->iframeUrl) {
            throw new DomainException('Unable to find the iframe URL.');
        }

        // Rendered with the template engine directly rather than SSViewer, so no
        // Requirements registered during this request are injected into the wrapper.
        return SSTemplateEngine::execute_template(
            self::TEMPLATE,
            ViewLayerData::create([
                'Title' => $this->getAppName(),
                'Favicon' => (string) ModuleResourceLoader::resourceURL((string) self::config()->get('favicon')),
                'FrameURL' => $this->iframeUrl,
                'ConfigJson' => $this->getConfigJson(),
            ]),
        );
    }

    protected function getAppName(): string
    {
        return (string) SiteConfig::current_site_config()->getTitle();
    }

    /**
     * Whether the request is a page navigation the bar may wrap, before
     * considering who is asking
     */
    protected function isEligibleRequest(): bool
    {
        $request = $this->request;

        return $request instanceof HTTPRequest
            && $request->isGET()
            && !$request->isAjax()
            && FrameRequest::isPageNavigation($request)
            && !FrameRequest::isCmsPreview($request)
            && !$this->isDisabledUrl($request);
    }

    /**
     * Whether the request path starts with a URL that never gets the bar
     */
    protected function isDisabledUrl(HTTPRequest $request): bool
    {
        $currentUrl = $this->normaliseUrl($request->getURL());

        foreach ($this->getDisabledUrls() as $url) {
            $prefix = $this->normaliseUrl($url);

            if ($prefix !== '/' && str_starts_with($currentUrl, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    protected function getDisabledUrls(): array
    {
        $securityConfig = Security::config();

        return array_merge(
            (array) self::config()->get('disable_on_url'),
            [
                AdminRootController::get_admin_route(),
                (string) $securityConfig->get('login_url'),
                (string) $securityConfig->get('logout_url'),
                (string) $securityConfig->get('lost_password_url'),
            ],
        );
    }

    /**
     * Reduce a site-relative URL to a lower-case path with a single leading and
     * trailing slash, so configured prefixes compare reliably
     */
    protected function normaliseUrl(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return strtolower('/' . ($path === '' ? '' : $path . '/'));
    }

}
