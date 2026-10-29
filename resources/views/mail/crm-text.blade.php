{{ $bodyText ?? $body }}

@if(filled($signature))
--
{{ $signature }}
{{ $siteHost }}
@endif

{{ $legalNotice }}
{{ $privacyUrl }}
