@props(['marking', 'beside' => 'kind'])
@php
    // The label the Code of Practice on AI-generated content describes for an image or a video: the
    // acronym as its main element and, beside it, whether the content was generated or modified, the
    // sentence of the notice, or nothing. Assistive technology reads the sentence of the notice as its
    // name. Nothing renders where the configuration shows no notice for the marking's source type.
    // The service provider's view composer hands in $aiContentMarkingNotice.
    $name = $aiContentMarkingNotice->label($marking);
    $words = $name === null ? null : $aiContentMarkingNotice->beside($marking, is_string($beside) ? $beside : '');
@endphp
@if ($name !== null)
<span {{ $attributes->merge(['role' => 'img', 'aria-label' => $name])->class(['ai-content-marking-label']) }}><span class="ai-content-marking-label-acronym">{{ $aiContentMarkingNotice->acronym() }}</span>@if ($words !== null) <span class="ai-content-marking-label-{{ $beside }}">{{ $words }}</span>@endif</span>@endif
