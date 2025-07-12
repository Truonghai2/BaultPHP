<?php

namespace Http\Middleware;

use Http\Request;

class TrimStrings
{
    public function handle(Request $request, callable $next)
    {
        $request->merge([
            'get' => self::trimArray($request->get()),
            'post' => self::trimArray($request->post()),
        ]);

        return $next($request);
    }

    protected static function trimArray(array $data): array
    {
        return array_map(function ($value) {
            return is_string($value) ? trim($value) : $value;
        }, $data);
    }
}
