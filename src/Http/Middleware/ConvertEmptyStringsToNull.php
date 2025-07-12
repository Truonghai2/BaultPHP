<?php

namespace Http\Middleware;

use Http\Request;

class ConvertEmptyStringsToNull
{
    public function handle(Request $request, callable $next)
    {
        $request->merge([
            'get' => self::convert($request->get()),
            'post' => self::convert($request->post()),
        ]);

        if ($request->isJson()) {
            $json = $request->json();
            $request->setJson(self::convert($json));
        }

        return $next($request);
    }

    protected static function convert(array $data): array
    {
        return array_map(function ($value) {
            if (is_array($value)) {
                return static::convert($value);
            }
            return is_string($value) && $value === '' ? null : $value;
        }, $data);
    }
}
