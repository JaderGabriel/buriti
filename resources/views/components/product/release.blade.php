@props(['as' => 'release'])

{{ \App\Support\RuntimeVersions::format((string) $as) }}
