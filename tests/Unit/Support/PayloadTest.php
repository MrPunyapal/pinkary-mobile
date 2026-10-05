<?php

declare(strict_types=1);

use App\Support\Payload;
use Tests\TestCase;

uses(TestCase::class);

it('passes strings through unchanged', function (): void {
    expect(Payload::string('hello'))->toBe('hello');
    expect(Payload::string(''))->toBe('');
});

it('defaults null strings to the fallback', function (): void {
    expect(Payload::string(null))->toBe('');
    expect(Payload::string(null, 'fallback'))->toBe('fallback');
});

it('casts scalar strings the way PHP would', function (): void {
    expect(Payload::string(42))->toBe('42');
    expect(Payload::string(4.5))->toBe('4.5');
    expect(Payload::string(true))->toBe('1');
    expect(Payload::string(false))->toBe('');
});

it('defaults non-representable strings rather than raising an error', function (): void {
    expect(Payload::string([]))->toBe('');
    expect(Payload::string(['answer' => 'hi']))->toBe('');
    expect(Payload::string(new stdClass))->toBe('');
    expect(Payload::string([], 'fallback'))->toBe('fallback');
    expect(Payload::string(new stdClass, 'fallback'))->toBe('fallback');
});

it('passes integers through unchanged', function (): void {
    expect(Payload::int(7))->toBe(7);
    expect(Payload::int(0))->toBe(0);
    expect(Payload::int(-3))->toBe(-3);
});

it('defaults null integers to the fallback', function (): void {
    expect(Payload::int(null))->toBe(0);
    expect(Payload::int(null, 12))->toBe(12);
});

it('narrows booleans and floats to integers', function (): void {
    expect(Payload::int(true))->toBe(1);
    expect(Payload::int(false))->toBe(0);
    expect(Payload::int(4.9))->toBe(4);
    expect(Payload::int(-4.9))->toBe(-4);
});

it('parses numeric strings into integers', function (): void {
    expect(Payload::int('42'))->toBe(42);
    expect(Payload::int('-42'))->toBe(-42);
    expect(Payload::int('4.9'))->toBe(4);
});

it('defaults non-numeric strings instead of coercing them to zero', function (): void {
    expect(Payload::int('abc'))->toBe(0);
    expect(Payload::int('abc', 5))->toBe(5);
    expect(Payload::int([]))->toBe(0);
    expect(Payload::int(new stdClass, 5))->toBe(5);
});

it('narrows integers and floats to floats', function (): void {
    expect(Payload::float(1.5))->toBe(1.5);
    expect(Payload::float(2))->toBe(2.0);
    expect(Payload::float(-0.25))->toBe(-0.25);
});

it('defaults null floats to the fallback', function (): void {
    expect(Payload::float(null))->toBe(0.0);
    expect(Payload::float(null, 1.5))->toBe(1.5);
});

it('parses numeric strings into floats', function (): void {
    expect(Payload::float('1.5'))->toBe(1.5);
    expect(Payload::float('2'))->toBe(2.0);
});

it('sends booleans down the float default path, unlike integers', function (): void {
    expect(Payload::float(true))->toBe(0.0);
    expect(Payload::float(false))->toBe(0.0);
    expect(Payload::float(true, 1.5))->toBe(1.5);
});

it('defaults non-numeric strings and arrays to the float fallback', function (): void {
    expect(Payload::float('abc'))->toBe(0.0);
    expect(Payload::float([]))->toBe(0.0);
    expect(Payload::float('abc', 2.5))->toBe(2.5);
});

it('passes booleans through unchanged', function (): void {
    expect(Payload::bool(true))->toBeTrue();
    expect(Payload::bool(false))->toBeFalse();
});

it('defaults null booleans to the fallback', function (): void {
    expect(Payload::bool(null))->toBeFalse();
    expect(Payload::bool(null, true))->toBeTrue();
});

it('narrows scalars to booleans the way PHP would', function (): void {
    expect(Payload::bool(1))->toBeTrue();
    expect(Payload::bool(0))->toBeFalse();
    expect(Payload::bool(-1))->toBeTrue();
    expect(Payload::bool('1'))->toBeTrue();
    expect(Payload::bool('0'))->toBeFalse();
    expect(Payload::bool(''))->toBeFalse();
});

it('treats any other non-empty string as true, including the word false', function (): void {
    expect(Payload::bool('false'))->toBeTrue();
    expect(Payload::bool('no'))->toBeTrue();
});

it('defaults non-representable booleans to the fallback', function (): void {
    expect(Payload::bool([]))->toBeFalse();
    expect(Payload::bool(new stdClass))->toBeFalse();
    expect(Payload::bool([], true))->toBeTrue();
});

it('passes real arrays through unchanged', function (): void {
    expect(Payload::array(['answer' => 'hi']))->toBe(['answer' => 'hi']);
    expect(Payload::array([]))->toBe([]);
    expect(Payload::array([['id' => 'q1']]))->toBe([['id' => 'q1']]);
});

it('defaults non-arrays to the fallback without decoding json', function (): void {
    expect(Payload::array(null))->toBe([]);
    expect(Payload::array('{"answer":"hi"}'))->toBe([]);
    expect(Payload::array(42))->toBe([]);
    expect(Payload::array(null, ['answer' => 'fallback']))->toBe(['answer' => 'fallback']);
});

it('keeps only string keys in a string map', function (): void {
    expect(Payload::stringMap(['answer' => 'hi', 'id' => 1]))->toBe(['answer' => 'hi', 'id' => 1]);
    expect(Payload::stringMap([]))->toBe([]);
});

it('drops integer keys rather than coercing them into a string-keyed shape', function (): void {
    expect(Payload::stringMap([['id' => 'q1'], ['id' => 'q2']]))->toBe([]);
    expect(Payload::stringMap([0 => 'a', 1 => 'b']))->toBe([]);
});

it('defaults a string map to the fallback for non-arrays', function (): void {
    expect(Payload::stringMap(null))->toBe([]);
    expect(Payload::stringMap('{"answer":"hi"}'))->toBe([]);
    expect(Payload::stringMap(null, ['answer' => 'fallback']))->toBe(['answer' => 'fallback']);
});

it('applies the string key filter to the fallback too', function (): void {
    expect(Payload::stringMap(null, [['id' => 'q1']]))->toBe([]);
});
