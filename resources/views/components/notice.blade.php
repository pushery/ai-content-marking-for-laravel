@props(['marking', 'as' => null, 'form' => null])
@php
    // The words a reader sees for a marking, in the element the page chooses. Nothing renders
    // where the configuration shows no notice for the marking's source type, and nothing for a
    // null marking, the state of a text nobody generated. The service provider's view composer
    // hands in $aiContentMarkingNotice.
    if ($marking !== null && ! $marking instanceof \Pushery\AiContentMarking\Marking) {
        throw new \InvalidArgumentException(sprintf(
            'The `marking` of <x-ai-content-marking::notice> takes a %s or null, not %s.',
            \Pushery\AiContentMarking\Marking::class,
            get_debug_type($marking),
        ));
    }

    $label = $marking === null ? null : $aiContentMarkingNotice->label($marking, null, $form);
    $element = $label === null ? null : $aiContentMarkingNotice->element($as);
@endphp
@if ($element !== null)
<{{ $element }} {{ $attributes->class(['ai-content-marking-notice']) }}>{{ $label }}</{{ $element }}>@endif
