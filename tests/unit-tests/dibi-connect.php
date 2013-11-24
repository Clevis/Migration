<?php
if (!is_file(__DIR__ . '/config.json'))
{
	Tester\Environment::skip('Missing file config.json');
}

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), TRUE);
$dibiConnection = new DibiConnection($config['dibi']);

return $dibiConnection;
