<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Frontend\TopBar;

use SilverStripe\Control\HTTPRequest;

/**
 * Classifies a request by the browsing context it was made from, using the
 * browser's Fetch Metadata headers with fallbacks for browsers that omit them.
 *
 * The top bar renders the real page inside an iframe. Only top-level page
 * navigations are wrapped; requests from that iframe are served normally and
 * load the inline script that keeps the bar in sync with the framed page.
 */
final class FrameRequest
{

    /**
     * Query parameter appended to URLs loaded inside the top bar iframe
     */
    public const string INLINE_PREVIEW_PARAM = 'InlinePreview';

    /**
     * Query parameter the CMS appends to URLs loaded in its own preview panel
     */
    public const string CMS_PREVIEW_PARAM = 'CMSPreview';

    private const string FETCH_DEST_HEADER = 'Sec-Fetch-Dest';

    private const string FETCH_MODE_HEADER = 'Sec-Fetch-Mode';

    private const array FRAME_DESTINATIONS = ['iframe', 'frame'];

    private const string DOCUMENT_DESTINATION = 'document';

    private const string NAVIGATE_MODE = 'navigate';

    private const array HTML_MEDIA_TYPES = ['text/html', 'application/xhtml+xml'];

    /**
     * Whether the request is rendered inside the CMS preview panel
     */
    public static function isCmsPreview(HTTPRequest $request): bool
    {
        return (bool) $request->getVar(self::CMS_PREVIEW_PARAM);
    }

    /**
     * Whether the request was made from any frame.
     *
     * The query parameter covers browsers that do not send Fetch Metadata headers.
     */
    public static function isFramed(HTTPRequest $request): bool
    {
        if ($request->getVar(self::INLINE_PREVIEW_PARAM)) {
            return true;
        }

        return in_array(self::getHeader($request, self::FETCH_DEST_HEADER), self::FRAME_DESTINATIONS, true);
    }

    /**
     * Whether the request is a page navigation inside the top bar iframe
     */
    public static function isTopBarFrame(HTTPRequest $request): bool
    {
        return self::isFramed($request) && !self::isCmsPreview($request);
    }

    /**
     * Whether the request is a top-level browser navigation to an HTML page,
     * as opposed to a frame, a fetch()/XHR call, a sub-resource, or a file URL.
     */
    public static function isPageNavigation(HTTPRequest $request): bool
    {
        if (self::isFramed($request) || self::hasFileExtension($request)) {
            return false;
        }

        return self::isDocumentFetch($request) && self::acceptsHtml($request);
    }

    /**
     * Fetch Metadata, when the browser sends it, must describe a document navigation
     */
    private static function isDocumentFetch(HTTPRequest $request): bool
    {
        $destination = self::getHeader($request, self::FETCH_DEST_HEADER);
        $mode = self::getHeader($request, self::FETCH_MODE_HEADER);

        return in_array($destination, ['', self::DOCUMENT_DESTINATION], true)
            && in_array($mode, ['', self::NAVIGATE_MODE], true);
    }

    private static function acceptsHtml(HTTPRequest $request): bool
    {
        $accept = self::getHeader($request, 'Accept');

        if ($accept === '') {
            return true;
        }

        foreach (self::HTML_MEDIA_TYPES as $mediaType) {
            if (str_contains($accept, $mediaType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Page URLs have no file extension; sitemap.xml, robots.txt, feeds and file
     * downloads do, and are never wrapped.
     */
    private static function hasFileExtension(HTTPRequest $request): bool
    {
        $segments = explode('/', trim($request->getURL(), '/'));

        return pathinfo((string) end($segments), PATHINFO_EXTENSION) !== '';
    }

    private static function getHeader(HTTPRequest $request, string $name): string
    {
        return strtolower(trim((string) $request->getHeader($name)));
    }

}
