# powerdns-db-to-bind-file
PHP script that scrapes the entire database and then generates a bind file for each zone with the records.

Here are the variables you need to change.

$pdns_db = ['host' => 'localhost', 'user' => 'pdns', 'pass' => 'pdns', 'name' => 'pdns'];

$zone_ns = ['ns1.domain.tld', 'ns2.domain.tld'];

$zone_adm = 'hostmaster.domain.tld';
