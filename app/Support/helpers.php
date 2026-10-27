<?php

use App\Support\RuntimeVersions;

if (! function_exists('product_name')) {
    function product_name(): string
    {
        return RuntimeVersions::name();
    }
}

if (! function_exists('product_version')) {
    function product_version(): string
    {
        return RuntimeVersions::version();
    }
}

if (! function_exists('product_codename')) {
    function product_codename(): string
    {
        return RuntimeVersions::codename();
    }
}

if (! function_exists('product_tagline')) {
    function product_tagline(): string
    {
        return RuntimeVersions::tagline();
    }
}

if (! function_exists('product_short')) {
    function product_short(): string
    {
        return RuntimeVersions::short();
    }
}

if (! function_exists('product_release')) {
    function product_release(): string
    {
        return RuntimeVersions::release();
    }
}
