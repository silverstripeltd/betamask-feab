<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Frontend\TopBar;

use SilverStripe\FeatureFlag\FeatureFlagInterface;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;

/**
 * Per-member feature flag that turns the front-end top bar on or off
 *
 * @phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
 */
class FeatureFlag implements FeatureFlagInterface
{

    public const string NAME = 'BetamaskFrontend';

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Enabled per member from their profile. Members with the Fabricator editor
     * enabled never get the bar, as Fabricator provides its own front-end chrome.
     */
    public function checkFeature(?Member $member = null): bool
    {
        return $member instanceof Member
            && (bool) $member->getField('FeatureFlag_' . self::NAME)
            && !$member->getField('FabricatorEnabled');
    }

    /**
     * The flag shows no banner message in the CMS
     */
    public function showMessage(?Member $member = null): bool
    {
        return false;
    }

    public function getMessage(): DBField
    {
        return DBField::create_field('Text', '');
    }

}
