---
name: ai-content-marking-for-laravel
description: >
  Install, configure, and apply the AI Content Marking for Laravel package in a Laravel
  application.
license: MIT
metadata:
  author: pushery
---

# AI Content Marking for Laravel

Use this skill when a Laravel application installs or integrates the
`pushery/ai-content-marking-for-laravel` package. The full reference is at
<https://docs.pushery.com/ai-content-marking-for-laravel/>.

## Primary Goal

Mark text a language model wrote where the application delivers it: a machine-readable marking
in the IPTC Digital Source Type vocabulary, and a separate notice for readers.

## Workflow

### 1. Install

```bash
composer require pushery/ai-content-marking-for-laravel
```

The service provider is registered automatically through package discovery.

### 2. Configure

Publish everything at once, or one group at a time:

```bash
php artisan vendor:publish --tag="ai-content-marking"
php artisan vendor:publish --tag="ai-content-marking-config"
php artisan vendor:publish --tag="ai-content-marking-views"
php artisan vendor:publish --tag="ai-content-marking-lang"
```

Every option in `config/ai-content-marking.php` is documented inline. The package ships no
migrations and no routes.

### 3. Apply the package

1. Wrap model output in a `Pushery\AiContentMarking\MarkedText` where it comes back:
   `MarkedText::generated($text)` for text the model wrote, `MarkedText::edited($text)` for text
   a person wrote and the model rewrote.
2. In a view, render `{{ $text->html() }}` where the text is shown as content. It writes the
   escaped text inside schema.org microdata that names the term.
3. Place `<x-ai-content-marking::notice :marking="$text->marking" />` where readers should see
   the notice.
4. In an API resource, return the `MarkedText` where the client should receive the marking: it
   serializes to the text beside the marking's values.
5. To keep the marking with a record, cast a JSON column to `Pushery\AiContentMarking\Marking`.
6. For an image, write the marking with `Pushery\AiContentMarking\Images\ImageMarker`; the
   conversions of `spatie/laravel-medialibrary` keep it.
7. For a mail, return `new Headers(text: MailMarking::headers($marking))` from the mailable's
   `headers()`, or add the field in `withSymfonyMessage()`. `MailMarkingReader` reads it back and
   names the DKIM signatures that list it.
8. To record what is known about each model, list it under `ai-content-marking.models` with
   `watermarks_text` (`yes`, `no` or `unknown`), and describe the delivered outputs as
   `Pushery\AiContentMarking\Gaps\Output` for `GapReporter::assess()`.

## Examples

```php
use Pushery\AiContentMarking\MarkedText;

$description = MarkedText::generated($response->text, generator: 'Amazon Bedrock', at: now());
```

```blade
<article>
    <x-ai-content-marking::notice :marking="$description->marking" />
    <p>{{ $description->html() }}</p>
</article>
```

In a test, prove the page carries the marking, and that a page without generated text carries
none:

```php
use Pushery\AiContentMarking\MarkingReader;

expect(app(MarkingReader::class)->marksGenerativeAi($this->get('/products/1')->getContent()))->toBeTrue()
    ->and(app(MarkingReader::class)->read($this->get('/about')->getContent()))->toBe([]);
```

## Anti-Patterns

- Calling `html()` inside an attribute, a `<title>` or a mail subject. Use the plain string there:
  `{{ $text }}` escapes it, and markup cannot go into those places.
- Passing user input to `Marking::wrap()`. It does not escape the HTML it is given; a plain text
  belongs in `MarkedText::html()`.
- Treating the marking as a legal statement. The package writes a marking and a notice, and
  whether that meets an obligation of the application is not decided by it.
- Adding a term to `ai-content-marking.notice.source_types` without adding its words to the
  published translations. The component then throws instead of rendering a key.
- Reading `signingDomains()` as a verified signature. It names the signatures that list the
  field and verifies none of them.
- Filling the model register from memory. An entry is a statement of the model's provider, with
  its source and the day it was checked.
