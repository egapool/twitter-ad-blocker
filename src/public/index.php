<?php
ini_set('display_errors',1);
ini_set('error_reporting()',-1);

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

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
		if ( isset($_SESSION['username']) ) {
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
    $file_handler = new \Monolog\Handler\StreamHandler("../logs/app.log");
    $logger->pushHandler($file_handler);
    return $logger;
};
$container['db'] = function ($c) {
    $db = $c['settings']['db'];
    $pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'],
        $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};
$container['view'] = new \Slim\Views\PhpRenderer("../templates/");

$app->get('/hello/{name}', function (Request $request, Response $response) {
    $name = $request->getAttribute('name');
    // $response->getBody()->write("Hello, $name");

    $response = $this->view->render($response, "hello.phtml", ["name" => $name]);
    return $response;
});
$app->get('/', function (Request $request, Response $response) {
	$response = $this->view->render($response, "index.phtml");
    return $response;
})->setName('top');

$app->get('/auth/twitter', function (Request $request, Response $response) {
	$config = [
	    'security_salt' => $this->get('settings')['oauth']['twitter']['security_salt'], //レスポンスのシグネチャ生成に使うソルト値。デフォルトのままだと Notice が出ておこられる
	    'path' => '/auth/', // Opauth を動かす URL のパス
	    'callback_url' => '/auth/callback',

	    'Strategy' => [
	        'Twitter' => [
	            'key' => $this->get('settings')['oauth']['twitter']['key'],
	            'secret' => $this->get('settings')['oauth']['twitter']['secret']
	        ],
	    ]
	];

	new Opauth($config);

    return $response;
});
$app->get('/auth/twitter/oauth_callback', function (Request $request, Response $response) {
	echo '<a href="/test">TEST API</a>';
    return $response;
})->setName('top');

$app->get('/test', function (Request $request, Response $response) {
	$twitter = new tmhOAuth(
		[
			'consumer_key' 	=> $this->get('settings')['oauth']['twitter']['key'],
			'consumer_secret' => $this->get('settings')['oauth']['twitter']['secret'],
			'user_token' 	=> $_SESSION['_opauth_twitter']['oauth_token'],
			'user_secret' 	=> $_SESSION['_opauth_twitter']['oauth_token_secret'],
			'curl_ssl_verifypeer' => false,
		]
	);
	$status = $twitter->request("GET", $twitter->url("1.1/account/settings"));
	$res = json_decode($twitter->response['response']);
    var_dump($status,$res);die;
	echo 'callback';
    return $response;
})->setName('top');

$app->run();
// $app->get('/tickets', function (Request $request, Response $response) {
//     $this->logger->addInfo("Ticket list");
//     $mapper = new TicketMapper($this->db);
//     $tickets = $mapper->getTickets();

//     $response->getBody()->write(var_export($tickets, true));
//     return $response;
// });
