<?php
declare(strict_types=1);

namespace App\Values\Packages;

use App\Values\DTO;
use Bag\Attributes\Laravel\FromRouteParameter;
use Bag\Attributes\StripExtraParameters;
use Bag\Attributes\Transforms;
use Illuminate\Http\Request;

#[StripExtraParameters]
readonly class PackageSearchRequest extends DTO
{
    /** @param array<string, string>|null $requires */
    public function __construct(
        #[FromRouteParameter]
        public string $type,
        public ?string $q = null,
        public ?array $requires = null,
        public int $page = 1,
        public int $per_page = 24,
    ) {}

    /** @return array<string, mixed> */
    #[Transforms(Request::class)]
    public static function fromRequest(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'requires' => ['nullable', 'array'],
            'requires.*' => ['string', 'max:20'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return [
            'type' => $request->route('type'),
            'q' => $validated['q'] ?? null,
            'requires' => $validated['requires'] ?? null,
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 24),
        ];
    }
}
