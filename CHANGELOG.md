# Changelog

All notable changes to `pushery/ai-content-marking-for-laravel` are documented here. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

A release carries only the sections that have an entry, in this order: `### ⚠️ Breaking`, `### ✨ Added`, `### 🔧 Changed`, `### ⚡ Performance`, `### 🐛 Fixed`, `### 🔒 Security`, `### 🧹 Deprecated`, `### 🗑️ Removed` and `### 📚 Documentation`.

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

[Unreleased]: https://github.com/pushery/ai-content-marking-for-laravel/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/pushery/ai-content-marking-for-laravel/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/pushery/ai-content-marking-for-laravel/releases/tag/v0.1.0
