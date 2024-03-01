# powerdns-db-to-bind-file
PHP script that scrapes the entire database and then generates a bind file for each zone with the records.

Here are the variables you need to change.

$pdns_db = [ 'host' => 'CHANGE ME', 'user' => 'CHANGE ME', 'pass' => 'CHANGE ME', 'name' => 'CHANGE ME' ];
$zone_ns = [ 'CHANGE ME', 'CHANGE ME' ];
$zone_adm = 'CHANGE ME';
