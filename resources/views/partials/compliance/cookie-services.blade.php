{{--
    One card per enabled cookie or service. The block is inserted into the cookie policy at the
    {{ cookie_services }} token (App\Services\Compliance\ComplianceContentBlocks). A necessary record
    documents a first-party technical cookie; an optional record is only ever executed after the
    matching consent. All values are escaped by Blade.
--}}
@foreach ($scripts as $script)
    <div class="legal-service">
        <h3>{{ $script->name }}</h3>

        @if (filled($script->description))
            <p>{{ $script->description }}</p>
        @endif

        <ul>
            <li><strong>Kategorie:</strong> {{ $script->category?->label() ?? '—' }}</li>

            <li>
                <strong>Souhlas:</strong>
                @if ($script->needsConsent())
                    ano – ukládá se nebo spouští jen po vašem souhlasu
                @else
                    ne – technické zajištění chodu webu
                @endif
            </li>

            @if (filled($script->provider_name))
                <li>
                    <strong>Poskytovatel:</strong>
                    @if (filled($script->provider_privacy_url))
                        <a href="{{ $script->provider_privacy_url }}" target="_blank" rel="noopener noreferrer">{{ $script->provider_name }}</a>
                    @else
                        {{ $script->provider_name }}
                    @endif
                </li>
            @endif
        </ul>
    </div>
@endforeach
