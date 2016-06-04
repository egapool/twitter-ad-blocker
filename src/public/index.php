<?php

if ( !isset($_SERVER['APP_ENV']) || $_SERVER['APP_ENV'] !== 'production' ) {
	ini_set('display_errors',1);
	ini_set('error_reporting()',-1);
}

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Abraham\TwitterOAuth\TwitterOAuth;

require '../vendor/autoload.php';
require '../config/configure.php';
session_cache_limiter(false);
session_name(SESSION_NAME);
session_start();

spl_autoload_register(function ($classname) {
    require ("../classes/" . $classname . ".php");
});

$app = new \Slim\App(["settings" => $config]);

$app->add(function (Request $request, Response $response, $next){

	$publicRoutes = ['/auth'];

	function url_match($url, $arr){
        foreach($arr as $v) {
            $w = preg_quote(rtrim($v, '/'), '/');
            if (preg_match('/'.$w.'(\/.*)?/', $url)) {
                return true;
            }
        }
    }

	$path = $request->getUri()->getPath();
	if ( url_match($path, $publicRoutes) ) {
		if ( isset($_SESSION['twitter_id']) ) {
			// リダイレクト
			return $response->withStatus(302)->withHeader('Location', '/');
		}
	}
	$response = $next($request, $response);
	return $response;
});

$container = $app->getContainer();
$container['logger'] = function($c) {
    $logger = new \Monolog\Logger('my_logger');
    $date = (new DateTime())->format('Ymd');
    $file_handler = new \Monolog\Handler\StreamHandler("../logs/{$date}.log");
    $logger->pushHandler($file_handler);
    return $logger;
};
$container['db'] = function ($c) {
    $db = $c['settings']['db'];
    $pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'],$db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};
$container['view'] = new \Slim\Views\PhpRenderer("../templates/");

$app->get('/', function (Request $request, Response $response) {

	$user = null;

	if ( isset($_SESSION['twitter_id']) ) {
		$user = (new User($this->db))->findByTwId($_SESSION['twitter_id']);
	}
	if ( $user ) {
		$blockObj = new Blocked($this->db);
		$blockList = $blockObj->getBlockedAccounts($user['id']);

		$v = [
			"user" 		=> $user,
			"blockList" => $blockList,
		];
		$response = $this->view->render($response, "index.phtml", $v);
	} else {
		echo '<a href="/auth/twitter">Twitterアカウントでログイン</a>';
	}


    return $response;
});

$app->get('/auth/twitter', function (Request $request, Response $response) {
	$connection = new TwitterOAuth($this->get('settings')['oauth']['twitter']['key'], $this->get('settings')['oauth']['twitter']['secret']);
	$request_token = $connection->oauth('oauth/request_token', array('oauth_callback' => "https://twitter-ad-blocker.8705.co/auth/twitter/callback"));
	$_SESSION['oauth_token'] = $request_token['oauth_token'];
	$_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];
	$url = $connection->url('oauth/authenticate', array('oauth_token' => $request_token['oauth_token']));

	header( 'location: '. $url );
	exit;

    // return $response;
});

$app->get('/auth/twitter/callback', function (Request $request, Response $response) {

	// cancel
	if ( isset( $_GET['denied'] ) && !empty( $_GET['denied'] ) ) {
		return $response->withStatus(302)->withHeader('Location', '/');
	}

	$request_token = [];
	$request_token['oauth_token'] = $_SESSION['oauth_token'];
	$request_token['oauth_token_secret'] = $_SESSION['oauth_token_secret'];

	if (isset($_REQUEST['oauth_token']) && $request_token['oauth_token'] !== $_REQUEST['oauth_token']) {
	    die( 'Error!' );
	}

	$connection = new TwitterOAuth(
		$this->get('settings')['oauth']['twitter']['key'],
		$this->get('settings')['oauth']['twitter']['secret'],
		$request_token['oauth_token'],
		$request_token['oauth_token_secret']
	);
	$_SESSION['access_token'] = $connection->oauth("oauth/access_token", array("oauth_verifier" => $_REQUEST['oauth_verifier']));

	//ユーザー情報取得
	$access_token = $_SESSION['access_token'];
	$connection = new TwitterOAuth(
		$this->get('settings')['oauth']['twitter']['key'],
		$this->get('settings')['oauth']['twitter']['secret'],
		$access_token['oauth_token'],
		$access_token['oauth_token_secret']
	);
	$tw_user = $connection->get("account/verify_credentials");

	// ログイン処理
	$user = new User($this->db);
	$user->login(
		$tw_user->id,
		$tw_user->screen_name,
		$tw_user->name,
		$tw_user->profile_image_url,
		$access_token['oauth_token'],
		$access_token['oauth_token_secret']
	);

	return $response->withStatus(301)->withHeader('Location', '/');

});

$app->get('/test', function (Request $request, Response $response) {
	$user = new User($this->db);
	$login_user = $user->findByTwId($_SESSION['twitter_id']);

	$access_token = $_SESSION['access_token'];
	$connection = new TwitterOAuth(
		$this->get('settings')['oauth']['twitter']['key'],
		$this->get('settings')['oauth']['twitter']['secret'],
		$access_token['oauth_token'],
		$access_token['oauth_token_secret']
	);
	$timeline = $connection->get("statuses/user_timeline");
	var_dump($timeline);die;

	echo '<img src="'.$login_user['icon'].'" width="100" style="border-radius:50%;">';
	var_dump($login_user);die;

    return $response;
});

$app->run();
// $app->get('/tickets', function (Request $request, Response $response) {
//     $this->logger->addInfo("Ticket list");
//     $mapper = new TicketMapper($this->db);
//     $tickets = $mapper->getTickets();

//     $response->getBody()->write(var_export($tickets, true));
//     return $response;
// });
