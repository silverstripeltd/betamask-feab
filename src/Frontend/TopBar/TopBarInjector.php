<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Frontend\TopBar;

use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Manifest\ModuleResourceLoader;

/**
 * Adds the admin tool bar to a rendered HTML page.
 *
 * Two tags are added and nothing else in the page is changed: a stylesheet in
 * the head that moves the page's content down by the bar's height, and the
 * loader script before </body> that shows the bar in an isolated iframe.
 */
class TopBarInjector
{

    use Injectable;

    public const string LOADER_SCRIPT = 'silverstripeltd/betamask-bao: client/dist/betamask-loader.js';

    public const string OFFSET_STYLESHEET = 'silverstripeltd/betamask-bao: client/dist/betamask-offset.css';

    private const string HTML_CONTENT_TYPE = 'text/html';

    /**
     * Whether the response is a full HTML page the bar can be added to
     */
    public function canInject(HTTPResponse $response): bool
    {
        if ($response->isRedirect() || !$this->isHtml($response) || $this->isAttachment($response)) {
            return false;
        }

        return stripos((string) $response->getBody(), '</body>') !== false;
    }

    /**
     * Add the bar to the page
     *
     * @param string $barUrl URL of the bar document to show in the iframe
     * @param bool $collapsed Whether the member has collapsed the bar
     */
    public function inject(HTTPResponse $response, string $barUrl, bool $collapsed): HTTPResponse
    {
        $body = (string) $response->getBody();
        $body = $this->insertBefore($body, '</head>', $this->getOffsetTag($collapsed), false);
        $body = $this->insertBefore($body, '</body>', $this->getLoaderTag($barUrl, $collapsed), true);

        return $response->setBody($body);
    }

    protected function getOffsetTag(bool $collapsed): string
    {
        return sprintf(
            '<link rel="stylesheet" href="%s" media="%s" data-betamask-offset>',
            Convert::raw2att((string) ModuleResourceLoader::resourceURL(self::OFFSET_STYLESHEET)),
            $collapsed ? 'not all' : 'all',
        );
    }

    protected function getLoaderTag(string $barUrl, bool $collapsed): string
    {
        return sprintf(
            '<script type="module" src="%s" data-betamask-bar="%s" data-betamask-collapsed="%s"></script>',
            Convert::raw2att((string) ModuleResourceLoader::resourceURL(self::LOADER_SCRIPT)),
            Convert::raw2att($barUrl),
            $collapsed ? '1' : '0',
        );
    }

    /**
     * Insert markup before the first (or last) occurrence of a closing tag,
     * leaving the body unchanged when the tag is missing
     */
    protected function insertBefore(string $body, string $closingTag, string $markup, bool $last): string
    {
        $position = $last
            ? strripos($body, $closingTag)
            : stripos($body, $closingTag);

        if ($position === false) {
            return $body;
        }

        return substr_replace($body, $markup, $position, 0);
    }

    private function isHtml(HTTPResponse $response): bool
    {
        $contentType = strtolower((string) $response->getHeader('Content-Type'));

        return $contentType === '' || str_starts_with($contentType, self::HTML_CONTENT_TYPE);
    }

    private function isAttachment(HTTPResponse $response): bool
    {
        return str_contains(strtolower((string) $response->getHeader('Content-Disposition')), 'attachment');
    }

}
