<?php
declare(strict_types=1);

use App\Values\WpOrg\ResponseFields;
use App\Values\WpOrg\Themes\QueryThemesRequest;
use App\Values\WpOrg\Themes\ThemeInformationRequest;

it('normalizes supported field switches', function (mixed $value, bool $expected) {
    expect(ResponseFields::resolve(['sections' => $value], ['sections' => !$expected]))
        ->toBe(['sections' => $expected]);
})->with([
    [true, true],
    [false, false],
    [1, true],
    [0, false],
    ['1', true],
    ['0', false],
    ['TRUE', true],
    ['false', false],
]);

it('ignores malformed switches without replacing defaults', function (mixed $value) {
    expect(ResponseFields::resolve(['sections' => $value], ['sections' => true]))->toBe(['sections' => true]);
    expect(ResponseFields::resolve(['sections' => $value], ['sections' => false]))->toBe(['sections' => false]);
})->with([[null], [[]], [['nested']], [2], ['yes'], [''], [new stdClass()]]);

it('accepts lists and comma separated fields', function (mixed $fields) {
    expect(ResponseFields::resolve($fields, ['sections' => false, 'versions' => false, 'description' => true]))
        ->toBe(['sections' => true, 'versions' => true, 'description' => true]);
})->with([[['sections', 'versions']], [' sections, versions,unknown'], [['sections', [], 'versions']]]);

it('ignores unsupported top-level field values', function (mixed $fields) {
    expect(ResponseFields::resolve($fields, ['sections' => false]))->toBe(['sections' => false]);
})->with([[null], [false], [1], [new stdClass()], [[]]]);

it('prefers the official downloadlink alias independently of key order', function (array $fields) {
    expect(ResponseFields::resolve($fields, ['download_link' => true]))->toBe(['download_link' => false]);
})->with([
    [['downloadlink' => false, 'download_link' => true]],
    [['download_link' => true, 'downloadlink' => false]],
]);

it('uses extended authors by default only for theme API 1.2', function (string $version, bool $expected) {
    $req = new QueryThemesRequest(apiVersion: $version);
    expect($req->responseFields()['extended_author'])->toBe($expected);
    $req = new QueryThemesRequest(fields: ['extended_author' => !$expected], apiVersion: $version);
    expect($req->responseFields()['extended_author'])->toBe(!$expected);
})->with([['1.1', false], ['1.2', true]]);

it('allows a separate last_updated_time override', function () {
    $req = new ThemeInformationRequest('example', fields: ['last_updated' => false, 'last_updated_time' => true]);
    expect($req->responseFields())->toMatchArray(['last_updated' => false, 'last_updated_time' => true]);
    $req = new QueryThemesRequest(fields: ['last_updated' => true, 'last_updated_time' => false]);
    expect($req->responseFields())->toMatchArray(['last_updated' => true, 'last_updated_time' => false]);
});
