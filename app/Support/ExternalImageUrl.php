<?php

namespace App\Support;

class ExternalImageUrl
{
    public static function normalize(mixed $url): mixed
    {
        if (is_string($url) && parse_url($url, PHP_URL_HOST) === 'drive.google.com') {
            parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
            preg_match('~/file/d/([a-zA-Z0-9_-]+)~', parse_url($url, PHP_URL_PATH) ?? '', $matches);
            $id = $matches[1] ?? ($query['id'] ?? null);
            if (is_string($id) && preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
                return 'https://drive.google.com/thumbnail?id='.$id.'&sz=w1200';
            }
        }

        return $url;
    }
}
