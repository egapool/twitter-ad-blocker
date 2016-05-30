<?php

use Abraham\TwitterOAuth\TwitterOAuth;
require __DIR__ . '/../config/configure.php';
require __DIR__ . '/../vendor/autoload.php';
spl_autoload_register(function ($classname) {
    require (__DIR__ ."/../classes/" . $classname . ".php");
});

$db = $config['db'];
$pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'],$db['user'], $db['pass']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);


// logger
$logger = new \Monolog\Logger('my_logger');
$date = (new DateTime())->format('Ymd');
$file_handler = new \Monolog\Handler\StreamHandler(__DIR__."/../logs/{$date}.log");
$logger->pushHandler($file_handler);

$userObj = new User($pdo);
$users = $userObj->findAllUsers();

foreach ( $users as $user ) {

	$TwitterOAuth = new TwitterOAuth(
		$config['oauth']['twitter']['key'],
		$config['oauth']['twitter']['secret'],
		$user['access_token'],
		$user['access_token_secret']
	);
	$blocker = new Blocker($pdo, $user, $TwitterOAuth);
	$blocker->setLogger($logger);
	$blocker->blockAll();
}
// var_dump($users);