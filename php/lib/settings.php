<?php

declare(strict_types=1);

function settings_file_path(): string
{
    $p = __DIR__ . '/../data/settings.json';
    $dir = dirname($p);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    return $p;
}

function load_settings(): array
{
    $file = settings_file_path();
    if (!file_exists($file)) return [];
    $raw = @file_get_contents($file);
    if ($raw === false) return [];
    $json = json_decode($raw, true);
    return is_array($json) ? $json : [];
}

function save_settings(array $s): bool
{
    $file = settings_file_path();
    $current = load_settings();
    $merged = array_merge($current, $s);
    $encoded = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return @file_put_contents($file, $encoded) !== false;
}
