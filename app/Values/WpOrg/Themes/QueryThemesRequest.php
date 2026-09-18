<?php
declare(strict_types=1);

namespace App\Values\WpOrg\Themes;

use App\Values\DTO;
use Bag\Attributes\StripExtraParameters;
use Bag\Attributes\Transforms;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

#[StripExtraParameters]
readonly class QueryThemesRequest extends DTO
{
    use ThemeFields;

    public const ACTION = 'query_themes';

    public const DEFAULT_FIELDS = [
        'description' => true,
        'rating' => true,
        'homepage' => true,
        'template' => true,
    ];

    /**
     * @param list<string>|null $tags
     * @param list<string>|null $ac_tags
     * @param mixed $fields Raw WordPress field selector, normalized by ThemeFields.
     */
    public function __construct(
        public null|string $search = null, // text to search
        public null|array $tags = null, // tag or set of tags
        public null|string $theme = null, // slug of a specific theme
        public null|string $author = null, // wp.org username of author
        public null|string $browse = null, // one of popular|featured|updated|new
        public mixed $fields = null,
        public int $page = 1,
        public int $per_page = 24,

        // AspireCloud-specific extensions
        public null|array $ac_tags = null, // tag or set of tags, AND'ed together
        public string $apiVersion = '1.2',
    ) {}

    /** @return array<string, mixed> */
    #[Transforms(Request::class)]
    public static function fromRequest(Request $request): array
    {
        $query = $request->query->all();
        $query['tags'] = Arr::wrap(Arr::pull($query, 'tag', []));
        $query['ac_tags'] = Arr::wrap(Arr::pull($query, 'ac_tag', []));

        $query['apiVersion'] = $request->route('version') ?? '1.2';
        $query['fields'] = $query['apiVersion'] === '1.0'
            ? self::getLegacyFields($request, self::DEFAULT_FIELDS)
            : $request->query('fields');
        return $query;
    }

    /** @return array<string, bool> */
    public function responseFields(): array
    {
        return $this->selectFields(self::DEFAULT_FIELDS);
    }
}
