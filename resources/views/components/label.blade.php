@props(['marking', 'beside' => 'kind', 'explain' => false, 'form' => null])
@php
    // The label the Code of Practice on AI-generated content describes for an image or a video: the
    // acronym as its main element and, beside it, whether the content was generated or modified, the
    // sentence of the notice, or nothing. Assistive technology reads the sentence of the notice as its
    // name. Nothing renders where the configuration shows no notice for the marking's source type, and
    // nothing for a null marking, the state of an image nobody generated. The service provider's view
    // composer hands in $aiContentMarkingNotice.
    //
    // `explain` makes the acronym a button that opens the sentence in a popover, for a reader who does
    // not know what the acronym stands for. A mouse, a keyboard and a touch open it without JavaScript,
    // and a browser without popovers shows the sentence beside the acronym. The visible acronym stays
    // the label; the popover adds to it. The popover is named after the label's `id`, else after its
    // `wire:key`, and a random name is left for a label with neither, which keeps two labels apart. A
    // name that changes from one render to the next closes an open explanation wherever the page is
    // morphed: Livewire keys an element by its id and swaps one whose id changed for a closed copy.
    // The button's name begins with the acronym it shows and goes on with the sentence, because the
    // name of a control contains its visible text (WCAG 2.5.3): a reader who speaks "AI" to voice
    // control reaches it in every language, also where the sentence says "KI" or "IA".
    // `form` takes the sentence of a form, `notice.forms.<form>.<term>`, as the notice does.
    if ($marking !== null && ! $marking instanceof \Pushery\AiContentMarking\Marking) {
        throw new \InvalidArgumentException(sprintf(
            'The `marking` of <x-ai-content-marking::label> takes a %s or null, not %s.',
            \Pushery\AiContentMarking\Marking::class,
            get_debug_type($marking),
        ));
    }

    $name = $marking === null ? null : $aiContentMarkingNotice->label($marking, null, is_string($form) ? $form : null);
    $words = $name === null ? null : $aiContentMarkingNotice->beside($marking, is_string($beside) ? $beside : '');
    $explained = $name !== null && $explain !== false && $explain !== null;
    $acronym = $name === null ? null : $aiContentMarkingNotice->acronym();
    $button = ! $explained ? null : ($attributes->get('aria-label') ?? $acronym.': '.$name);
    $labelId = $attributes->get('id');
    $wireKey = $attributes->get('wire:key');
    $popover = match (true) {
        ! $explained => null,
        is_string($labelId) && $labelId !== '' => $labelId.'-explanation',
        (is_string($wireKey) || is_int($wireKey)) && (string) $wireKey !== '' => 'ai-content-marking-explanation-'.hash('xxh32', (string) $wireKey),
        default => 'ai-content-marking-explanation-'.bin2hex(random_bytes(4)),
    };
@endphp
@if ($name !== null && ! $explained)
<span {{ $attributes->merge(['role' => 'img', 'aria-label' => $name])->class(['ai-content-marking-label']) }}><span class="ai-content-marking-label-acronym">{{ $acronym }}</span>@if ($words !== null) <span class="ai-content-marking-label-{{ $beside }}">{{ $words }}</span>@endif</span>@elseif ($explained)
<span {{ $attributes->except('aria-label')->class(['ai-content-marking-label', 'ai-content-marking-label-explained']) }}><button type="button" class="ai-content-marking-label-acronym" popovertarget="{{ $popover }}" aria-label="{{ $button }}">{{ $acronym }}</button>@if ($words !== null) <span class="ai-content-marking-label-{{ $beside }}" aria-hidden="true">{{ $words }}</span>@endif <span popover id="{{ $popover }}" class="ai-content-marking-label-explanation">{{ $name }}</span></span>@endif
