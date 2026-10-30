@props(['marking', 'as' => null])
@php
    // The words a reader sees for a marking, in the element the page chooses. Nothing renders
    // where the configuration shows no notice for the marking's source type. The service
    // provider's view composer hands in $aiContentMarkingNotice.
    $label = $aiContentMarkingNotice->label($marking);
    $element = $label === null ? null : $aiContentMarkingNotice->element($as);
@endphp
@if ($element !== null)
<{{ $element }} {{ $attributes->class(['ai-content-marking-notice']) }}>{{ $label }}</{{ $element }}>@endif
