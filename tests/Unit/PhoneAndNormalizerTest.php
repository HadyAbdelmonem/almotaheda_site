<?php

use App\Support\ArabicNormalizer;
use App\Support\Phone;

it('normalizes Egyptian phone numbers from Arabic digits and prefixes', function () {
    expect(Phone::normalize('٠١٠٠١٢٣٤٥٦٧'))->toBe('01001234567')
        ->and(Phone::normalize('+201001234567'))->toBe('01001234567')
        ->and(Phone::normalize('00201001234567'))->toBe('01001234567')
        ->and(Phone::normalize('0100 123-4567'))->toBe('01001234567')
        ->and(Phone::normalize('201001234567'))->toBe('01001234567');
});

it('validates Egyptian mobile numbers', function () {
    expect(Phone::isValidMobile('01001234567'))->toBeTrue()
        ->and(Phone::isValidMobile('01112345678'))->toBeTrue()
        ->and(Phone::isValidMobile('01212345678'))->toBeTrue()
        ->and(Phone::isValidMobile('01512345678'))->toBeTrue()
        ->and(Phone::isValidMobile('01312345678'))->toBeFalse()
        ->and(Phone::isValidMobile('0100123456'))->toBeFalse()
        ->and(Phone::isValidMobile('0223456789'))->toBeFalse();
});

it('converts phones to WhatsApp international format', function () {
    expect(Phone::toWhatsApp('01001234567'))->toBe('201001234567')
        ->and(Phone::toWhatsApp('+201001234567'))->toBe('201001234567');
});

it('normalizes Arabic text for search', function () {
    expect(ArabicNormalizer::normalize('أسلاك'))->toBe('اسلاك')
        ->and(ArabicNormalizer::normalize('لمبة'))->toBe('لمبه')
        ->and(ArabicNormalizer::normalize('قاطعٰ كهرباء'))->toBe('قاطع كهرباء')
        ->and(ArabicNormalizer::normalize('ABB S201'))->toBe('abb s201')
        ->and(ArabicNormalizer::normalize('مفتاح ١٦ أمبير'))->toBe('مفتاح 16 امبير')
        ->and(ArabicNormalizer::normalize('  كابل   نحاس '))->toBe('كابل نحاس');
});

it('builds a stable catalog identity', function () {
    $a = ArabicNormalizer::catalogIdentity('S201C16', '1');
    $b = ArabicNormalizer::catalogIdentity('s201c16', '1');
    $c = ArabicNormalizer::catalogIdentity('S201C16', '');
    expect(strlen($a))->toBe(64)
        ->and($a)->toBe($b)
        ->and($a)->not->toBe($c);
});
