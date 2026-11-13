<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Frontend\TopBar\Extension;

use SilverStripe\Betamask\Frontend\TopBar\TopBarContext;
use SilverStripe\Betamask\Frontend\TopBar\TopBarUI;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Records the page being rendered, so the admin tool bar can link to its CMS
 * edit form and show which reading stage it is in.
 *
 * Controllers can provide the edit link by implementing getPageEditUrl();
 * otherwise the CMS edit link of the record returned by data() is used.
 *
 * @extends Extension<Controller>
 */
class InitController extends Extension
{

    public function onBeforeInit(): void
    {
        if (!TopBarUI::isAvailableToCurrentMember()) {
            return;
        }

        $context = TopBarContext::singleton()->setStage((string) Versioned::get_stage());
        $editUrl = $this->getPageEditUrl();

        // Nested controllers (e.g. RootURLController then the page controller)
        // each run this hook; the innermost one with a record wins.
        if ($editUrl === '') {
            return;
        }

        $context->setEditUrl($editUrl);
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
