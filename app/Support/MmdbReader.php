<?php

namespace App\Support;

use RuntimeException;

/**
 * A reader for MaxMind DB files (.mmdb), written to the published format specification
 * (https://maxmind.github.io/MaxMind-DB/). It is used for the visitor locations when the
 * maxmind-db/reader library is not installed; both read the same files the same way.
 *
 * Only what a lookup needs is implemented: the metadata, the binary search tree (record sizes of
 * 24, 28 and 32 bits, IPv4 and IPv6 trees) and the data section (every data type, pointers included).
 */
class MmdbReader
{
    private const METADATA_MARKER = "\xAB\xCD\xEFMaxMind.com";

    /** @var resource */
    private $file;

    private int $fileSize;

    private array $metadata;

    private int $nodeCount;

    private int $recordSize;

    private int $nodeBytes;

    private int $searchTreeSize;

    private int $ipVersion;

    private int $ipv4Start = 0;

    public function __construct(string $path)
    {
        $file = @fopen($path, 'rb');
        if (! $file) {
            throw new RuntimeException("Cannot open $path.");
        }
        $this->file = $file;
        $this->fileSize = (int) fstat($file)['size'];

        // the metadata follows the last marker, within the last 128 KB of the file
        $tailSize = min($this->fileSize, 128 * 1024);
        $tail = $this->read($this->fileSize - $tailSize, $tailSize);
        $at = strrpos($tail, self::METADATA_MARKER);
        if ($at === false) {
            throw new RuntimeException('Not a MaxMind DB file: no metadata.');
        }
        $start = $this->fileSize - $tailSize + $at + strlen(self::METADATA_MARKER);
        [$metadata] = $this->decode($start, $start);
        if (! is_array($metadata) || ! isset($metadata['node_count'], $metadata['record_size'], $metadata['ip_version'])) {
            throw new RuntimeException('Not a MaxMind DB file: incomplete metadata.');
        }
        $this->metadata = $metadata;
        $this->nodeCount = (int) $metadata['node_count'];
        $this->recordSize = (int) $metadata['record_size'];
        if (! in_array($this->recordSize, [24, 28, 32], true)) {
            throw new RuntimeException("Unsupported record size {$this->recordSize}.");
        }
        $this->nodeBytes = $this->recordSize * 2 / 8;
        $this->searchTreeSize = $this->nodeCount * $this->nodeBytes;
        $this->ipVersion = (int) $metadata['ip_version'];

        // in an IPv6 tree the IPv4 addresses live under ::/96: walk 96 zero bits once
        if ($this->ipVersion === 6) {
            $node = 0;
            for ($i = 0; $i < 96 && $node < $this->nodeCount; $i++) {
                $node = $this->record($node, 0);
            }
            $this->ipv4Start = $node;
        }
    }

    public function __destruct()
    {
        if (is_resource($this->file)) {
            fclose($this->file);
        }
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    /** The record for an IP address, or null when the database has none. */
    public function get(string $ip): mixed
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            throw new RuntimeException("Not an IP address: $ip");
        }
        $bits = strlen($packed) * 8;
        if ($bits === 128 && $this->ipVersion === 4) {
            throw new RuntimeException('An IPv6 address cannot be looked up in an IPv4 database.');
        }
        $node = $bits === 32 && $this->ipVersion === 6 ? $this->ipv4Start : 0;

        for ($i = 0; $i < $bits && $node < $this->nodeCount; $i++) {
            $bit = (ord($packed[$i >> 3]) >> (7 - ($i & 7))) & 1;
            $node = $this->record($node, $bit);
        }
        if ($node === $this->nodeCount) {
            return null; // no data for this address
        }
        if ($node < $this->nodeCount) {
            throw new RuntimeException('The search tree is damaged.');
        }
        // a record above the node count points into the data section
        $offset = $this->searchTreeSize + ($node - $this->nodeCount);
        [$value] = $this->decode($offset, $this->searchTreeSize + 16);

        return $value;
    }

    /** The left (0) or right (1) record of a node of the search tree. */
    private function record(int $node, int $bit): int
    {
        $bytes = $this->read($node * $this->nodeBytes, $this->nodeBytes);

        return match ($this->recordSize) {
            24 => unpack('N', "\0".substr($bytes, $bit * 3, 3))[1],
            28 => $bit === 0
                ? ((ord($bytes[3]) & 0xF0) << 20) | unpack('N', "\0".substr($bytes, 0, 3))[1]
                : ((ord($bytes[3]) & 0x0F) << 24) | unpack('N', "\0".substr($bytes, 4, 3))[1],
            32 => unpack('N', substr($bytes, $bit * 4, 4))[1],
        };
    }

    /**
     * Decode one value of the data section.
     *
     * @param  int  $offset  where the value starts in the file
     * @param  int  $base  where the data section starts (pointers count from there)
     * @return array{0: mixed, 1: int} the value and the offset just after it
     */
    private function decode(int $offset, int $base): array
    {
        $control = ord($this->read($offset, 1));
        $offset++;
        $type = $control >> 5;
        if ($type === 0) { // extended type in the next byte
            $type = 7 + ord($this->read($offset, 1));
            $offset++;
        }

        if ($type === 1) { // pointer: the value lives elsewhere; reading continues after the pointer
            $size = ($control >> 3) & 0x3;
            $bytes = $this->read($offset, $size + 1);
            $offset += $size + 1;
            $low = $control & 0x7;
            $pointer = match ($size) {
                0 => ($low << 8) | ord($bytes[0]),
                1 => (($low << 16) | unpack('n', $bytes)[1]) + 2048,
                2 => (($low << 24) | unpack('N', "\0".$bytes)[1]) + 526336,
                3 => unpack('N', $bytes)[1],
            };
            [$value] = $this->decode($base + $pointer, $base);

            return [$value, $offset];
        }

        $size = $control & 0x1F;
        if ($size >= 29) {
            $extra = $size - 28;
            $bytes = $this->read($offset, $extra);
            $offset += $extra;
            $size = match ($extra) {
                1 => 29 + ord($bytes),
                2 => 285 + unpack('n', $bytes)[1],
                3 => 65821 + unpack('N', "\0".$bytes)[1],
            };
        }

        switch ($type) {
            case 2: // UTF-8 string
            case 4: // bytes
                return [$size ? $this->read($offset, $size) : '', $offset + $size];
            case 3: // double
                return [unpack('E', $this->read($offset, 8))[1], $offset + 8];
            case 15: // float
                return [unpack('G', $this->read($offset, 4))[1], $offset + 4];
            case 5: // uint16
            case 6: // uint32
            case 9: // uint64
            case 10: // uint128
                return [$this->unsigned($size ? $this->read($offset, $size) : ''), $offset + $size];
            case 8: // int32
                $value = $size ? unpack('N', str_pad($this->read($offset, $size), 4, "\0", STR_PAD_LEFT))[1] : 0;

                return [$value >= 0x80000000 && $size === 4 ? $value - 0x100000000 : $value, $offset + $size];
            case 7: // map
                $map = [];
                for ($i = 0; $i < $size; $i++) {
                    [$key, $offset] = $this->decode($offset, $base);
                    [$map[$key], $offset] = $this->decode($offset, $base);
                }

                return [$map, $offset];
            case 11: // array
                $list = [];
                for ($i = 0; $i < $size; $i++) {
                    [$list[], $offset] = $this->decode($offset, $base);
                }

                return [$list, $offset];
            case 14: // boolean: the size is the value
                return [$size !== 0, $offset];
            case 12: // data cache container
            case 13: // end marker
                return [null, $offset];
        }

        throw new RuntimeException("Unknown data type $type.");
    }

    /** A big-endian unsigned number; beyond PHP's integers it is given as hexadecimal text (never needed for a lookup). */
    private function unsigned(string $bytes): int|string
    {
        $bytes = ltrim($bytes, "\0");
        if (strlen($bytes) < 8 || (strlen($bytes) === 8 && ord($bytes[0]) < 0x80)) {
            $value = 0;
            foreach (str_split($bytes) as $byte) {
                $value = ($value << 8) | ord($byte);
            }

            return $value;
        }

        return '0x'.bin2hex($bytes);
    }

    private function read(int $offset, int $length): string
    {
        if ($length === 0) {
            return '';
        }
        if ($offset < 0 || $offset + $length > $this->fileSize) {
            throw new RuntimeException('Read past the end of the database file.');
        }
        fseek($this->file, $offset);
        $data = fread($this->file, $length);
        if ($data === false || strlen($data) !== $length) {
            throw new RuntimeException('The database file could not be read.');
        }

        return $data;
    }
}
