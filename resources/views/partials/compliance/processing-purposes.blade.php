{{--
    One card per active processing purpose. The block is inserted into the privacy policy at the
    {{ processing_purposes }} token (App\Services\Compliance\ComplianceContentBlocks), so the document
    never repeats the purposes in prose as well. All values are escaped by Blade.
--}}
@foreach ($purposes as $purpose)
    <div class="legal-purpose">
        <h3>{{ $purpose->name }}</h3>

        @if (filled($purpose->description))
            <p>{{ $purpose->description }}</p>
        @endif

        <ul>
            @if (filled($purpose->legalBasisLabel()))
                <li><strong>Právní základ:</strong> {{ $purpose->legalBasisLabel() }}</li>
            @endif

            @if (filled($purpose->personal_data_categories))
                <li><strong>Rozsah osobních údajů:</strong> {{ $purpose->personal_data_categories }}</li>
            @endif

            @if (filled($purpose->retention_period))
                <li><strong>Doba uchovávání:</strong> {{ $purpose->retention_period }}</li>
            @endif

            @if (filled($purpose->recipients))
                <li><strong>Příjemci:</strong> {{ $purpose->recipients }}</li>
            @endif

            @if (filled($purpose->third_country_transfer))
                <li><strong>Předání do třetích zemí:</strong> {{ $purpose->third_country_transfer }}</li>
            @endif
        </ul>
    </div>
@endforeach
