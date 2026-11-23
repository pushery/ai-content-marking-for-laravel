# Changelog

All notable changes to `pushery/ai-content-marking-for-laravel` are documented here. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

A release carries only the sections that have an entry, in this order: `### ⚠️ Breaking`, `### ✨ Added`, `### 🔧 Changed`, `### ⚡ Performance`, `### 🐛 Fixed`, `### 🔒 Security`, `### 🧹 Deprecated`, `### 🗑️ Removed` and `### 📚 Documentation`.

## [0.5.1] - 2026-11-23

### 📚 Documentation

- **The first example of the Boost skill sets the text it marks.** It passed `$response->text` from a variable nothing set, so code copied from it failed before it marked anything. It now starts from `$modelOutput`, as the README does.
- **The package description fits a search result.** The description in `composer.json`, which Packagist shows as the description of the package page, ran past the roughly 160 characters a search result shows, so the result cut it off mid-sentence. It now says what the package does within that length.
- **Two more pictures of text carry their text below them.** The output of exiftool on the images page and the custom properties on the documents page stood only in a picture, which a narrow screen shrinks below reading size and which text zoom, search and copying never reach. Each now follows as a code block.

## [0.5.0] - 2026-11-20

### 🔧 Changed

- **`Support\EnvSwitch` is internal.** 0.4.0 shipped it as public API, for a configuration file that reads an on-off switch through it. The configuration of this package reads no such switch, so the class leaves the public API: an application that calls it keeps working until a later release changes or removes it.

### ⚡ Performance

- **The marking of an original is read once for all its conversions, from the copy the media library makes of it.** `KeepMarkingOnConversions` read up to a megabyte of the original from its disk for every conversion of every image the application stores, avatars and attachments included, so on a disk in the cloud an image with three conversions cost three requests for its original on top of the one the media library makes, and `media-library:regenerate` repeated them for every image. The marking is read from the local copy the media library makes before the conversions now, once for each image, and the disk is asked for the original only for responsive images made without conversions in the same run and for the conversions of a PDF or a video.

### 🐛 Fixed

- **The notice and the label render nothing for a `null` marking.** A column cast to `Marking` holds `null` for a text or an image nobody generated, and `<x-ai-content-marking::notice>` and `<x-ai-content-marking::label>` stopped the page with a `TypeError` when they were given one. They render nothing for it now, as for a source type without a notice, so one template serves a list in which only some items were generated. A value that is neither a marking nor `null` throws an `InvalidArgumentException` that names the prop and the component.
- **The button of an explained label begins its name with the acronym it shows.** Its name was the sentence of the notice, which says "KI" or "IA" in five of the seven languages while the button shows "AI", so voice control could not reach the button by its visible text, as WCAG 2.5.3 asks. The name is the acronym, a colon and the sentence now, such as "AI: Mit generativer KI bearbeitet", in every language. An application that matches the button's `aria-label` in a test updates it.
- **An explained label with a `wire:key` keeps its explanation open across a Livewire update.** Without an `id`, the popover got a new random name on every render, and Livewire, which keys an element by its id when it morphs a component, replaced an open explanation with a closed copy on every update. The popover is named after the label's `id`, else after its `wire:key`, and only a label with neither gets a random name. The documentation asks for one of the two wherever an explained label sits in a Livewire component.
- **A conversion the marking cannot go into no longer stops the media library's other conversions.** `KeepMarkingOnConversions` let the marker's refusal escape from the loop in which the media library makes the conversions of an image, after the unmarked conversion was stored and recorded as generated. The image kept an unmarked copy, the conversions after it were never made, and a conversion made during the upload failed the request. A conversion that cannot hold the marking, such as a BMP, is deleted and recorded as not generated now, the refusal goes to the application's exception handler, and the media library goes on with the conversions after it.
- **The responsive images the media library makes of a marked image carry its marking.** The package marked each conversion and left the images of a srcset as the image driver wrote them, and gd writes no XMP, so a browser that picked one of them loaded an unmarked copy. Every responsive image of the original and of each conversion is marked now, those of a conversion together with it and those of the original once the media library reports them made. A conversion that cannot hold the marking is deleted with its responsive images.
- **A test that only runs `MarkingPipeline` is no longer reported as risky.** `assertKeepsMarking()` reported a failure with an exception and counted nothing when every stage kept the marking, so PHPUnit reported the call alone, as the documentation shows it, as a test that tests nothing, and a suite with `failOnRisky` failed it. Each check that passes counts as an assertion of the test now, wherever PHPUnit is loaded, and a failure is still the `UnexpectedValueException` that names the stage.
- **A generator with white space around it, or an empty one, no longer fails the marking of an image or a document.** The readers trim the generator, the model and the operator and read an empty one as none, while `ImageMarker` and `DocumentMarker` compared what they read back with the value as given, so `Marking::generated('Example Model ')` or `Marking::generated('')` failed with a message about the image format, and a document was already written when the comparison failed. A `Marking` keeps the three values without the white space around them now, and one that is empty counts as none. A notice sentence that names `:generator` is refused for a marking whose generator is empty, as for one without a generator.
- **A model that only read its marking from a `jsonb` or `json` column no longer writes its row again.** PostgreSQL and MySQL hand the JSON of a marking back in a spelling of their own, its keys in another order and its spacing changed, and the cast left Eloquent to compare that text with the text it writes, so `save()` sent an `UPDATE` with a new `updated_at` for a model whose marking had only been read, and `updateOrCreate()` with the marking a row already held wrote the row every time. The cast compares two values of the column by the marking they hold now.
- **A WebP of more than a megabyte whose XMP or C2PA chunk stands behind its image data passes its marking on to its conversions.** `KeepMarkingOnConversions` gave the reader the first megabyte of an original as a string, and the walk to the chunks a WebP lists after its image data begins past that megabyte, so the conversions of such an original, in the order its container recommends, stayed unmarked without a word. The marking is read from the copy the media library makes of the original now, the way `ImageMarker::read()` reads a file, and every conversion carries it.
- **A generator that ends in a line feed no longer breaks the header field of a mail or a response.** `MailMarking` and `HttpMarking` wrote such a generator bare or in quotes with its line break, because their check for those two forms let a line feed at the end through. PHP refused the field of a response once the moment followed the generator, and in a mail neither the generator nor the moment read back. Such a generator is written as an extended parameter now, like any other line break. A new marking trims its generator, so this concerns a marking an earlier version serialized, in a queued job or a cache.
- **A conversion that cannot hold the marking is no longer reported as deleted while it stays.** The conversion and its responsive images were deleted without reading the answer, and Laravel's own disks answer a refused delete with `false` instead of throwing, so the report said they were gone while they stayed unmarked on the disk. The report names the files that stayed now, so they can be removed by hand.
- **`MarkingPipeline::through()` runs a list of stages.** A list numbers its stages, and a stage named only by digits is an integer key as well. The name went to a method that takes a string, so such a pipeline ended with a type error at its first stage instead of running; a failure now names a stage of a list by its number.

### 🔒 Security

- **An uploaded image can no longer keep the marking off its conversions with an overlong generator.** The reader took an `xmp:CreatorTool` of any length from an original, and a JPEG conversion refused a marking too long for its one APP1 segment, so a generator of about 65 KB in an upload decided what became of every conversion made from it. The reader leaves out a generator too long for a JPEG to carry now, and the conversions keep the source type and the moment. `ImageMarker` refuses such a marking in every format, as it did for a JPEG, so a JPEG made from any image it marked can carry the marking.
- **A part of a Word document written in UTF-16 can no longer bring a document type declaration past `DocumentMarker`.** The marker refused a part that declares a document type by searching its bytes, and a part in UTF-16, which Office Open XML allows beside UTF-8, spells the declaration in two bytes a character, so the search missed it and the parser read it. Such a part is refused with the same `InvalidArgumentException` now, once the parser has read the declaration, which it reads without replacing an entity.
- **An uploaded image no longer chooses the decoder that reads it, or how many pixels it decodes.** `ImageMarker` handed every image other than a JPEG, a PNG or a WebP to Imagick, and `ImageLabeler` every image it labels, and ImageMagick picked the decoder from the bytes, SVG and MVG among them where they are built in, and set aside the canvas the header declared before it read a pixel. Imagick now reads a JPEG, a PNG, a WebP or a TIFF only, in the format the signature of the bytes names, and other bytes are refused with an `InvalidArgumentException` that names the four formats. An image whose frames declare more pixels than `ai-content-marking.images.max_pixels`, 100 megapixels by default and set with `AI_CONTENT_MARKING_IMAGE_MAX_PIXELS`, is refused the same way before a pixel is decoded. A GIF or a BMP, which could not hold the marking before either, is refused with that `InvalidArgumentException` instead of an `UnexpectedValueException`.

  **Upgrade:** an image in another format goes through a conversion to one of the four before it is marked or labeled.

### 📚 Documentation

- **The pictures of a response, a mail header and a gap report carry their text below them.** The response of the HTTP example on *Marking text*, the header section of the mail on *Marking mail* and the document `markdown()` writes on *Model register and gap report* stood only in pictures, which a phone shrinks to a few pixels a letter and which a reader can neither search, copy nor enlarge as text. Each picture has its text in a code block right below it now, titled like the card in the picture, with the marked lines highlighted.
- **The README says what the engine suites prove, and no more.** It concluded from the runs on PostgreSQL and MySQL 8.4 that the package runs on Laravel Cloud without further setup, while a run against a database server in the test lane is no run on the serverless Postgres of Laravel Cloud. The requirements name the two engines as the ones Laravel Cloud runs, the fact the sentence stood on, and leave the conclusion out.
- **The phrases in other languages on the pages about the notice and about labeling images carry their language.** Each of the 24 phrases in German, Spanish, French, Italian, Dutch and Portuguese is marked with `<span lang>`, so a screen reader speaks it in its own language instead of with an English pronunciation (WCAG 2.2, SC 3.1.2).
- **An image with a drawn label carries the notice in its alternative text.** The label `ImageLabeler` draws is pixels and has no name, so a screen reader learns of it only from the alternative text of the image. The page *Labeling images* and the Boost skill say so beside `ImageLabeler`, and the page shows the alternative text built with `Notice::label()`, for a newsletter as for a download.
- **The sample CSS of an explained label opens the explanation in the middle of the window where the browser does not anchor it.** Its margin took away the margins that center a popover, so a browser that did not anchor the explanation to its button opened it in the top left corner of the window. The anchoring and the margin stand in an `@supports` block now, and each explanation is anchored to the button of its own label by name, with `anchor-scope`, instead of relying on the browser to anchor a popover to the button that opened it.
- **The start page of the documentation names the requirements before the installation.** PHP 8.4, Laravel 13, the `imagick` extension and `spatie/laravel-medialibrary` stood only in the README, so a reader who followed the documentation met a missing extension in Composer's error. The page lists them before `composer require`, as the README does, with the reason the package needs the extension.
- **The documentation no longer gives the English words of the notice as IPTC's names for the two terms.** IPTC names them "Created using Generative AI" and "Edited using Generative AI", and the notice writes "generative AI" in lower case since 0.4.0, yet the start page headed its words "IPTC label" and the notice page called them the labels IPTC gives the terms. The start page heads them "Notice in English" now, and the notice page quotes IPTC's names and says that the notice follows them in lower case.
- **The start page of the documentation says which party Article 50 asks to mark content and which to disclose it.** It said that an application writing product descriptions, summaries or replies with a language model needs a marker and a notice wherever it hands the text out, while the page *What it does not do* says that the Commission's guidelines let a provider rely on the marking of the model's provider and place product descriptions and advertising outside the duty to disclose, unless they make claims about health, safety or sustainability. The start page names the provider's marking under Article 50(2) and the deployer's disclosure under Article 50(4) now, leaves which of them applies to the reader's role and content, and links to that page for what the guidelines say about each.
- **The example of the gap report asks for the document the picture below it shows.** It called `markdown($report, 'de')`, which writes the report in German, above a picture of the English report, so a reader who ran it got a document other than the one shown. It calls `markdown($report, 'en')` now.
- **The card of the package on the documentation portal names the images as well as the text.** It described a package that marks the text an application delivers and nothing else, while the package also marks the images it stores, through every media library conversion. The card shows the description the package has on Packagist now.
- **The test example on the page about mail imports the mailable it sends.** It sent an `AnswerMail` without its `use` line, so the example did not run where a reader copied it as it stands.
- **The sample CSS of an explained label gives its button the height of a finger on a touch screen.** The button was only as tall as the acronym, which a finger easily misses; at a coarse pointer it now has the height a finger needs, and negative margins keep the line of the heading as it was.

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

[Unreleased]: https://github.com/pushery/ai-content-marking-for-laravel/compare/v0.5.1...HEAD
[0.5.1]: https://github.com/pushery/ai-content-marking-for-laravel/compare/v0.5.0...v0.5.1
[0.5.0]: https://github.com/pushery/ai-content-marking-for-laravel/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/pushery/ai-content-marking-for-laravel/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/pushery/ai-content-marking-for-laravel/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/pushery/ai-content-marking-for-laravel/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/pushery/ai-content-marking-for-laravel/releases/tag/v0.1.0
