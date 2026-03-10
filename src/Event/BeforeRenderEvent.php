<?php

declare(strict_types=1);

namespace App\Event;

use App\Model\Document;
use App\Model\Site;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched before document rendering begins.
 */
final class BeforeRenderEvent extends Event
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
