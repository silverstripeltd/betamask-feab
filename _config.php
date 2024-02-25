<?php

use SilverStripe\Betamask\Frontend\TopBar;
use SilverStripe\FeatureFlag\FeatureFlag;

FeatureFlag::singleton()->register(TopBar\FeatureFlag::class);
