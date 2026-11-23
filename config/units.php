<?php

/*
 * Allowed sale units (spec §4.11). Each product has exactly ONE base
 * sale/stock unit. No implicit conversion between units is ever performed.
 * Quantities are decimal strings with up to 3 fractional digits.
 */
return [
    'piece' => [
        'label_ar' => 'قطعة',
        'per_ar' => 'للقطعة',
        'default_min' => '1',
        'default_step' => '1',
        'allow_fractional' => false,
    ],
    'meter' => [
        'label_ar' => 'متر',
        'per_ar' => 'للمتر',
        'default_min' => '1',
        'default_step' => '0.5',
        'allow_fractional' => true,
    ],
    'roll' => [
        'label_ar' => 'بكرة',
        'per_ar' => 'للبكرة',
        'default_min' => '1',
        'default_step' => '1',
        'allow_fractional' => false,
    ],
    'pack' => [
        'label_ar' => 'عبوة',
        'per_ar' => 'للعبوة',
        'default_min' => '1',
        'default_step' => '1',
        'allow_fractional' => false,
    ],
    'set' => [
        'label_ar' => 'طقم',
        'per_ar' => 'للطقم',
        'default_min' => '1',
        'default_step' => '1',
        'allow_fractional' => false,
    ],
    'box' => [
        'label_ar' => 'علبة',
        'per_ar' => 'للعلبة',
        'default_min' => '1',
        'default_step' => '1',
        'allow_fractional' => false,
    ],
    'pair' => [
        'label_ar' => 'زوج',
        'per_ar' => 'للزوج',
        'default_min' => '1',
        'default_step' => '1',
        'allow_fractional' => false,
    ],
    'kilogram' => [
        'label_ar' => 'كجم',
        'per_ar' => 'للكيلو',
        'default_min' => '1',
        'default_step' => '0.1',
        'allow_fractional' => true,
    ],
];
