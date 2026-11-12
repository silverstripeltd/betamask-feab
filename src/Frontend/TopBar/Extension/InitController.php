<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Frontend\TopBar\Extension;

use SilverStripe\Betamask\Frontend\TopBar\FeatureFlag;
use SilverStripe\Betamask\Frontend\TopBar\FrameRequest;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\FeatureFlag\FeatureFlag as FeatureFlagRegistry;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\Requirements;

/**
 * Loads the script that keeps the top bar in sync with the page shown in its
 * iframe: browser URL and title, the "Edit page" link, and closing open menus.
 *
 * Controllers can provide the edit link by implementing getPageEditUrl();
 * otherwise the CMS edit link of the record returned by data() is used.
 *
 * @extends Extension<Controller>
 */
class InitController extends Extension
{

    public const string EDIT_URL_META = 'betamask-page-url';

    private const string INLINE_SCRIPT = 'silverstripeltd/betamask-bao: client/dist/betamask-inline.js';

    public function onBeforeInit(): void
    {
        if (!$this->isTopBarFrame()) {
            return;
        }

        // Nested controllers (e.g. RootURLController then the page controller) each
        // run this hook; Requirements de-duplicates the script, and the edit link
        // shares a uniqueness ID so the innermost controller's record wins.
        Requirements::javascript(self::INLINE_SCRIPT, ['type' => 'module']);

        $editUrl = $this->getPageEditUrl();

        if ($editUrl === '') {
            return;
        }

        Requirements::insertHeadTags(
            sprintf('<meta name="%s" content="%s">', self::EDIT_URL_META, Convert::raw2att($editUrl)),
            self::EDIT_URL_META,
        );
    }

    protected function isTopBarFrame(): bool
    {
        $request = $this->getOwner()->getRequest();

        return FrameRequest::isTopBarFrame($request)
            && FeatureFlagRegistry::checkFeatureFlag(FeatureFlag::NAME);
    }

    /**
     * CMS edit link for the record the owner controller displays
     */
    protected function getPageEditUrl(): string
    {
        $owner = $this->getOwner();

        if ($owner->hasMethod('getPageEditUrl')) {
            return (string) $owner->getPageEditUrl();
        }

        if (!$owner->hasMethod('data')) {
            return '';
        }

        $record = $owner->data();

        if (!$record instanceof DataObject || !$record->isInDB()) {
            return '';
        }

        return (string) $record->getCMSEditLink();
    }

}
