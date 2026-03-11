<?php

declare(strict_types=1);

namespace Limb\Event;

use Limb\Model\BuildResult;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after the build is complete and output has been written.
 */
final class BuildCompleteEvent extends Event
{
    public function __construct(
        public readonly BuildResult $result,
    ) {
    }
}
