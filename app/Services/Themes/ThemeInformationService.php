<?php
declare(strict_types=1);

namespace App\Services\Themes;

use App\Exceptions\NotFoundException;
use App\Models\WpOrg\Theme;
use App\Values\WpOrg\Themes\ThemeInformationRequest;
use App\Values\WpOrg\Themes\ThemeResponse;

class ThemeInformationService
{
    public function info(ThemeInformationRequest $req): ThemeResponse
    {
        $theme = Theme::query()->where('slug', $req->slug)->first() or throw new NotFoundException("Theme not found");
        $fields = $req->responseFields();
        return ThemeResponse::fromModel(
            $theme,
            $req->apiVersion === '1.0' ? null : $fields,
        )->withFields($fields);
    }
}
