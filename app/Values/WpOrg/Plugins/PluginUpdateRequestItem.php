<?php
declare(strict_types=1);

namespace App\Values\WpOrg\Plugins;

use App\Models\WpOrg\Plugin;
use App\Utils\Regex;
use App\Values\DTO;
use Bag\Attributes\StripExtraParameters;
use Bag\Attributes\Transforms;
use Bag\Values\Optional;

#[StripExtraParameters]
readonly class PluginUpdateRequestItem extends DTO
{
    public function __construct(
        public ?string $Name = null,
        public ?string $Version = null,     // if null, either any version is an update or none is.  not sure which.
        public ?string $Description = null,
        public ?string $Title = null,       // not sure how this differs from $Name...
        public ?string $PluginURI = null,
        public ?string $Author = null,
        public ?string $AuthorName = null,
        public ?string $AuthorURI = null,
        public ?string $RequiresWP = null,
        public ?string $RequiresPHP = null,
        public ?string $RequiresPlugins = null,
        public ?string $TextDomain = null,
        public ?string $DomainPath = null,
        public ?string $Network = null,
        public ?string $UpdateURI = null,
    ) {}

    public function hasValidUpdateUri(): bool
    {
        // '!^(https?://)?(wordpress.org|w.org)/plugins?/(?P<slug>[^/]+)/?$!i'
        // NOTE: we do not match the slug because we don't know it.  This should be more than good enough.
        return !$this->UpdateURI || Regex::match('!(?:https?://)?(?:wordpress\.org|w\.org)/plugins?/!', $this->UpdateURI);
    }
}
