<?php

use Tests\TestCase;

/*
 * Pest configuration. Note: pestphp/pest-plugin-laravel is NOT installed —
 * its v5.x requires PHP ^8.4 and v4.x requires laravel/framework ^11/^12,
 * which conflicts with this project's PHP 8.3 + Laravel 13 stack
 * (see PHASE1B_REPORT.md). Core Pest works without the plugin.
 */
pest()->extend(TestCase::class)->in('Feature', 'Unit');
