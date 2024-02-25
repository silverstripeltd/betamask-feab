<?php

namespace SilverStripe\Betamask\Frontend\TopBar;

use SilverStripe\FeatureFlag\FeatureFlagInterface;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;

/**
 * Feature flag to manage enable/disable Betamask frontend top bar
 *
 * @phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
 */
class FeatureFlag implements FeatureFlagInterface
{

    public function getName(): string
    {
        return 'BetamaskFrontend';
    }

    /**
     * Feature enabled at member level. Each member can enable/disable it
     */
    public function checkFeature(?Member $member = null): bool
    {
        return $member instanceof Member && $member->FeatureFlag_BetamaskFrontend && !$member->FabricatorEnabled;
    }

    /**
     * No banner message in CMS to display
     */
    public function showMessage(?Member $member = null): bool
    {
        return false;
    }

    public function getMessage(): DBField
    {
        return DBField::create_field('Text', 'Not implemented');
    }

}
