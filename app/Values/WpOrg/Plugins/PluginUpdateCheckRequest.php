<?php
declare(strict_types=1);

namespace App\Values\WpOrg\Plugins;

use App\Utils\JSON;
use App\Values\DTO;
use Bag\Attributes\Transforms;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * @phpstan-type TranslationMetadata array{
 *     POT-Creation-Date: string,
 *     PO-Revision-Date: string,
 *     Project-Id-Version: string,
 *     X-Generator: string
 * }
 */
readonly class PluginUpdateCheckRequest extends DTO
{
    /**
     * @param Collection<string, PluginUpdateRequestItem> $plugins
     * @param array<string, array<string, TranslationMetadata>> $translations
     * @param list<string> $locale
     */
    public function __construct(
        public Collection $plugins,
        public array $translations,
        public array $locale,
        public bool $all = false,
    ) {}

    /** @return array<string, mixed> */
    #[Transforms(Request::class)]
    public static function fromRequest(Request $request): array
    {
        $decode = fn($key) => JSON::tryToAssoc($request->post($key) ?? '[]') ?? [];
        return [
            'plugins' => PluginUpdateRequestItem::collect($decode('plugins')['plugins']),
            'locale' => $decode('locale'),
            'translations' => $decode('translations'),
            'all' => $request->boolean('all'),
        ];
    }
}
