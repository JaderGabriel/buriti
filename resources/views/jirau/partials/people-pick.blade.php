@php
    $field = $field ?? 'user_ids';
    $picked = collect($picked ?? []);
    $exclude = collect($exclude ?? [])->map(fn ($id) => (int) $id)->filter();
    $people = collect($people ?? [])->reject(fn ($person) => $exclude->contains((int) $person->id));
    $pickedIds = $picked->map(fn ($item) => (int) (is_object($item) ? $item->id : $item));
@endphp
<input type="hidden" name="{{ $field }}_sync" value="1">
<div class="jirau-watch__people">
    @forelse($people as $person)
        <label>
            <input type="checkbox" name="{{ $field }}[]" value="{{ $person->id }}" @checked($pickedIds->contains((int) $person->id))>
            <span class="jirau-avatar">{{ $person->initials() }}</span>
            <span>
                <strong>{{ $person->name }}</strong>
                <small>{{ $person->jirauTeamLabel() }}</small>
            </span>
        </label>
    @empty
        <p class="jirau-hint">Ninguém da equipe disponível.</p>
    @endforelse
</div>
