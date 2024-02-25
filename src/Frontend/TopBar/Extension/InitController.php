<?php

namespace SilverStripe\Betamask\Frontend\TopBar\Extension;

use PageController;
use SilverStripe\Control\Controller;
use SilverStripe\FeatureFlag\FeatureFlag;
use SilverStripe\ORM\DataExtension;
use SilverStripe\View\Requirements;

class InitController extends DataExtension
{

    private array $runOnce = [
        'inlineJs' => false,
        'meta' => false,
    ];

    public function onBeforeInit(): void
    {
        // For iframe preview add JS to ensure all links includes InlinePreview
        // to ensure the frame preview does not render the topbar UI
        FeatureFlag::withBetamaskFrontend(function (): void {
            $request = Controller::curr()->getRequest();
            $isInlinePreviewFrame = $request->getVar('InlinePreview');

            if (!$isInlinePreviewFrame || $request->getHeader('X-Betamask')) {
                return;
            }

            $this->loadInlineJs();
            $this->loadMetadata();
        });
    }

    protected function loadInlineJs(): void
    {
        if ($this->runOnce['inlineJs']) {
            return;
        }

        Requirements::javascript('silverstripeltd/betamask-bao: client/dist/betamask-inline.js', [
            'type' => 'module',
        ]);
        $this->runOnce['inlineJs'] = true;
    }

    protected function loadMetadata(): void
    {
        $url = $this->getPageEditUrl();

        if (!$url || $this->runOnce['meta']) {
            return;
        }

        // Some metadata to help with communication with parent window
        Requirements::insertHeadTags(
            sprintf('<meta name="betamask-page-url" content="%s" />', $url),
        );
        $this->runOnce['meta'] = true;
    }

    /**
     * Get CMS edit link for the current page
     */
    protected function getPageEditUrl(): string
    {
        if ($this->getOwner() instanceof PageController) {
            return $this->getOwner()->CMSEditLink();
        }

        if ($this->getOwner()->hasMethod('getPageEditUrl')) {
            return $this->getOwner()->getPageEditUrl();
        }

        return '';
    }

}
