@props(['marking', 'beside' => 'kind', 'explain' => false, 'form' => null])
@php
    // The label the Code of Practice on AI-generated content describes for an image or a video: the
    // acronym as its main element and, beside it, whether the content was generated or modified, the
    // sentence of the notice, or nothing. Assistive technology reads the sentence of the notice as its
    // name. Nothing renders where the configuration shows no notice for the marking's source type.
    // The service provider's view composer hands in $aiContentMarkingNotice.
    //
    // `explain` makes the acronym a button that opens the sentence in a popover, for a reader who does
    // not know what the acronym stands for. A mouse, a keyboard and a touch open it without JavaScript,
    // and a browser without popovers shows the sentence beside the acronym. The visible acronym stays
    // the label; the popover adds to it. The popover is named after the label's `id` where it has one.
    // `form` takes the sentence of a form, `notice.forms.<form>.<term>`, as the notice does.
    $name = $aiContentMarkingNotice->label($marking, null, is_string($form) ? $form : null);
    $words = $name === null ? null : $aiContentMarkingNotice->beside($marking, is_string($beside) ? $beside : '');
    $explained = $name !== null && $explain !== false && $explain !== null;
    $popover = ! $explained ? null : (is_string($attributes->get('id'))
        ? $attributes->get('id').'-explanation'
        : 'ai-content-marking-explanation-'.bin2hex(random_bytes(4)));
@endphp
@if ($name !== null && ! $explained)
<span {{ $attributes->merge(['role' => 'img', 'aria-label' => $name])->class(['ai-content-marking-label']) }}><span class="ai-content-marking-label-acronym">{{ $aiContentMarkingNotice->acronym() }}</span>@if ($words !== null) <span class="ai-content-marking-label-{{ $beside }}">{{ $words }}</span>@endif</span>@elseif ($explained)
<span {{ $attributes->except('aria-label')->class(['ai-content-marking-label', 'ai-content-marking-label-explained']) }}><button type="button" class="ai-content-marking-label-acronym" popovertarget="{{ $popover }}" aria-label="{{ $attributes->get('aria-label') ?? $name }}">{{ $aiContentMarkingNotice->acronym() }}</button>@if ($words !== null) <span class="ai-content-marking-label-{{ $beside }}" aria-hidden="true">{{ $words }}</span>@endif <span popover id="{{ $popover }}" class="ai-content-marking-label-explanation">{{ $name }}</span></span>@endif
