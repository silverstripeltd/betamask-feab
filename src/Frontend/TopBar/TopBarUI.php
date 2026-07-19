<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Frontend\TopBar;

use DomainException;
use SilverStripe\Admin\AdminRootController;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\FeatureFlag\FeatureFlag;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\TemplateEngine\SSTemplateEngine;
use SilverStripe\View\ViewLayerData;
use Throwable;

/**
 * @phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
 * @phpcs:disable SlevomatCodingStandard.Functions.FunctionLength.FunctionLength
 * @phpcs:disable SlevomatCodingStandard.Files.FunctionLength.FunctionLength
 * @phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
 * @phpcs:disable SlevomatCodingStandard.Exceptions.DisallowNonCapturingCatch.DisallowedNonCapturingCatch
 * @phpcs:disable SlevomatCodingStandard.Files.LineLength.LineTooLong
 * @phpcs:disable Generic.Files.LineLength.TooLong
 */
class TopBarUI
{

    use Configurable;
    use Injectable;

    private const string ENV_DEV = 'dev';
    private const string ENV_UAT = 'uat';
    private const string ENV_TEST = 'test';

    private static array $environments = [
        self::ENV_DEV => 'dev',
        'live' => 'prod',
    ];

    /**
     * Location of the application favicon
     *
     * @config
     */
    private static string $favicon = 'themes/app/dist/images/favicon/favicon-32x32.png';

    /**
     * Collection of URL partials that won't have the bar rendered
     *
     * @config
     */
    private static array $disable_on_url = [];

    /**
     * Instance of current http request
     */
    protected ?HTTPRequest $request = null;

    /**
     * URL used for iframe
     */
    protected string $iframeUrl = '';

    /**
     * Set current instance of http request
     */
    public function setRequest(HTTPRequest $request): self
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Set URL for iframe
     */
    public function setIframeUrl(string $url): self
    {
        $this->iframeUrl = $url;

        return $this;
    }

    /**
     * Get type of current environment
     */
    public function getEnvironmentLabel(): string
    {
        $env = (string) Environment::getEnv('SS_ENVIRONMENT_TYPE');

        // If environment type defined in config, return its value
        if (array_key_exists($env, self::$environments)) {
            return self::$environments[$env];
        }

        // For test environments, find UAT or Test
        $uatCwp = (string) Environment::getEnv('CWP_ENVIRONMENT');
        $uatCloud = (string) Environment::getEnv('CL_ENVIRONMENT');

        if (str_contains($uatCwp, 'uat') || str_contains($uatCloud, 'uat')) {
            return self::ENV_UAT;
        }

        // Default is always test
        return self::ENV_TEST;
    }

    /**
     * Get URL for app. Used for CMS logo
     */
    public function getLogoUrl(): string
    {
        return $this->getAdminUrl('pages');
    }

    /**
     * Get name of the application
     */
    public function getAppName(): string
    {
        return (string)SiteConfig::current_site_config()->getTitle();
    }

    /**
     * Get profile name to display, full name, or first name, or email username
     */
    public function getProfileUsername(): string
    {
        $member = Security::getCurrentUser();

        if (!$member instanceof Member) {
            return 'Login';
        }

        if ($member->Surname && $member->FirstName) {
            return sprintf('%s %s', $member->FirstName, $member->Surname);
        }

        if ($member->FirstName) {
            return (string)$member->FirstName;
        }

        $email = explode('@', (string)$member->Email);

        return $email[0];
    }

    /**
     * Get JSON string for list of items to display in the topbar
     */
    public function getItemsAsJson(): string
    {
        try {
            $items = [
                [
                    'id' => 'topbar-1',
                    'css_modifier' => 'edit',
                    'icon' => 'font-icon-edit',
                    'label' => 'Edit page',
                    'link' => '/', // updated by betamask-iframe.js
                ],
                [
                    'id' => 'topbar-2',
                    'css_modifier' => 'settings',
                    'icon' => 'font-icon-menu-settings',
                    'label' => 'Site settings',
                    'link' => $this->getAdminUrl('settings'),
                ],
                [
                    'id' => 'topbar-3',
                    'css_modifier' => 'help',
                    'icon' => 'font-icon-white-question',
                    'label' => 'Help',
                    'items' => $this->getHelpLinks(),
                    'heading' => (string)SSTemplateEngine::execute_template(
                        BASE_PATH . '/vendor/silverstripeltd/betamask-bao/templates/SilverStripe/Frontend/TopBarVersion.ss',
                        ViewLayerData::create([
                            'CMSVersionNumber' => LeftAndMain::singleton()->CMSVersionNumber(),
                        ]),
                    ),
                ],
                [
                    'id' => 'topbar-4',
                    'css_modifier' => 'username',
                    'icon' => 'font-icon-torso',
                    'label' => $this->getProfileUsername(),
                    'items' => [
                        [
                            'link' => $this->getAdminUrl('myprofile'),
                            'label' => 'My profile',
                            'target' => '_blank',
                        ],
                        [
                            'link' => Security::logout_url(),
                            'label' => 'Logout',
                            'target' => '_blank',
                        ],
                    ],
                ],
            ];

            return (string)json_encode($items, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return '[]';
        }
    }

    /**
     * Get list of help links for a topbar item
     */
    protected function getHelpLinks(): array
    {
        $items = [];
        $helpLinks = LeftAndMain::singleton()->getHelpLinks();

        foreach ($helpLinks as $helpLink) {
            $items[] = [
                'link' => $helpLink->URL,
                'label' => $helpLink->Title,
                'target' => '_blank',
            ];
        }

        return $items;
    }

    /**
     * Get URL to admin page
     */
    protected function getAdminUrl(string $action): string
    {
        return '/' . ltrim(AdminRootController::admin_url($action), '/');
    }

    /**
     * Whether or not the topbar is enabled or allowed in the current request
     */
    public function isEnabled(): bool
    {
        // Current logged in user
        $member = Security::getCurrentUser();
        // Indicate of iframe usage cms preview or betamask preview
        $isInlinePreviewFrame = $this->request->getVar('InlinePreview');
        $isCMSIFrame = $this->request->getVar('CMSPreview');
        // Check if feature enabled by current user
        $enabled = FeatureFlag::withBetamaskFrontend(static function () {
            return true;
        }, static function () {
            return false;
        });

        // Topbar should not be visible if
        // Member is not logged in, or
        // Fabricator enabled, or
        // Request is inline preview or topbar iframe or
        // CLI and ajax requests or
        // Only if request method is GET or
        // If we have custom header to indicate its iframe X-Betamask-InlinePreview
        if (!$enabled
            || !$member instanceof Member
            || $isCMSIFrame
            || $isInlinePreviewFrame
            || (array_key_exists('HTTP_SEC_FETCH_DEST', $_SERVER) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'iframe')
            || Director::is_cli()
            || Director::is_ajax()
            || !$this->request->isGET()
            || $this->request->getHeader('X-Betamask-InlinePreview')
        ) {
            return false;
        }

        // Disable topbar from specific pages
        $urls = (array) self::config()->get('disable_on_url');

        // Hardcoded URLs
        $urls[] = Security::login_url();
        $urls[] = Security::logout_url();
        $urls[] = Security::lost_password_url();
        $urls[] = 'Security/changepassword';
        $urls[] = 'dev/';
        $urls[] = 'admin/';
        $urls[] = 'health/check';
        $urls[] = 'bigcommerce/api';

        $currentURL = rtrim($this->request->getURL(), '/') . '/';

        foreach ($urls as $url) {
            if (str_starts_with($currentURL, $url)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get HTML content for betamask interface
     */
    public function getInterface(): string|DBHTMLText
    {
        if (!$this->iframeUrl) {
            throw new DomainException('Unable to find the iframe URL.');
        }

        // Render the wrapper as a self-contained document. Its CSS/JS are linked
        // directly in the template (see TopBar.ss) rather than via the Requirements
        // API, so the actual page's requirements (app theme CSS etc.) - registered
        // while the middleware rendered the iframe content - are NOT leaked into
        // this chrome. The real page loads its own CSS inside the iframe.
        return SSTemplateEngine::execute_template(
            BASE_PATH . '/vendor/silverstripeltd/betamask-bao/templates/SilverStripe/Frontend/TopBar.ss',
            ViewLayerData::create([
                'Favicon' => ModuleResourceLoader::resourceURL(self::config()->get('favicon')),
                'FrameURL' => $this->iframeUrl,
                'AppName' => $this->getAppName(),
                'AppEnv' => $this->getEnvironmentLabel(),
                'LogoUrl' => $this->getLogoUrl(),
                'CMSVersionNumber' => LeftAndMain::singleton()->CMSVersionNumber(),
                'ItemsAsJson' => $this->getItemsAsJson(),
            ]),
        );
    }

}
