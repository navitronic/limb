<?php

declare(strict_types=1);

namespace Limb\Event;

use Limb\Model\Document;
use Limb\Model\Site;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after all documents have been rendered.
 */
final class AfterRenderEvent extends Event
{
    /**
     * @param Document[] $documents
     */
    public function __construct(
        public readonly array $documents,
        public readonly Site $site,
    ) {
    }
}
