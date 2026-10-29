{{ $body }}

@if(filled($signature))
--
{{ $signature }}
{{ $fromAddress }}
{{ $siteHost }}
@endif

{{ $legalNotice }}
{{ $privacyUrl }}
