<?php

namespace Core\Facades;

use Core\Support\Facade;

/**
 * Cung cấp một giao diện tĩnh cho service dịch thuật.
 *
 * @method static string get(string $key, array $replace = [], string $locale = null)
 * @method static string choice(string $key, int|array|\Countable $number, array $replace = [], string $locale = null)
 * @method static void setLocale(string $locale)
 * @method static string getLocale()
 *
 * @see \Illuminate\Translation\Translator
 */
class Translate extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'translator';
    }
}
