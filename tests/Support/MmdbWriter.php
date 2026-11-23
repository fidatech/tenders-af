<?php

namespace Tests\Support;

/**
 * Writes small MaxMind DB files for the tests, to the published specification
 * (https://maxmind.github.io/MaxMind-DB/): an IPv6 search tree with IPv4 under ::/96, a record size of
 * 24, 28 or 32 bits, a data section with repeated strings written as pointers, and the metadata.
 */
class MmdbWriter
{
    /** @var array<int, array{0: mixed, 1: mixed}> */
    private array $nodes = [[null, null]];

    private string $data = '';

    /** @var array<string, int> */
    private array $seen = [];

    /** @param  array<string, array>  $networks  CIDR => record */
    public static function write(string $path, array $networks, int $recordSize = 28): void
    {
        $writer = new self;
        foreach ($networks as $cidr => $record) {
            $writer->insert($cidr, $record);
        }
        file_put_contents($path, $writer->build($recordSize));
    }

    private function insert(string $cidr, array $record): void
    {
        [$ip, $prefix] = explode('/', $cidr);
        $packed = inet_pton($ip);
        if (strlen($packed) === 4) {
            $packed = str_repeat("\0", 12).$packed;
            $prefix += 96;
        }
        $node = 0;
        for ($i = 0; $i < $prefix; $i++) {
            $bit = (ord($packed[$i >> 3]) >> (7 - ($i & 7))) & 1;
            if ($i === $prefix - 1) {
                $this->nodes[$node][$bit] = ['data', $record];
            } else {
                if (! is_int($this->nodes[$node][$bit])) {
                    $this->nodes[] = [null, null];
                    $this->nodes[$node][$bit] = count($this->nodes) - 1;
                }
                $node = $this->nodes[$node][$bit];
            }
        }
    }

    private function build(int $recordSize): string
    {
        $count = count($this->nodes);
        $tree = '';
        foreach ($this->nodes as [$left, $right]) {
            [$l, $r] = [$this->value($left, $count), $this->value($right, $count)];
            $tree .= match ($recordSize) {
                24 => substr(pack('N', $l), 1).substr(pack('N', $r), 1),
                28 => substr(pack('N', $l), 1).chr((($l >> 24) & 0x0F) << 4 | (($r >> 24) & 0x0F)).substr(pack('N', $r), 1),
                32 => pack('N', $l).pack('N', $r),
            };
        }
        $metadata = $this->encode([
            'binary_format_major_version' => ['uint16', 2], 'binary_format_minor_version' => ['uint16', 0],
            'build_epoch' => ['uint64', 1700000000], 'database_type' => 'Test-City', 'description' => ['en' => 'Test database'],
            'ip_version' => ['uint16', 6], 'languages' => ['array', ['en']], 'node_count' => ['uint32', $count], 'record_size' => ['uint16', $recordSize],
        ], false);

        return $tree.str_repeat("\0", 16).$this->data."\xAB\xCD\xEFMaxMind.com".$metadata;
    }

    private function value(mixed $child, int $count): int
    {
        if (is_int($child)) {
            return $child;
        }
        if ($child === null) {
            return $count; // no data
        }
        $offset = strlen($this->data);
        $this->data .= $this->encode($child[1], true, $offset);

        return $count + 16 + $offset;
    }

    /** Encode a value; plain strings already written become pointers when $pointers is on. */
    private function encode(mixed $value, bool $pointers, ?int $at = null): string
    {
        $at ??= strlen($this->data);
        if (is_array($value) && in_array($value[0] ?? null, ['uint16', 'uint32', 'uint64', 'array', 'double', 'bool'], true) && count($value) === 2 && array_is_list($value)) {
            [$type, $inner] = $value;

            return match ($type) {
                'uint16' => $this->control(5, strlen($b = ltrim(pack('n', $inner), "\0"))).$b,
                'uint32' => $this->control(6, strlen($b = ltrim(pack('N', $inner), "\0"))).$b,
                'uint64' => $this->control(9, strlen($b = ltrim(pack('J', $inner), "\0"))).$b,
                'double' => $this->control(3, 8).pack('E', $inner),
                'bool' => $this->control(14, $inner ? 1 : 0),
                'array' => $this->list($inner, $pointers, $at),
            };
        }
        if (is_string($value)) {
            if ($pointers && isset($this->seen[$value])) {
                return $this->pointer($this->seen[$value]);
            }
            if ($pointers) {
                $this->seen[$value] = $at;
            }

            return $this->control(2, strlen($value)).$value;
        }
        if (is_int($value)) {
            return $this->encode(['uint32', $value], $pointers, $at);
        }
        if (is_float($value)) {
            return $this->encode(['double', $value], $pointers, $at);
        }
        if (is_array($value)) {
            $out = $this->control(7, count($value));
            foreach ($value as $key => $item) {
                $out .= $this->encode((string) $key, $pointers, $at + strlen($out));
                $out .= $this->encode($item, $pointers, $at + strlen($out));
            }

            return $out;
        }
        throw new \InvalidArgumentException('Cannot encode '.gettype($value));
    }

    private function list(array $items, bool $pointers, int $at): string
    {
        $out = $this->control(11, count($items));
        foreach ($items as $item) {
            $out .= $this->encode($item, $pointers, $at + strlen($out));
        }

        return $out;
    }

    private function control(int $type, int $size): string
    {
        $extended = $type > 7;
        $prefix = '';
        if ($size < 29) {
            $bits = $size;
        } elseif ($size < 285) {
            [$bits, $prefix] = [29, chr($size - 29)];
        } elseif ($size < 65821) {
            [$bits, $prefix] = [30, pack('n', $size - 285)];
        } else {
            [$bits, $prefix] = [31, substr(pack('N', $size - 65821), 1)];
        }

        return chr(($extended ? 0 : $type << 5) | $bits).($extended ? chr($type - 7) : '').$prefix;
    }

    private function pointer(int $offset): string
    {
        if ($offset < 2048) {
            return chr((1 << 5) | ($offset >> 8)).chr($offset & 0xFF);
        }
        if ($offset < 526336) {
            $p = $offset - 2048;

            return chr((1 << 5) | (1 << 3) | ($p >> 16)).pack('n', $p & 0xFFFF);
        }
        $p = $offset - 526336;

        return chr((1 << 5) | (2 << 3) | ($p >> 24)).substr(pack('N', $p & 0xFFFFFF), 1);
    }
}
