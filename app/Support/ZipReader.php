<?php

namespace App\Support;

use RuntimeException;

/**
 * Reads one entry out of a .zip archive without needing ext-zip.
 *
 * ext-zip is not a Laravel requirement, and plenty of shared hosting leaves
 * it switched off. The course material is plain .docx (which is a .zip), so
 * rather than make the importer fail on those hosts we read the central
 * directory ourselves and inflate with ext-zlib, which is always present.
 *
 * If ZipArchive *is* available we defer to it, because that path is better
 * tested. This reader only exists as the fallback.
 *
 * Scope: store (method 0) and deflate (method 8) entries, no ZIP64, no
 * encryption. That covers every .docx a course author will realistically
 * produce.
 */
class ZipReader
{
    /** @var resource|null */
    private $handle;

    private string $path;

    public function __construct(string $path)
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Unable to read [{$path}].");
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open [{$path}].");
        }

        $this->handle = $handle;
        $this->path = $path;
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    /**
     * The uncompressed contents of a named entry, or null when absent.
     */
    public function read(string $entryName): ?string
    {
        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive;
            $opened = $zip->open($this->path) === true;

            if ($opened) {
                $contents = $zip->getFromName($entryName);
                $zip->close();

                return $contents === false ? null : $contents;
            }
        }

        return $this->readEntry($entryName);
    }

    private function readEntry(string $entryName): ?string
    {
        $size = filesize($this->path);
        $handle = $this->handle;

        if ($size === false || $size < 22) {
            return null;
        }

        // The End Of Central Directory record sits at the very end, after a
        // comment of up to 64KB, so scan backwards for its signature.
        $eocd = null;

        for ($offset = $size - 22; $offset >= 0 && $offset > $size - 22 - 0xFFFF; $offset--) {
            if ($this->readAt($offset, 4) === "PK\x05\x06") {
                $eocd = $offset;
                break;
            }
        }

        if ($eocd === null) {
            throw new RuntimeException("[{$this->path}] is not a valid zip archive.");
        }

        $directory = $this->readAt($eocd + 16, 4);
        $entries = $this->readAt($eocd + 10, 2);

        if ($directory === null || $entries === null) {
            return null;
        }

        $cursor = unpack('V', $directory)[1];
        $total = unpack('v', $entries)[1];

        for ($i = 0; $i < $total; $i++) {
            $header = $this->readAt($cursor, 46);

            if ($header === null || substr($header, 0, 4) !== "PK\x01\x02") {
                return null;
            }

            // Central directory header, 46 fixed bytes then the entry name.
            // The version/flag fields before `method` are skipped.
            $fields = unpack(
                'a4signature/vversion/vversion_needed/vflag/vmethod/vtime/vdate'
                .'/Vcrc/Vcompressed/Vuncompressed/vnamelen/vextralen/vcommentlen'
                .'/vdisk/vinternal/Vexternal/Vlocal',
                $header
            );

            $name = $this->readAt($cursor + 46, $fields['namelen']);

            if ($name === $entryName) {
                return $this->inflateEntry($fields, $name);
            }

            $cursor += 46 + $fields['namelen'] + $fields['extralen'] + $fields['commentlen'];
        }

        return null;
    }

    /**
     * @param  array<string, int|string>  $fields
     */
    private function inflateEntry(array $fields, string $name): ?string
    {
        // The local header repeats the name and extra field lengths, which
        // may differ from the central directory's, so re-read them here.
        // They sit at offsets 26 and 28 of the 30 byte fixed local header.
        $lengths = $this->readAt($fields['local'] + 26, 4);

        if ($lengths === null || $lengths === '') {
            return null;
        }

        $localFields = unpack('vnamelen/vextralen', $lengths);

        $dataOffset = $fields['local'] + 30 + $localFields['namelen'] + $localFields['extralen'];
        $data = $this->readAt($dataOffset, $fields['compressed']);

        if ($data === null) {
            return null;
        }

        return match ((int) $fields['method']) {
            0 => $data,
            8 => gzinflate($data, (int) $fields['uncompressed'] ?: null) ?: null,
            default => throw new RuntimeException(
                "Unsupported compression method [{$fields['method']}] for [{$name}]."
            ),
        };
    }

    private function readAt(int $offset, int $length): ?string
    {
        if (fseek($this->handle, $offset) !== 0) {
            return null;
        }

        $data = $length > 0 ? fread($this->handle, $length) : '';

        return $data === false ? null : $data;
    }
}
