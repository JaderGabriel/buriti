@php
    $block = $block ?? ['name' => 'BURI-TI', 'role' => 'Consultoria e software', 'company' => 'BURI-TI', 'logo' => asset('images/logo-buriti.png'), 'site_url' => $siteUrl ?? '', 'site_host' => $siteHost ?? 'buriti.dev.br', 'channels' => []];
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
    <tr>
        <td valign="top" style="padding:0 16px 0 0;">
            <img src="{{ $block['logo'] }}" width="72" height="68" alt="BURI-TI" style="display:block;border:0;width:72px;height:68px;object-fit:contain;">
        </td>
        <td valign="top" style="padding:0;font-family:Arial,Helvetica,sans-serif;">
            <p style="margin:0;font-size:15px;line-height:1.3;font-weight:700;color:#0f2744;">{{ $block['name'] }}</p>
            <p style="margin:3px 0 0;font-size:12px;line-height:1.4;color:#5b6b7c;">{{ $block['role'] }}</p>
            <p style="margin:2px 0 0;font-size:12px;line-height:1.4;font-weight:600;color:#1a5f9e;letter-spacing:.04em;">{{ $block['company'] }}</p>
            @if(($block['channels'] ?? []) !== [])
                <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-top:10px;">
                    <tr>
                        @foreach($block['channels'] as $i => $channel)
                            @if($i > 0)
                                <td style="width:8px;font-size:0;line-height:0;">&nbsp;</td>
                            @endif
                            <td style="padding:0;">
                                <a href="{{ $channel['href'] }}" target="_blank" rel="noopener" title="{{ $channel['label'] }}" style="display:block;text-decoration:none;border:0;">
                                    <img src="{{ $channel['icon'] }}" width="28" height="28" alt="{{ $channel['label'] }}" style="display:block;border:0;width:28px;height:28px;">
                                </a>
                            </td>
                        @endforeach
                    </tr>
                </table>
            @endif
            <p style="margin:10px 0 0;font-size:12px;line-height:1.4;">
                <a href="{{ $block['site_url'] }}" style="color:#1a5f9e;text-decoration:none;">{{ $block['site_host'] }}</a>
            </p>
        </td>
    </tr>
</table>
