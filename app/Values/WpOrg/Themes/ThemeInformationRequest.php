<?php
declare(strict_types=1);

namespace App\Values\WpOrg\Themes;

use App\Values\DTO;
use Bag\Attributes\StripExtraParameters;
use Illuminate\Http\Request;

#[StripExtraParameters]
readonly class ThemeInformationRequest extends DTO
{
    use ThemeFields;

    public const ACTION = 'theme_information';

    public const DEFAULT_FIELDS = [
        'sections' => true,
        'rating' => true,
        'downloaded' => true,
        'download_link' => true,
        'last_updated' => true,
        'last_updated_time' => true,
        'homepage' => true,
        'tags' => true,
        'template' => true,
    ];

    public function __construct(
        public string $slug,
        public mixed $fields = null,
        public string $apiVersion = '1.2',
    ) {}

    public static function fromRequest(Request $request): static
    {
        // this sort of defeats the purpose of Bag, but Bag doesn't throw validation failure on missing props, since it
        // checks for missing props before it runs validation rules (which is why overriding rules() won't work either).
        // TODO: generalize from on request classes to convert MissingPropertiesException to ValidationException
        $req = $request->validate(['slug' => 'required']);

        $req['apiVersion'] = $request->route('version') ?? '1.2';
        $req['fields'] = $req['apiVersion'] === '1.0'
            ? self::getLegacyFields($request, self::DEFAULT_FIELDS)
            : $request->query('fields');

        return static::from($req);
    }

    /** @return array<string, bool> */
    public function responseFields(): array
    {
        return $this->selectFields(self::DEFAULT_FIELDS, information: true);
    }
}
