<?php

/**
 * Minimal stub of System\Models\SettingModel for standalone test execution.
 *
 * Provides the static Settings::get() interface used by ColorExtractor and
 * ColorClassifier. Always returns the $default value since no database is
 * available during standalone PHPUnit runs.
 */

namespace System\Models;

/**
 * Stub SettingModel — returns defaults for all get() calls (no database).
 */
class SettingModel
{
    /**
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    /**
     * @param string|array<string,mixed> $key
     * @param mixed                      $value
     * @return void
     */
    public static function set(string|array $key, mixed $value = null): void
    {
        // no-op in test stub
    }

    /**
     * @return static
     */
    public static function instance(): static
    {
        return new static();
    }

    /**
     * @return array<string, mixed>
     */
    public function getFieldConfig(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $data
     * @return void
     */
    public function fill(array $data): void
    {
        // no-op in test stub
    }

    /**
     * @return void
     */
    public function save(): void
    {
        // no-op in test stub
    }

    /**
     * @return void
     */
    public function initSettingsData(): void
    {
        // no-op in test stub
    }
}
