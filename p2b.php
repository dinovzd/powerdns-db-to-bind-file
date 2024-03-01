#!/bin/env php
<?php
$pdns_db = ['host' => 'localhost', 'user' => 'pdns', 'pass' => 'pdns', 'name' => 'pdns'];
$zone_ns = ['ns1.domain.tld', 'ns2.domain.tld'];
$zone_adm = 'hostmaster.domain.tld';

$zones = [];
$db = new mysqli($pdns_db['host'], $pdns_db['user'], $pdns_db['pass'], $pdns_db['name']);
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

if ($q = $db->query('SELECT * FROM domains')) {
    while ($r = $q->fetch_assoc()) {
        $d = strtolower($r['name']);
        if ($q1 = $db->query('SELECT * FROM records WHERE domain_id=' . $r['id'])) {
            $x = ['A' => [], 'CNAME' => [], 'NS' => [], 'MX' => [], 'TXT' => [], 'PTR' => []];
            while ($r1 = $q1->fetch_assoc()) {
                $r1['name'] = strtolower($r1['name']);
                switch ($r1['type']) {
                    case 'A':
                        $x[$r1['type']][] = [$r1['name'], $r1['content']];
                        break;
                    case 'CNAME':
                        $x[$r1['type']][] = [trim(str_replace($d, '', $r1['name']), '.'), strtolower($r1['content'])];
                        break;
                    case 'NS':
                        $x[$r1['type']][] = strtolower($r1['content']);
                        break;
                    case 'MX':
                        $x[$r1['type']][] = [trim(str_replace($d, '', $r1['name']), '.'), (int)$r1['prio'], strtolower($r1['content'])];
                        break;
                    case 'TXT':
                        $x[$r1['type']][] = [trim(str_replace($d, '', $r1['name']), '.'), $r1['content']];
                        break;
                    case 'PTR':
                        $x[$r1['type']][] = [$r1['name'], $r1['content']];
                        break;
                }
            }
            $q1->free();
        }
        $zones[$d] = $x;
    }
    $q->free();
}
$db->close();

if (is_array($zones) && count($zones) > 0) {
    $p = './tmp/';
    if (!is_dir($p)) {
        if (!mkdir($p, 0777, true)) {
            die('Unable to make tmp directory' . PHP_EOL);
        }
    }
    foreach ($zones as $d => $r) {
        $x = '             ';
        $t = ['$TTL 43200', null, '@ IN SOA ' . $zone_ns[0] . '. ' . $zone_adm . '. (', $x . time(), $x . '7200', $x . '3600', $x . '604800', $x . '43200 )', null];
        foreach ($zone_ns as $ns) {
            $t[] = '  IN NS      ' . $ns . '.';
        }
        $t[] = null;
        foreach ($r['MX'] as $mx) {
            $mxLine = $mx[0] ? $mx[0] : '@';
            $t[] = $mxLine . '  IN MX ' . str_pad($mx[1], 5, ' ', STR_PAD_RIGHT) . ' ' . $mx[2] . '.';
        }
        foreach ($r['A'] as $a) {
            $t[] = str_pad($a[0] . '.', 32, ' ', STR_PAD_RIGHT) . 'IN A ' . $a[1];
        }
        foreach ($r['CNAME'] as $cname) {
            $t[] = str_pad($cname[0], 32, ' ', STR_PAD_RIGHT) . 'IN CNAME ' . $cname[1] . '.';
        }
        foreach ($r['TXT'] as $txt) {
            $t[] = str_pad($txt[0], 32, ' ', STR_PAD_RIGHT) . 'IN TXT "' . $txt[1] . '"';
        }
        foreach ($r['PTR'] as $ptr) {
            $t[] = $ptr[0] . '. IN PTR ' . $ptr[1] . '.';
        }
        file_put_contents($p . 'db.' . $d, implode(PHP_EOL, $t) . PHP_EOL);
    }
}
?>
