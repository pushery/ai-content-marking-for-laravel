<p align="center">
  <a href="https://github.com/pushery/ai-content-marking-for-laravel">
    <img src="art/header.png" alt="AI Content Marking for Laravel" width="100%">
  </a>
</p>

# AI Content Marking for Laravel

[![Latest Version](https://img.shields.io/packagist/v/pushery/ai-content-marking-for-laravel.svg)](https://packagist.org/packages/pushery/ai-content-marking-for-laravel)
[![PHP Version](https://img.shields.io/packagist/dependency-v/pushery/ai-content-marking-for-laravel/php.svg)](https://packagist.org/packages/pushery/ai-content-marking-for-laravel)
[![Laravel Versions](https://badge.laravel.cloud/badge/pushery/ai-content-marking-for-laravel?style=flat)](https://packagist.org/packages/pushery/ai-content-marking-for-laravel)
[![License](https://img.shields.io/packagist/l/pushery/ai-content-marking-for-laravel.svg)](LICENSE)

[![Tests](https://img.shields.io/badge/tests-Pest%205-8BC34A.svg)](https://pestphp.com)
![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg)
![Type Coverage](https://img.shields.io/badge/types-100%25-brightgreen.svg)
[![PHPStan](https://img.shields.io/badge/PHPStan-max-blue.svg)](https://phpstan.org)
[![Code Style](https://img.shields.io/badge/code%20style-pint-orange.svg)](https://laravel.com/docs/pint)

Marks content made with generative AI in Laravel: machine-readable markers in the text your app delivers and in the images it stores, kept through every media library conversion, and a separate notice for readers that you place yourself.

## Documentation

**Full documentation at
[docs.pushery.com/ai-content-marking-for-laravel](https://docs.pushery.com/ai-content-marking-for-laravel/).**
This README is the quick start; the guides, the configuration reference and the
upgrade notes live there.

## Requirements

- PHP 8.4+
- Laravel 13+
- The `imagick` PHP extension
- `spatie/laravel-medialibrary` 11, which the package extends

Tested on PostgreSQL and MySQL 8.4, the databases Laravel Cloud runs (serverless Postgres
and MySQL 8.4 LTS), so it runs there out of the box. The fast suite runs on SQLite.

Tested on **PHP 8.4** — the floor of the declared range, because a `>=` promise is
only proven by running on the boundary — and on **PHP 8.5**, so the upper half of
`^8.4` is exercised here rather than first by a consumer.

## Installation

```bash
composer require pushery/ai-content-marking-for-laravel
```

The service provider is registered automatically through package discovery.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=ai-content-marking-config
```

This writes `config/ai-content-marking.php`, where every option is documented inline.

The package ships no migrations and no routes.

## Usage

Wrap the text a model returns in a `MarkedText`, and give each place you deliver it the form it
needs:

```php
use Pushery\AiContentMarking\MarkedText;

$description = MarkedText::generated($modelOutput, generator: 'Amazon Bedrock');
```

```blade
<x-ai-content-marking::notice :marking="$description->marking" />
<p>{{ $description->html() }}</p>
```

The notice renders "Created using Generative AI" in the application's locale, and `html()` writes
the escaped text inside schema.org microdata that names the IPTC Digital Source Type term
`trainedAlgorithmicMedia`. In JSON, a `MarkedText` is the text beside the values of its marking,
and wherever a plain string is expected it is the plain text. `MarkedText::edited()` marks a text a
model rewrote.

`Pushery\AiContentMarking\MarkingReader` finds the markings a rendered page carries, for your own
tests, and `Marking` is castable, so a JSON column keeps the marking with a record.

An image carries its marking as XMP:

```php
use Pushery\AiContentMarking\Images\ImageMarker;
use Pushery\AiContentMarking\Marking;

new ImageMarker()->write($path, Marking::generated('Example Image Model'));

new ImageMarker()->read($path)->marksGenerativeAi(); // true
```

The reader also recognizes the XMP other tools write and an embedded C2PA manifest. Every conversion
the media library derives from a marked original carries the original's marking, and
`Pushery\AiContentMarking\Testing\MarkingPipeline` checks in your own suite that your image
pipeline keeps it: [marking images](https://docs.pushery.com/ai-content-marking-for-laravel/images).

A mail carries its marking in a header field, where a DKIM signature can cover it:

```php
use Illuminate\Mail\Mailables\Headers;
use Pushery\AiContentMarking\Mail\MailMarking;

public function headers(): Headers
{
    return new Headers(text: MailMarking::headers($this->reply->marking));
}
```

`MailMarkingReader` reads the field back from a raw mail and names the DKIM signatures that list it.
The model register records whether each model you use watermarks its text, and `GapReporter` holds
every output against the layers of marking the European Commission's Code of Practice asks of text:
[marking mail](https://docs.pushery.com/ai-content-marking-for-laravel/mail) and
[the gap report](https://docs.pushery.com/ai-content-marking-for-laravel/gap-report).

The package writes the marking and renders the notice. Whether that meets an obligation of yours
under the AI Act is not decided by it: the
[documentation](https://docs.pushery.com/ai-content-marking-for-laravel/limits) says what it does
and does not do.

## Testing

The test suite is not part of the published package: the tests and their PHPUnit
configuration stay in the development repository, so a clone of this repository has no
`composer test`. [CONTRIBUTING.md](CONTRIBUTING.md) says how a change is tested on its way in.

## Security

Please review the [security policy](SECURITY.md) and report vulnerabilities
privately rather than opening a public issue.

## Built by Pushery

This package is built and maintained by [Pushery](https://www.pushery.com) — a
Berlin-based studio building Laravel applications, SaaS products, and open-source
tools.

Building a Laravel UI? [WireKit](https://www.wirekit.app) is Pushery's open-source
Livewire component kit.
Browse the rest of our work at [pushery.com](https://www.pushery.com).

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
