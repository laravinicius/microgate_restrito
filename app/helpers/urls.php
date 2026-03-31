<?php
declare(strict_types=1);

function normalize_path_segment(string $path): string
{
    $trimmed = trim($path);
    if ($trimmed === '') {
        return '/';
    }

    return '/' . ltrim($trimmed, '/');
}

function route_url(string $path = ''): string
{
    return normalize_path_segment($path);
}

function asset_url(string $path): string
{
    return normalize_path_segment($path);
}

function action_url(string $path): string
{
    return normalize_path_segment('app/actions/' . ltrim($path, '/'));
}

function debug_url(string $path): string
{
    return normalize_path_segment('app/support/debug/' . ltrim($path, '/'));
}
