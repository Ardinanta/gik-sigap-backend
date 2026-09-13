<?php

use App\Support\IndonesianPhoneNumber;

it('normalizes supported Indonesian mobile number formats', function (string $input, string $expected) {
    expect(IndonesianPhoneNumber::normalize($input))->toBe($expected);
})->with([
    'local prefix' => ['081234567890', '6281234567890'],
    'subscriber prefix' => ['81234567890', '6281234567890'],
    'country prefix' => ['6281234567890', '6281234567890'],
    'international prefix' => ['+6281234567890', '6281234567890'],
    'formatted local number' => ['0812-3456-7890', '6281234567890'],
    'formatted international number' => ['+62 812 3456 7890', '6281234567890'],
]);

it('rejects unsupported or invalid phone numbers', function (string $input) {
    expect(IndonesianPhoneNumber::normalize($input))->toBeNull();
})->with([
    'empty' => [''],
    'letters' => ['0812abc45678'],
    'punctuation' => ['(0812) 3456 7890'],
    'non-mobile Indonesian number' => ['627712345678'],
    'unsupported international number' => ['+12025550123'],
    'plus with local prefix' => ['+081234567890'],
    'too short' => ['08123456'],
    'too long' => ['0812345678901234'],
]);
