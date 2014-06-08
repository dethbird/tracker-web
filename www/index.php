<?php

	/**
	* 
	*    _____                                 __                
  	*   /  _  \ ______ ______     ______ _____/  |_ __ ________  
 	*  /  /_\  \\____ \\____ \   /  ___// __ \   __\  |  \____ \ 
	* /    |    \  |_> >  |_> >  \___ \\  ___/|  | |  |  /  |_> >
	* \____|__  /   __/|   __/  /____  >\___  >__| |____/|   __/ 
	*         \/|__|   |__|          \/     \/           |__|    
	*/
	session_start();
	ini_set('error_reporting', E_ALL);
	ini_set('display_errors', 1);
	define("APPLICATION_PATH", __DIR__ . "/..");
	date_default_timezone_set('America/Los_Angeles');

	//read env file
	// # just points to environment config yml
	$env = parse_ini_file("../env.ini");
	$configs = parse_ini_file($env['config_file']);

	global $api_url, 
		$user_id,
		$client,
		$googleClient;

	$user_id = 1;
	$api_url = $configs['api_url'];


	/**
	* __________               __                                
	* \______   \ ____   _____/  |_  __________________  ______  
 	* |    |  _//  _ \ /  _ \   __\/  ___/\_  __ \__  \ \____ \ 
 	* |    |   (  <_> |  <_> )  |  \___ \  |  | \// __ \|  |_> >
 	* |______  /\____/ \____/|__| /____  > |__|  (____  /   __/ 
	*         \/                        \/             \/|__|    
	*/
	
	require '../vendor/autoload.php';

	use Guzzle\Http\Client;
	use Google\Client as GoogleClient;

	$googleClient = new Google_Client();
	$googleClient->setClientId("478947664225-j1fndcve7sc45mia8rrqghvjd7u9j5df.apps.googleusercontent.com");
	$googleClient->setClientSecret("F-YEjw-iV91SURUgp3w2g4WZ");
	$googleClient->setScopes("https://www.googleapis.com/auth/plus.login https://www.googleapis.com/auth/userinfo.email");
	$googleClient->setRedirectUri("http://tracker-dev.rishisatsangi.com/callback/oauth/google");
	

	$client = new Client($api_url, array(
	    "request.options" => array(
	       "headers" => array(
		       "auth_token" => isset($_COOKIE['auth_token']) ? $_COOKIE['auth_token'] : false
	       	)
	    )
	));

	class AcmeExtension extends \Twig_Extension
	{
	    public function getFilters()
	    {
	        return array(
	            new \Twig_SimpleFilter('print_r', array($this, 'print_r')),
	            new \Twig_SimpleFilter('date_format', array($this, 'date_format')),
	            new \Twig_SimpleFilter('activity_name_label', array($this, 'activity_name_label')),
	        );
	    }

	    public function print_r($output)
	    {
	        return print_r($output,1);
	    }

	    public function date_format($date, $format)
	    {
	        return date($format, strtotime($date));
	    }

	    public function getName()
	    {
	        return 'acme_extension';
	    }


	    public function activity_name_label($activity_type_id, $acivity_types)
	    {
	    	$activity_type = $acivity_types[$activity_type_id];
	    	return '<span class="label label-'.($activity_type['polarity']>0?"suc cess":"danger").'">'.$activity_type['name'].'</span>';
	    }

	}

	$app = new \Slim\Slim(array(
    	'view' => new Slim\Views\Twig(),
    	'templates.path' => APPLICATION_PATH . '/view',
	));
	$view = $app->view();
	$view->parserExtensions = array(
	    new \Slim\Views\TwigExtension(),
	    new AcmeExtension()
	);


	/**
	* __________               __  .__                
	* \______   \ ____  __ ___/  |_|__| ____    ____  
 	* |       _//  _ \|  |  \   __\  |/    \  / ___\ 
 	* |    |   (  <_> )  |  /|  | |  |   |  \/ /_/  >
 	* |____|_  /\____/|____/ |__| |__|___|  /\___  / 
	*         \/                           \//_____/  	
	*/

	/**
	* Authentication should be run as middleware before each route
	*/
	$authCheck = function($app, $client) 
	{
		return function () use ( $app, $client ) 
		{

			//if no auth token
			if (!isset($_COOKIE['auth_token'])) {
    			$app->redirect('/auth');
			}

			//if user not in session
			if (!isset($_SESSION['user'])) {

				//try to get user from the auth token
				$response = $client->get("/user/token-auth")->send();
				$response = json_decode($response->getBody(true));

				if($response->status===true && isset($response->data->id)) {
					$_SESSION['user'] = $response->data;
					setcookie("auth_token", $_SESSION['user']->auth_token, time()+60*60*24*30, "/", "rishisatsangi.com", false, true);
					$_COOKIE['auth_token'] = $_SESSION['user']->auth_token;
					$app->redirect('/');
				} else {
					//clear cookies and session
					unset($_SESSION['user']);
					setcookie("auth_token", null, time() - 3600);
					unset($_COOKIE['auth_token']);
					$app->redirect('/auth');
				}
			}
			
		};
	};


	$app->get('/', $authCheck($app, $client), function () use ($app) {
	    $app->redirect('/activity/add');
	});


	$app->get('/auth', function () use ($app, $googleClient) {

		$app->render('partials/auth.html.twig', array(
	    	"section"=>$app->environment()->offsetGet("PATH_INFO"),
	    	"googleAuthUrl" => $googleClient->createAuthUrl()
    	));

	});

	$app->get('/logout', function () use ($app, $googleClient) {
		
		//clear cookies and session
		unset($_SESSION['user']);
		setcookie("auth_token", "", time() - 3600, "/", "rishisatsangi.com", false, true);
		unset($_COOKIE['auth_token']);
		session_destroy();
		$app->redirect('/auth');

	});

	/**
	*  ACTIVITY
	*/

	//add new log entry form
	$app->get('/activity/add', $authCheck($app, $client), function () use ($app, $client) {
		// var_dump($client);die();
		$typeResponse = json_decode($client->get("activity/type")->send()->getBody(true));
	    $app->render('partials/activity_form.html.twig', array(
	    	"section"=>$app->environment()->offsetGet("PATH_INFO"),
	    	"types" => $typeResponse->data,
	    	"user" => $_SESSION['user']
    	));
	});

	// create an activity
	$app->post('/activity/add', function () use ($app, $client) {

		$response = $client->post("activity", array(), $app->request->params())->send();
		$response = json_decode($response->getBody(true));

		if($response->status===true){
			$app->flash("success", "Activity logged");
			$app->redirect("/activity/report/by/day");
		} else {
			$app->redirect("/activity/add");
		}

	});

	// delete an activity
	$app->post('/activity/delete', function () use ($app, $client) {

		$request = $client->delete("activity");
		$request->getQuery()->set('id', $app->request->params('id'));
		$response = $request->send();

		$response = json_decode($response->getBody(true));
		$app->flash("success", "Activity deleted");
		$app->redirect("/activity/report/by/day");

	});

	//list activity types
	$app->get('/activity/type', function () use ($app, $client) {

		$typeResponse = json_decode($client->get("activity/type")->send()->getBody(true));
		// print_R($typeResponse);
	    $app->render('partials/activity_type_list.html.twig', array(
	    	"section"=>$app->environment()->offsetGet("PATH_INFO"),
	    	"types" => $typeResponse->data,
	    	"user" => $_SESSION['user']
    	));
	});

	//add activity type form
	$app->get('/activity/type/add', function () use ($app, $client) {

	    $app->render('partials/activity_type_form.html.twig', array(
	    	"section"=>"/activity/type",
	    	"user" => $_SESSION['user']
    	));
	});


	//add activity type
	$app->post('/activity/type', function () use ($app, $client) {

		$response = $client->post("activity/type", array(), $app->request->params())->send();
		$response = json_decode($response->getBody(true));

		if($response->status===true){
			$app->redirect("/activity/type");
		} else {
			$app->redirect("/activity/type/add");
		}
	});

	//update activity type
	$app->post('/activity/type/:id', function ($id) use ($app, $client) {

		$params = $app->request->params();

		$response = $client->patch("activity/type/".$id, array(), $params)->send();
		$response = json_decode($response->getBody(true));

		if($response->status===true){
			$app->flash("success", "Activity type updated");
			$app->redirect("/activity/type");
		} else {
			$app->redirect("/activity/type/update/".$id);
		}
	});


	//edit activity type form
	$app->get('/activity/type/update/:id', function ($id) use ($app, $client) {

		//retrieve the record
		$request = $client->get("activity/type/".$id);
		$response = $request->send();
		$response = json_decode($response->getBody(true));

	    $app->render('partials/activity_type_form.html.twig', array(
	    	"section"=>"/activity/type",
	    	"type" => $response->data[0],
	    	"user" => $_SESSION['user']
    	));
	});



	/**
	*  REPORTS 
	*/

	$app->get('/activity/report', function () use ($app) {
	    $app->redirect('/activity/report/by/day');
	});


	$app->get('/activity/report/by/day', function () use ($app, $client) {

		$response = $client->get("/activity/report/by/day")->send();
		$response = json_decode($response->getBody(true), true);

	    $app->render('partials/activity_report_by_day.html.twig', array(
	    	"section"=>$app->environment()->offsetGet("PATH_INFO"),
	    	"report"=>$response['data'],
	    	"user" => $_SESSION['user']
    	));

	});

	/**
	*  OAUTH CALLBACK 
	*/

	$app->get('/callback/oauth/google', function () use ($app, $client, $googleClient) {

		$googleClient->authenticate($_GET['code']);
		$accessToken = $googleClient->getAccessToken();
		$googleClient->setAccessToken($accessToken);

		$plus = new \Google_Service_Plus($googleClient);
		$person = $plus->people->get('me');

		$emails = $person->getEmails();
		$email = $emails[0]->value;

		$params = array(
			"name"=>$person->getDisplayName(),
			"email"=>$email
		);

		$client->setDefaultOption(
	       "headers",  array(
		       "auth_token" => "c4ca4238a0b923820dcc509a6f75849b"
	       	)
	    );

		$request = $client->post("/user", array(), $params);
		$response = $request->send();
		$response = json_decode($response->getBody(true));

		// echo "<pre>"; print_r($response); echo "</pre>"; die();

		$_SESSION['user'] = $response->data;
		setcookie("auth_token", $_SESSION['user']->auth_token, time()+60*60*24*30, "/", "rishisatsangi.com", false, true);
		$_COOKIE['auth_token'] = $_SESSION['user']->auth_token;

		// add flash message
		if(isset($_SESSION['user']->is_new)){
			$app->flash("success", "Account created");
		} else {
			$app->flash("success", "Logged in");
		}

		$app->redirect("/");

	});



	/**
	* __________            ._._._.
	* \______   \__ __  ____| | | |
 	* |       _/  |  \/    \ | | |
 	* |    |   \  |  /   |  \|\|\|	
 	* |____|_  /____/|___|  /_____
	*        \/           \/\/\/\/	
	*/
	$app->run();
?>