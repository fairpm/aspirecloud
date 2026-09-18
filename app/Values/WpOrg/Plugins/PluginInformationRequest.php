<?php
declare(strict_types=1);

namespace App\Values\WpOrg\Plugins;

use App\Values\DTO;
use Bag\Attributes\StripExtraParameters;
use Bag\Attributes\Transforms;
use Illuminate\Http\Request;

#[StripExtraParameters]
readonly class PluginInformationRequest extends DTO
{
    public const ACTION = 'plugin_information';

    public function __construct(
        public string $slug,
        public mixed $fields = null,
    ) {}

    /** @return array<string, mixed> */
    #[Transforms(Request::class)]
    public static function fromRequest(Request $request): array
    {
        // Bag throws 500 (RuntimeException) for missing fields, this throws a friendlier 422
        return $request->validate(['slug' => 'required']) + ['fields' => $request->query('fields')];
    }
}
