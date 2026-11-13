<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Frontend\TopBar;

use JsonException;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\TemplateEngine\SSTemplateEngine;
use SilverStripe\View\ViewLayerData;

/**
 * Renders the admin tool bar document shown inside the bar's iframe
 *
 * @phpcs:disable SlevomatCodingStandard.Exceptions.DisallowNonCapturingCatch.DisallowedNonCapturingCatch
 */
class TopBarRenderer
{

    use Configurable;
    use Injectable;

    public const string TEMPLATE = 'SilverStripe/Betamask/Frontend/TopBar/TopBar';

    private const string ICON_RESOURCE = 'silverstripeltd/betamask-bao: client/dist/icons/bar/%s.svg';

    private const string ENV_UAT = 'uat';

    private const string ENV_TEST = 'test';

    /**
     * Where the Silverstripe logo at the start of the bar links to; opens in a new tab
     *
     * @config
     */
    private static string $logo_url = 'https://www.silverstripe.org/';

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
     * Full HTML document of the bar
     *
     * @param string $editUrl CMS edit link of the page the bar is shown on
     * @param string $stage Reading stage of that page ("Stage", "Live" or "")
     */
    public function render(string $editUrl, string $stage, bool $collapsed): string
    {
        return SSTemplateEngine::execute_template(
            self::TEMPLATE,
            ViewLayerData::create([
                'Title' => $this->getAppName(),
                'ConfigJson' => $this->getConfigJson($editUrl, $stage, $collapsed),
            ]),
        );
    }

    /**
     * Properties for the Vue bar, encoded as JSON
     */
    public function getConfigJson(string $editUrl, string $stage, bool $collapsed): string
    {
        $menu = TopBarMenu::create()->setEditUrl($editUrl);
        $config = [
            'logo' => $this->getIconUrl('glyph'),
            'menuIcon' => $this->getIconUrl('menu'),
            'caretIcon' => $this->getIconUrl('caret'),
            'logoUrl' => (string) self::config()->get('logo_url'),
            'siteUrl' => Director::absoluteBaseURL(),
            'appName' => $this->getAppName(),
            'appEnv' => $this->getEnvironmentLabel(),
            'stage' => $stage,
            'collapsed' => $collapsed,
            'items' => $menu->getItems(),
        ];

        try {
            return json_encode($config, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (JsonException) {
            return '{}';
        }
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

    protected function getIconUrl(string $name): string
    {
        return (string) ModuleResourceLoader::resourceURL(sprintf(self::ICON_RESOURCE, $name));
    }

    protected function getAppName(): string
    {
        return (string) SiteConfig::current_site_config()->getTitle();
    }

}
