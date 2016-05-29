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

$userObj = new User($pdo);
$users = $userObj->findAllUsers();

foreach ( $users as $user ) {

	$TwitterOAuth = new TwitterOAuth(
		$config['oauth']['twitter']['key'],
		$config['oauth']['twitter']['secret'],
		$user['oauth_token'],
		$user['oauth_token_secret']
	);
	$blocker = new Blocker($pdo, $user, $TwitterOAuth);
	$blocker->blockAll();
}
// var_dump($users);