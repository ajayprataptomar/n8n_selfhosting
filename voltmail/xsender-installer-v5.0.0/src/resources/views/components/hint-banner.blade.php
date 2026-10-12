@if($hint)
<div class="alert {{ $alertClass }} d-flex align-items-start gap-3 mb-4" role="alert">
    <i class="{{ $icon }} fs-22 mt-1 flex-shrink-0"></i>
    <div class="flex-grow-1">
        @if(!empty($hint['title']))
            <strong>{{ translate($hint['title']) }}</strong>
        @endif
        <div class="mt-1">{!! $hint['default_text'] !!}</div>
    </div>
</div>
@endif
