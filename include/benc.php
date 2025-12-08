<?php
declare(strict_types=1);

/**
 * ===== BENCODE (serialize) =====
 * Поддерживает структуру вида:
 * ['type' => 'string|integer|list|dictionary', 'value' => mixed]
 */

function benc(array $obj): ?string {
    if (!isset($obj['type'], $obj['value'])) return null;

    return match ($obj['type']) {
        'string'     => benc_str((string)$obj['value']),
        'integer'    => benc_int((int)$obj['value']),
        'list'       => benc_list((array)$obj['value']),
        'dictionary' => benc_dict((array)$obj['value']),
        default      => null,
    };
}

function benc_str(string $s): string {
    // strlen() в PHP 8 — байтовая длина (то, что нужно для bencode)
    return \strlen($s) . ':' . $s;
}

function benc_int(int $i): string {
    return 'i' . $i . 'e';
}

function benc_list(array $a): string {
    $out = 'l';
    foreach ($a as $elem) {
        $enc = benc($elem);
        if ($enc === null) return ''; // защитно, но поведение можно менять на исключение
        $out .= $enc;
    }
    return $out . 'e';
}

function benc_dict(array $d): string {
    // Ключи должны быть строками и отсортированы лексикографически (по байтам)
    $out = 'd';
    $keys = array_keys($d);
    // Быстрая и стабильная сортировка ключей как строк (байтовая лексикография)
    sort($keys, SORT_STRING);
    foreach ($keys as $k) {
        $out .= benc_str((string)$k);
        $enc = benc($d[$k]);
        if ($enc === null) return '';
        $out .= $enc;
    }
    return $out . 'e';
}

/**
 * ===== BDECODE (deserialize) =====
 * Возвращает структуру:
 * ['type' => 'string|integer|list|dictionary', 'value' => mixed, 'strlen' => int, 'string' => string]
 * где 'string' — точный фрагмент исходной строки, который был распознан.
 */

function bdec_file(string $f, int $maxBytes): ?array {
    if (!is_file($f)) return null;
    if ($maxBytes <= 0) {
        // Читаем файл целиком (осторожно с очень большими файлами)
        $data = file_get_contents($f);
    } else {
        $data = file_get_contents($f, false, null, 0, $maxBytes);
    }
    if ($data === false) return null;
    return bdec($data);
}

function bdec(string $s): ?array {
    if ($s === '') return null;
    $i = 0; // текущий байтовый индекс парсера
    $node = _bparse_value($s, $i);
    return $node;
}

/* ==================== PRIVATE: индексный парсер ==================== */

/**
 * Парсит одно значение bencode, начиная с offset $i.
 * Возвращает ту же форму узла, что и публичный bdec(), с корректными 'strlen' и 'string'.
 */
function _bparse_value(string $s, int &$i): ?array {
    $start = $i;
    $len = \strlen($s);
    if ($i >= $len) return null;

    $c = $s[$i];

    // string: <len>:<bytes>
    if ($c >= '0' && $c <= '9') {
        $numStart = $i;
        // читаем длину
        $n = 0;
        while ($i < $len && $s[$i] >= '0' && $s[$i] <= '9') {
            // защита от переполнения длины (нереально для торрентов, но корректно)
            $n = $n * 10 + (ord($s[$i]) - 48);
            $i++;
        }
        if ($i >= $len || $s[$i] !== ':') return null;
        $i++; // пропускаем ':'
        // теперь $n байт данных
        if ($n < 0) return null;
        if ($i + $n > $len) return null;
        $val = substr($s, $i, $n);
        $i += $n;

        $raw = substr($s, $start, $i - $start);
        return [
            'type'   => 'string',
            'value'  => $val,
            'strlen' => \strlen($raw),
            'string' => $raw,
        ];
    }

    // integer: i<digits>e (без лидирующих нулей, кроме нуля; без -0)
    if ($c === 'i') {
        $i++; // skip 'i'
        if ($i >= $len) return null;

        $neg = false;
        if ($s[$i] === '-') {
            $neg = true;
            $i++;
            if ($i >= $len) return null;
        }
        if ($s[$i] < '0' || $s[$i] > '9') return null;

        // лидирующие нули запрещены (кроме одиночного "0")
        $isZero = ($s[$i] === '0');
        $numStart = $i;
        while ($i < $len && $s[$i] >= '0' && $s[$i] <= '9') {
            $i++;
        }
        $numStr = substr($s, $numStart, $i - $numStart);
        if ($isZero && \strlen($numStr) > 1) return null; // 0xxx — запрещено
        if ($neg && $numStr === '0') return null;         // -0 — запрещено

        if ($i >= $len || $s[$i] !== 'e') return null;
        $i++; // skip 'e'

        // Преобразуем в int (bencode допускает большие числа, но в PHP int 64-бит)
        // Если хочешь строго BigInt — можно хранить value строкой.
        $intVal = (int)($neg ? ('-' . $numStr) : $numStr);

        $raw = substr($s, $start, $i - $start);
        return [
            'type'   => 'integer',
            'value'  => $intVal,
            'strlen' => \strlen($raw),
            'string' => $raw,
        ];
    }

    // list: l <values> e
    if ($c === 'l') {
        $i++; // skip 'l'
        $items = [];
        while (true) {
            if ($i >= $len) return null;
            if ($s[$i] === 'e') { $i++; break; }
            $elem = _bparse_value($s, $i);
            if (!is_array($elem)) return null;
            $items[] = $elem;
        }
        $raw = substr($s, $start, $i - $start);
        return [
            'type'   => 'list',
            'value'  => $items,
            'strlen' => \strlen($raw),
            'string' => $raw,
        ];
    }

    // dict: d <key><value> ... e ; ключи — strings
    if ($c === 'd') {
        $i++; // skip 'd'
        $dict = [];
        while (true) {
            if ($i >= $len) return null;
            if ($s[$i] === 'e') { $i++; break; }

            // key должен быть string
            $keyNode = _bparse_value($s, $i);
            if (!is_array($keyNode) || $keyNode['type'] !== 'string') return null;
            $key = (string)$keyNode['value'];

            $valNode = _bparse_value($s, $i);
            if (!is_array($valNode)) return null;

            $dict[$key] = $valNode;
        }
        $raw = substr($s, $start, $i - $start);
        return [
            'type'   => 'dictionary',
            'value'  => $dict,
            'strlen' => \strlen($raw),
            'string' => $raw,
        ];
    }

    // неизвестный префикс
    return null;
}
