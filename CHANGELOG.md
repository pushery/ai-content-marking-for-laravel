# Changelog

All notable changes to `pushery/ai-content-marking-for-laravel` are documented here. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

A release carries only the sections that have an entry, in this order: `### ⚠️ Breaking`, `### ✨ Added`, `### 🔧 Changed`, `### ⚡ Performance`, `### 🐛 Fixed`, `### 🔒 Security`, `### 🧹 Deprecated`, `### 🗑️ Removed` and `### 📚 Documentation`.

## [0.1.0] - 2026-10-30

Initial release. The package marks content made with generative AI in a Laravel application with the IPTC Digital Source Type: text as schema.org microdata, images as XMP that every media library conversion keeps, and mail as a header field a DKIM signature can cover. A Blade component shows readers a notice in seven languages, every marking can be read back, a test helper checks an application's image pipeline, and a report names each output that lacks the two layers of marking the European Commission's Code of Practice asks of text.

[Unreleased]: https://github.com/pushery/ai-content-marking-for-laravel/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/pushery/ai-content-marking-for-laravel/releases/tag/v0.1.0
