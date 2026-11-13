# Changelog

All notable changes to `pushery/ai-content-marking-for-laravel` are documented here. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

A release carries only the sections that have an entry, in this order: `### ⚠️ Breaking`, `### ✨ Added`, `### 🔧 Changed`, `### ⚡ Performance`, `### 🐛 Fixed`, `### 🔒 Security`, `### 🧹 Deprecated`, `### 🗑️ Removed` and `### 📚 Documentation`.

## [0.4.0] - 2026-11-13

### ✨ Added

- **A label can explain its acronym.** `explain` on `<x-ai-content-marking::label>` makes the acronym a button that opens the sentence of the notice in a popover, for a reader who does not know what "AI" stands for. A click, a touch and the keyboard open it without JavaScript, and a browser without popovers shows the sentence beside the acronym. The popover is named after the label's `id` where it has one. An explained label is a button and no image, so it carries no `role="img"`; the button reads the sentence as its name. `form` on the label takes the sentence of a form, as it does on the notice. The notice and the image pages show the acronym alone beside a heading and the explanation, with their CSS.

### 🔧 Changed

- **The English notice writes "generative AI" in lower case.** "Created using Generative AI" and "Edited using Generative AI" capitalized it, unlike every other language of the package and the sentences around a notice. They read "Created using generative AI" and "Edited using generative AI" now. An application that matches the English words, in a test or a stylesheet, updates them.
- **The gap report says what is unknown about a watermark once.** The finding read "no known watermark: whether the model watermarks text is not known" and its counterparts in every language. It reads "watermark unknown: the register does not say whether the model watermarks its text" now, in each language.
- **The gap report sets its token counts flush right.** `GapReporter::markdown()` marks the column `Tokens` as right-aligned, `---:`, so the digits of the counts line up from row to row wherever the report is rendered. The other columns stay as they were.

## [0.3.0] - 2026-11-03

### ✨ Added

- **An image a model made or changed gets a visible label.** `<x-ai-content-marking::label>` renders the label the European Commission's Code of Practice describes for a deep fake: the acronym "AI" as its main element and, beside it, whether the content was generated or modified, the sentence of the notice, or nothing, picked with `beside`. Assistive technology reads the sentence of the notice as its name, and the label renders for the same markings as the notice. Its words come in the seven languages of the notice, and the acronym is a translation line of its own for a locale whose law on the use of languages asks for another. The new page *Labeling images* shows it on a shop, a news page and a newsletter, and in its smallest form.

  **Upgrade:** nothing to do.

- **`ImageLabeler` draws the label into an image itself.** An overlay does not travel with a mail, a download or a file passed on. `ImageLabeler::stamp()` draws the acronym "AI" on a dark backing into the top right corner of the image at a path, a twentieth of its width high and never under 16 pixels, and writes the marking into the result again, since drawing encodes the image anew; `stamped()` does the same with the bytes of an image. The letters are drawn as strokes, so the package ships no font. An image whose source type the notice does not show gets the marking and no label.

  **Upgrade:** nothing to do.

- **`Notice::fill()` fills a sentence the application translated itself.** An application that keeps its texts in its own catalog, a JSON catalog where each key is a literal `__()` call, words the sentence of a notice there, and `fill($sentence, $marking)` gives it the values of the marking with the check `label()` makes: `:generator`, `:model` and `:operator`, also capitalized, and a sentence that names a value the marking does not carry is refused. `Notice::shows($marking)` says whether a notice is due for the marking's source type, where `label()` answers `null`. The package's translation files take no part in either.

### 🔒 Security

- **A Word document can no longer exhaust the memory of the request that reads or writes its marking.** `DocumentMarker` reads each part it needs up to a megabyte, whatever size the archive claims for it, and refuses a larger one with an `InvalidArgumentException`, so an archive built to unpack to gigabytes stops there. A part that declares a document type, which no part of an Office document does, is refused the same way before anything parses it.

  **Upgrade:** nothing to do.

- **A crafted WebP can no longer make the reader walk it eight bytes at a time.** Past the first megabyte, `ImageMarker` follows at most 65,536 chunks to the XMP and C2PA a WebP lists after its image data, and keeps about a megabyte of what they hold.

  **Upgrade:** nothing to do.

## [0.2.0] - 2026-11-02

### ✨ Added

- **A marking names the model and the operator as fields of their own, and its array form names the version of its shape.** `Marking::generated()`, `Marking::edited()` and the matching methods of `MarkedText` take an optional `model` and `operator` beside the generator, and `toArray()` and the JSON form write them, with `version` set to 1. A marking stored before reads back with both as `null`. The mail header field and the XMP of an image do not carry the two new fields.

  **Upgrade:** a client that compares the JSON of a marking key by key sees three more keys. Nothing else changes.

- **A response that carries generated text can name it in its header section.** `HttpMarking::headers()` gives the field a mail carries, `Digital-Source-Type`, with the same value, for `response()->json()` or `withHeaders()`. A client that reads the headers before the body, such as one that streams the answer, finds the marking there. The package still writes no `AI-Disclosure` header, a proposal that expired without becoming a standard.

  **Upgrade:** nothing to do.

- **A Word document can carry its marking.** `DocumentMarker::write()` puts a marking into the custom properties of an Office Open XML document, each value a property of its own, and `read()` reads it back. A document without custom properties gets the part with its content type and relationship, every property of the document's own stays, and a marking written before is replaced rather than repeated. It needs the `zip` and `dom` extensions, which `composer.json` suggests.

  **Upgrade:** nothing to do.

- **A notice can say something else in another place.** `Notice::label()` and the component take a `form`, and its sentence stands in the published translations under `notice.forms.<form>.<term>`. A sentence can name the generator, the model and the operator of the marking as `:generator`, `:model` and `:operator`, and one that names a value the marking does not carry is refused rather than shown with a gap. Without a form nothing changes.

  **Upgrade:** nothing to do.

### 📚 Documentation

- **Every kind of marking has a picture of what it looks like.** The documentation shows the notice on a product page and in each element it can render in, the same paragraph with and without its microdata, a marked image as exiftool reads it, the header section of a marked mail and of a marked response, the custom properties of a marked Word document and a rendered gap report. The notice page also gives the stylesheet of those samples, since the package ships none.

## [0.1.0] - 2026-10-30

Initial release. The package marks content made with generative AI in a Laravel application with the IPTC Digital Source Type: text as schema.org microdata, images as XMP that every media library conversion keeps, and mail as a header field a DKIM signature can cover. A Blade component shows readers a notice in seven languages, every marking can be read back, a test helper checks an application's image pipeline, and a report names each output that lacks the two layers of marking the European Commission's Code of Practice asks of text.

[Unreleased]: https://github.com/pushery/ai-content-marking-for-laravel/compare/v0.4.0...HEAD
[0.4.0]: https://github.com/pushery/ai-content-marking-for-laravel/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/pushery/ai-content-marking-for-laravel/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/pushery/ai-content-marking-for-laravel/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/pushery/ai-content-marking-for-laravel/releases/tag/v0.1.0
