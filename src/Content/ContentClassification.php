<?php

declare(strict_types=1);

namespace Limb\Content;

enum ContentClassification: string
{
    case Layout = 'layout';
    case Include = 'include';
    case Data = 'data';
    case Post = 'post';
    case Draft = 'draft';
    case Page = 'page';
    case Static = 'static';
}
