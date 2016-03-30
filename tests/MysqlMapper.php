<?php

require __DIR__ . '/../src/MysqlMapper.php';

$mapper = new MysqlMapper('192.168.99.100:3307', 'realtime_walmart_com', 'root', 'root');
$mapper->setDestination('../resources/');
$mapper->loadJoinSettings('../resources/join_settings.json');
$mapper->loadCustomMapping('../resources/custom_mapping.json');
$mapper->map();

echo 'error = ' . $mapper->error . "\r\n";
echo "done\r\n";
