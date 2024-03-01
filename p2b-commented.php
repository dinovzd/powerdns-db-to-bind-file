#!/bin/env php
<?php
// Configuration for connecting to the PowerDNS database
$pdns_db = [
    'host' => 'localhost',
    'user' => 'pdns',
    'pass' => 'pdns',
    'name' => 'pdns'
];

// Configuration for the Name Servers and administrative contact for the zone files
$zone_ns = ['ns1.domain.tld', 'ns2.domain.tld']; // Name servers for the zone
$zone_adm = 'hostmaster.domain.tld'; // Administrative contact for the zone

// Initialize an array to hold the DNS zone data
$zones = [];

// Establish a connection to the MySQL database
$db = new mysqli($pdns_db['host'], $pdns_db['user'], $pdns_db['pass'], $pdns_db['name']);
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Query the database for all domains
if ($q = $db->query('SELECT * FROM domains')) {
    while ($r = $q->fetch_assoc()) {
        $d = strtolower($r['name']); // Normalize domain name to lowercase
        
        // Query the database for all records associated with the current domain
        if ($q1 = $db->query('SELECT * FROM records WHERE domain_id=' . $r['id'])) {
            // Initialize arrays to store different types of DNS records
            $x = ['A' => [], 'CNAME' => [], 'NS' => [], 'MX' => [], 'TXT' => [], 'PTR' => []];
            
            while ($r1 = $q1->fetch_assoc()) {
                $r1['name'] = strtolower($r1['name']); // Normalize record name to lowercase
                
                // Switch case to categorize DNS records by type
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
                    case 'PTR': // Handle PTR records similarly
                        $x[$r1['type']][] = [$r1['name'], $r1['content']];
                        break;
                }
            }
            $q1->free(); // Free the result set
        }
        $zones[$d] = $x; // Store the collected records in the zones array
    }
    $q->free(); // Free the result set
}
$db->close(); // Close the database connection

// Check if there are zones to process
if (is_array($zones) && count($zones) > 0) {
    $p = './tmp/'; // Directory where zone files will be stored
    
    // Check if the directory exists, if not, attempt to create it
    if (!is_dir($p)) {
        if (!mkdir($p, 0777, true)) {
            die('Unable to make tmp directory' . PHP_EOL);
        }
    }
    
    // Iterate through each zone to generate the zone files
    foreach ($zones as $d => $r) {
        // Start constructing the DNS zone file content
        $x = '             '; // Placeholder for indenting
        $t = [
            '$TTL 43200', // Default TTL
            null,
            '@ IN SOA ' . $zone_ns[0] . '. ' . $zone_adm . '. (', // SOA record
            $x . time(), // Serial number based on current timestamp
            $x . '7200', // Refresh
            $x . '3600', // Retry
            $x . '604800', // Expire
            $x . '43200 )', // Minimum TTL
            null
        ];
        
        // Add NS records
        foreach ($zone_ns as $ns) {
            $t[] = '  IN NS      ' . $ns . '.';
        }
        $t[] = null;
        
        // Process and add other record types (MX, A, CNAME, TXT, PTR) to the zone file content
        // MX records
        foreach ($r['MX'] as $mx) {
            $mxLine = $mx[0] ? $mx[0] : '@';
            $t[] = $mxLine . '  IN MX ' . str_pad($mx[1], 5, ' ', STR_PAD_RIGHT) . ' ' . $mx[2] . '.';
        }
        // A records
        foreach ($r['A'] as $a) {
            $t[] = str_pad($a[0] . '.', 32, ' ', STR_PAD_RIGHT) . 'IN A ' . $a[1];
        }
        // CNAME records
        foreach ($r['CNAME'] as $cname) {
            $t[] = str_pad($cname[0], 32, ' ', STR_PAD_RIGHT) . 'IN CNAME ' . $cname[1] . '.';
        }
        // TXT records
        foreach ($r['TXT'] as $txt) {
            $t[] = str_pad($txt[0], 32, ' ', STR_PAD_RIGHT) . 'IN TXT "' . $txt[1] . '"';
        }
        // PTR records
        foreach ($r['PTR'] as $ptr) {
            $t[] = $ptr[0] . '. IN PTR ' . $ptr[1] . '.';
        }
        
        // Write the constructed zone file content to a file
        file_put_contents($p . 'db.' . $d, implode(PHP_EOL, $t) . PHP_EOL);
    }
}
?>
