<?php

declare(strict_types=1);

namespace Limb\Event;

use Limb\Model\Site;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after configuration is loaded and site files are scanned, before rendering.
 */
final class SiteLoadedEvent extends Event
{
    public function __construct(
        public readonly Site $site,
    ) {
    }
}
