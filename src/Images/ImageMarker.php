<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking\Images;

use DateTimeImmutable;
use Imagick;
use ImagickException;
use InvalidArgumentException;
use Pushery\AiContentMarking\DigitalSourceType;
use Pushery\AiContentMarking\Marking;
use SplFileObject;
use SplTempFileObject;
use UnexpectedValueException;

/**
 * Writes a marking into an image as XMP, and reads back what an image declares.
 *
 * One field schema serves both sides. The IPTC term goes into `Iptc4xmpExt:DigitalSourceType` as
 * the URI of the term, the generator into `xmp:CreatorTool` and the moment into `xmp:CreateDate`.
 * The reader finds those fields in the XMP of any tool, whatever prefix it binds the namespaces to,
 * and it recognizes a C2PA manifest. It compares values: a comment such as the `CREATOR: gd-jpeg`
 * gd writes, or a packet whose term is outside the vocabulary, is no marking.
 *
 * JPEG, PNG and WebP are marked without decoding a pixel: the packet goes into an APP1 segment, an
 * `iTXt` chunk or an `XMP ` chunk near the start of the file, and a packet the image carried
 * before is replaced. Any other format Imagick reads and writes is marked through Imagick, which
 * encodes the image again. Every write is read back, and an image that does not hold the value
 * refuses it.
 *
 * The reader reads the first megabyte of a file, where the header segments that carry provenance
 * live, and of a WebP also the metadata chunks its container lists after that, so the cost does not
 * grow with the file. It does not verify the signature of a C2PA manifest: the presence of the
 * standardized marker is the signal.
 */
final readonly class ImageMarker
{
    /** How much of a file the reader reads: 1 MiB. */
    public const int HEAD_BYTES = 1_048_576;

    private const string IPTC_EXTENSION = 'http://iptc.org/std/Iptc4xmpExt/2008-02-29/';

    private const string XMP_BASIC = 'http://ns.adobe.com/xap/1.0/';

    private const string IPTC_TERMS = 'http://cv.iptc.org/newscodes/digitalsourcetype/';

    private const string JPEG_XMP = "http://ns.adobe.com/xap/1.0/\x00";

    private const string JPEG_EXTENDED_XMP = "http://ns.adobe.com/xmp/extension/\x00";

    private const string PNG_XMP = 'XML:com.adobe.xmp';

    private const string BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    /**
     * Writes the marking into the image at the path, in place of the XMP it carried.
     *
     * @throws InvalidArgumentException when there is no file, or no image the marker can write into
     * @throws UnexpectedValueException when the image does not hold the marking once written
     */
    public function write(string $path, Marking $marking): void
    {
        $image = is_file($path) ? file_get_contents($path) : false;

        if ($image === false) {
            throw new InvalidArgumentException("There is no file to mark at [{$path}].");
        }

        file_put_contents($path, $this->mark($image, $marking));
    }

    /**
     * What the image at the path declares.
     *
     * @throws InvalidArgumentException when there is no file
     */
    public function read(string $path): ImageMarkings
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("There is no file to read at [{$path}].");
        }

        return $this->readFrom(new SplFileObject($path, 'rb'));
    }

    /**
     * What the image these bytes hold declares. The first megabyte is enough; a whole WebP also
     * gives the metadata chunks its container lists after it.
     */
    public function inspect(string $bytes): ImageMarkings
    {
        $file = new SplTempFileObject;
        $file->fwrite($bytes);

        return $this->readFrom($file);
    }

    /**
     * The bytes of an image with the marking written into them, in place of the XMP they carried.
     *
     * @throws InvalidArgumentException when the bytes are no image the marker can write into
     * @throws UnexpectedValueException when the image does not hold the marking once written
     */
    public function mark(string $image, Marking $marking): string
    {
        $packet = $this->packet($marking);

        $marked = match (true) {
            str_starts_with($image, "\xFF\xD8\xFF") => $this->intoJpeg($image, $packet),
            str_starts_with($image, "\x89PNG\r\n\x1A\n") => $this->intoPng($image, $packet),
            str_starts_with($image, 'RIFF') && substr($image, 8, 4) === 'WEBP' => $this->intoWebp($image, $packet),
            default => $this->throughImagick($image, $packet),
        };

        $kept = $this->inspect($marked)->xmp;

        if (! $kept instanceof Marking || ! $this->same($kept, $marking)) {
            throw new UnexpectedValueException('The image does not hold the marking written into it: its format keeps no XMP, or keeps it past the first megabyte the reader reads.');
        }

        return $marked;
    }

    private function readFrom(SplFileObject $file): ImageMarkings
    {
        $head = $this->bytes($file, 0, self::HEAD_BYTES);
        $bytes = $head.$this->trailingWebpMetadata($file, $head);

        [$c2pa, $c2paSourceType] = $this->c2pa($bytes);

        return new ImageMarkings($this->xmpMarking($bytes), $c2pa, $c2paSourceType);
    }

    /**
     * The XMP and C2PA chunks a WebP lists past the first megabyte, found by walking the chunk
     * headers, so the cost follows the number of chunks and not the size of the image.
     */
    private function trailingWebpMetadata(SplFileObject $file, string $head): string
    {
        if (! str_starts_with($head, 'RIFF') || substr($head, 8, 4) !== 'WEBP') {
            return '';
        }

        $metadata = '';
        $offset = 12;

        while (strlen($header = $this->bytes($file, $offset, 8)) === 8) {
            $size = $this->uint32le(substr($header, 4, 4));

            if ($offset >= self::HEAD_BYTES && in_array(substr($header, 0, 4), ['XMP ', 'C2PA'], true)) {
                $metadata .= $this->bytes($file, $offset + 8, min($size, self::HEAD_BYTES));
            }

            $offset += 8 + $size + $size % 2;
        }

        return $metadata;
    }

    /**
     * The marking of the first XMP packet that names a term of the vocabulary.
     */
    private function xmpMarking(string $bytes): ?Marking
    {
        $offset = 0;

        while (($start = strpos($bytes, '<rdf:RDF', $offset)) !== false) {
            $end = strpos($bytes, '</rdf:RDF>', $start);

            if ($end === false) {
                return null;
            }

            $packet = substr($bytes, $start, $end - $start);
            $term = $this->property($packet, self::IPTC_EXTENSION, 'DigitalSourceType');
            $sourceType = $term === null ? null : DigitalSourceType::fromUri($term);

            if ($sourceType instanceof DigitalSourceType) {
                return new Marking(
                    $sourceType,
                    $this->property($packet, self::XMP_BASIC, 'CreatorTool'),
                    $this->moment($this->property($packet, self::XMP_BASIC, 'CreateDate')),
                );
            }

            $offset = $end;
        }

        return null;
    }

    /**
     * The value of a property in an XMP packet, under whatever prefix the packet binds its namespace
     * to: as an attribute, as an element with a text, or as an element that names a resource.
     */
    private function property(string $packet, string $namespace, string $name): ?string
    {
        preg_match_all('/xmlns:([A-Za-z_][\w.\-]*)\s*=\s*(["\'])'.preg_quote($namespace, '/').'\2/', $packet, $bindings);

        foreach ($bindings[1] as $prefix) {
            $qualified = preg_quote($prefix.':'.$name, '/');

            if (preg_match('/\s'.$qualified.'\s*=\s*(["\'])(.*?)\1/s', $packet, $match) === 1
                || preg_match('/<'.$qualified.'(?:\s[^>]*)?>([^<]*)<\/'.$qualified.'\s*>/s', $packet, $match) === 1
                || preg_match('/<'.$qualified.'\s[^>]*?rdf:resource\s*=\s*(["\'])(.*?)\1/s', $packet, $match) === 1) {
                $value = trim(html_entity_decode((string) end($match), ENT_QUOTES | ENT_XML1, 'UTF-8'));

                return $value === '' ? null : $value;
            }
        }

        return null;
    }

    /**
     * Whether the bytes carry a C2PA manifest store, a JUMBF box labeled `c2pa`, or a reference to
     * a manifest, and the IPTC term the store names.
     *
     * @return array{bool, DigitalSourceType|null}
     */
    private function c2pa(string $bytes): array
    {
        $offset = 0;

        while (($at = strpos($bytes, 'jumd', $offset)) !== false) {
            // A JUMBF description box: its type, a UUID of sixteen bytes, a byte of toggles, then
            // the label, and the label of a C2PA manifest store is `c2pa`.
            if (substr($bytes, $at + 21, 5) === "c2pa\x00") {
                $named = preg_match('~'.preg_quote(self::IPTC_TERMS, '~').'[A-Za-z]+~', $bytes, $term, 0, $at) === 1;

                return [true, $named ? DigitalSourceType::fromUri($term[0]) : null];
            }

            $offset = $at + 4;
        }

        return [str_contains($bytes, 'urn:c2pa:'), null];
    }

    private function intoJpeg(string $image, string $packet): string
    {
        $payload = self::JPEG_XMP.$packet;

        if (strlen($payload) > 0xFFFD) {
            throw new InvalidArgumentException('The marking is longer than the one APP1 segment a JPEG gives XMP. Shorten the generator.');
        }

        $leading = '';
        $following = '';
        $offset = 2;
        $length = strlen($image);
        $atTheStart = true;

        while ($offset + 4 <= $length && $image[$offset] === "\xFF") {
            $marker = ord($image[$offset + 1]);

            // Start of scan or end of image: what follows is image data, and it stays as it is.
            if ($marker === 0xDA || $marker === 0xD9) {
                break;
            }

            $size = $this->uint16be(substr($image, $offset + 2, 2));
            $segment = substr($image, $offset, 2 + $size);
            $offset += 2 + $size;

            if ($size < 2 || strlen($segment) < 2 + $size) {
                throw new InvalidArgumentException('The JPEG ends inside one of its segments, so a marking cannot be written into it without damage.');
            }

            $signature = substr($segment, 4);

            // An XMP packet the image carried before, standard or extended, gives way to the marking.
            if ($marker === 0xE1 && (str_starts_with($signature, self::JPEG_XMP) || str_starts_with($signature, self::JPEG_EXTENDED_XMP))) {
                continue;
            }

            // The marking follows the JFIF and Exif segments, which belong at the very start.
            $atTheStart = $atTheStart && ($marker === 0xE0 || ($marker === 0xE1 && str_starts_with($signature, "Exif\x00\x00")));

            if ($atTheStart) {
                $leading .= $segment;
            } else {
                $following .= $segment;
            }
        }

        return "\xFF\xD8".$leading."\xFF\xE1".$this->uint16beBytes(strlen($payload) + 2).$payload.$following.substr($image, $offset);
    }

    private function intoPng(string $image, string $packet): string
    {
        $marked = substr($image, 0, 8);
        $offset = 8;
        $length = strlen($image);
        $written = false;

        while ($offset + 12 <= $length) {
            $size = $this->uint32be(substr($image, $offset, 4));
            $type = substr($image, $offset + 4, 4);
            $chunk = substr($image, $offset, 12 + $size);
            $offset += 12 + $size;

            if (strlen($chunk) < 12 + $size) {
                throw new InvalidArgumentException('The PNG ends inside one of its chunks, so a marking cannot be written into it without damage.');
            }

            // An XMP packet the image carried before gives way to the marking.
            if ($type === 'iTXt' && str_starts_with(substr($chunk, 8), self::PNG_XMP."\x00")) {
                continue;
            }

            $marked .= $chunk;

            if ($type === 'IHDR' && ! $written) {
                // Keyword, then no compression, no language and no translated keyword, then the packet.
                $data = self::PNG_XMP."\x00\x00\x00\x00\x00".$packet;
                $marked .= $this->uint32beBytes(strlen($data)).'iTXt'.$data.$this->uint32beBytes(crc32('iTXt'.$data));
                $written = true;
            }
        }

        return $written ? $marked : throw new InvalidArgumentException('The PNG has no IHDR chunk to write the marking after.');
    }

    private function intoWebp(string $image, string $packet): string
    {
        $chunks = [];
        $offset = 12;
        $length = strlen($image);

        while ($offset + 8 <= $length) {
            $fourcc = substr($image, $offset, 4);
            $size = $this->uint32le(substr($image, $offset + 4, 4));
            $data = substr($image, $offset + 8, $size);
            $offset += 8 + $size + $size % 2;

            if (strlen($data) < $size) {
                throw new InvalidArgumentException('The WebP ends inside one of its chunks, so a marking cannot be written into it without damage.');
            }

            // An XMP packet the image carried before gives way to the marking.
            if ($fourcc !== 'XMP ') {
                $chunks[] = [$fourcc, $data];
            }
        }

        [$fourcc, $data] = $chunks[0] ?? throw new InvalidArgumentException('The WebP holds no chunk at all.');

        if ($fourcc === 'VP8X' && strlen($data) >= 10) {
            // The extended format: the flag that announces XMP is set, the rest stays.
            $chunks[0] = ['VP8X', chr(ord($data[0]) | 0x04).substr($data, 1)];
        } else {
            // The simple format becomes the extended one, which can carry XMP.
            [$width, $height, $alpha] = $this->canvasOf($fourcc, $data);
            array_unshift($chunks, ['VP8X', chr(0x04 | ($alpha ? 0x10 : 0x00))."\x00\x00\x00".$this->uint24leBytes($width - 1).$this->uint24leBytes($height - 1)]);
        }

        // After the VP8X chunk and its color profile and ahead of the image data, so the first
        // megabyte holds the marking however large the image. Decoders read the chunk anywhere.
        array_splice($chunks, isset($chunks[1]) && $chunks[1][0] === 'ICCP' ? 2 : 1, 0, [['XMP ', $packet]]);

        $body = 'WEBP';

        foreach ($chunks as [$name, $content]) {
            $body .= $name.$this->uint32leBytes(strlen($content)).$content.(strlen($content) % 2 === 1 ? "\x00" : '');
        }

        return 'RIFF'.$this->uint32leBytes(strlen($body)).$body;
    }

    /**
     * The canvas of a WebP in the simple format, read from its bitstream, and whether it has alpha.
     *
     * @return array{int, int, bool}
     */
    private function canvasOf(string $fourcc, string $data): array
    {
        if ($fourcc === 'VP8 ' && strlen($data) >= 10 && substr($data, 3, 3) === "\x9D\x01\x2A") {
            return [$this->uint16le(substr($data, 6, 2)) & 0x3FFF, $this->uint16le(substr($data, 8, 2)) & 0x3FFF, false];
        }

        if ($fourcc === 'VP8L' && strlen($data) >= 5 && $data[0] === "\x2F") {
            $bits = $this->uint32le(substr($data, 1, 4));

            return [($bits & 0x3FFF) + 1, (($bits >> 14) & 0x3FFF) + 1, (($bits >> 28) & 1) === 1];
        }

        throw new InvalidArgumentException('The WebP starts with neither a VP8X chunk nor a VP8 or VP8L bitstream it can read, so the size of its canvas is unknown.');
    }

    private function throughImagick(string $image, string $packet): string
    {
        try {
            $imagick = new Imagick;
            $imagick->readImageBlob($image);
            $imagick->profileImage('xmp', $packet);

            return $imagick->getImagesBlob();
        } catch (ImagickException $exception) {
            throw new InvalidArgumentException('The bytes are no image Imagick can read: '.$exception->getMessage(), 0, $exception);
        }
    }

    /**
     * The XMP packet of a marking, in the one schema the reader reads back.
     */
    private function packet(Marking $marking): string
    {
        $properties = "\n".'    Iptc4xmpExt:DigitalSourceType="'.$this->xml($marking->sourceType->iptcUri()).'"';

        if ($marking->generator !== null) {
            $properties .= "\n".'    xmp:CreatorTool="'.$this->xml($marking->generator).'"';
        }

        if ($marking->generatedAt instanceof DateTimeImmutable) {
            $properties .= "\n".'    xmp:CreateDate="'.$marking->generatedAt->format(DATE_ATOM).'"';
        }

        return '<?xpacket begin="'.self::BYTE_ORDER_MARK.'" id="W5M0MpCehiHzreSzNTczkc9d"?>'."\n"
            .'<x:xmpmeta xmlns:x="adobe:ns:meta/">'."\n"
            .' <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'."\n"
            .'  <rdf:Description rdf:about=""'."\n"
            .'    xmlns:Iptc4xmpExt="'.self::IPTC_EXTENSION.'"'."\n"
            .'    xmlns:xmp="'.self::XMP_BASIC.'"'
            .$properties.'/>'."\n"
            .' </rdf:RDF>'."\n"
            .'</x:xmpmeta>'."\n"
            .'<?xpacket end="w"?>';
    }

    /**
     * A moment as XMP writes it, with or without fractions of a second; null for a value that names
     * no moment, such as a date that does not exist.
     */
    private function moment(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        foreach (['Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP'] as $format) {
            $moment = DateTimeImmutable::createFromFormat($format, $value);

            if ($moment instanceof DateTimeImmutable && DateTimeImmutable::getLastErrors() === false) {
                return $moment;
            }
        }

        return null;
    }

    private function same(Marking $one, Marking $other): bool
    {
        return $one->sourceType === $other->sourceType
            && $one->generator === $other->generator
            && $one->generatedAt?->format(DATE_ATOM) === $other->generatedAt?->format(DATE_ATOM);
    }

    private function bytes(SplFileObject $file, int $offset, int $length): string
    {
        if ($length < 1 || $file->fseek($offset) !== 0) {
            return '';
        }

        $bytes = $file->fread($length);

        return $bytes === false ? '' : $bytes;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function uint16be(string $bytes): int
    {
        return ord($bytes[0] ?? "\x00") << 8 | ord($bytes[1] ?? "\x00");
    }

    private function uint16le(string $bytes): int
    {
        return ord($bytes[0] ?? "\x00") | ord($bytes[1] ?? "\x00") << 8;
    }

    private function uint32be(string $bytes): int
    {
        return $this->uint16be(substr($bytes, 0, 2)) << 16 | $this->uint16be(substr($bytes, 2, 2));
    }

    private function uint32le(string $bytes): int
    {
        return $this->uint16le(substr($bytes, 0, 2)) | $this->uint16le(substr($bytes, 2, 2)) << 16;
    }

    private function uint16beBytes(int $value): string
    {
        return chr($value >> 8 & 0xFF).chr($value & 0xFF);
    }

    private function uint24leBytes(int $value): string
    {
        return chr($value & 0xFF).chr($value >> 8 & 0xFF).chr($value >> 16 & 0xFF);
    }

    private function uint32beBytes(int $value): string
    {
        return chr($value >> 24 & 0xFF).chr($value >> 16 & 0xFF).chr($value >> 8 & 0xFF).chr($value & 0xFF);
    }

    private function uint32leBytes(int $value): string
    {
        return chr($value & 0xFF).chr($value >> 8 & 0xFF).chr($value >> 16 & 0xFF).chr($value >> 24 & 0xFF);
    }
}
