<div class="admin-meetings">
    <a
        href="{{ $instantMeetUrl ?? \App\Support\InstantMeetings::meet() }}"
        target="_blank"
        rel="noopener"
        class="admin-icon-btn admin-icon-btn--meet"
        title="Novo Meet"
        aria-label="Novo Meet"
    >
        <x-ui.icon name="meet" class="h-5 w-5" />
    </a>
    <a
        href="{{ $instantTeamsUrl ?? \App\Support\InstantMeetings::teams() }}"
        target="_blank"
        rel="noopener"
        class="admin-icon-btn admin-icon-btn--teams"
        title="Novo Teams"
        aria-label="Novo Teams"
    >
        <x-ui.icon name="teams" class="h-5 w-5" />
    </a>
    <a
        href="{{ $instantJitsiUrl ?? \App\Support\InstantMeetings::jitsi() }}"
        target="_blank"
        rel="noopener"
        data-jitsi-new
        data-jitsi-host="{{ $instantJitsiHost ?? \App\Support\InstantMeetings::jitsiHost() }}"
        class="admin-icon-btn admin-icon-btn--jitsi"
        title="Novo Jitsi"
        aria-label="Novo Jitsi"
    >
        <x-ui.icon name="jitsi" class="h-5 w-5" />
    </a>
</div>
