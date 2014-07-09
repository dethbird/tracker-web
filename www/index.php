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
	// date_default_timezone_set('America/New_York');
	date_default_timezone_set('America/New_York');


	global $configs,
		$client,
		$googleClient,
		$instagramClient;


	//read env file
	// # just points to environment config yml
	$env = parse_ini_file("../env.ini");
	$configs = parse_ini_file($env['config_file']);

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
	$googleClient->setClientId($configs['google.client_id']);
	$googleClient->setClientSecret($configs['google.client_secret']);
	$googleClient->setScopes($configs['google.scopes']);
	$googleClient->setRedirectUri($configs['google.redirect_url']);

		

	$client = new Client($configs['api_url'], array(
	    "request.options" => array(
	       "headers" => array(
		       "auth_token" => isset($_COOKIE['auth_token']) ? $_COOKIE['auth_token'] : false
	       	)
	    )
	));

	require_once("../vendor/cosenary/instagram/instagram.class.php");
	$instagramClient = new Instagram(array(
      'apiKey'      => $configs['instagram.key'],
      'apiSecret'   => $configs['instagram.secret'],
      'apiCallback' => "http://". $_SERVER['HTTP_HOST'] . '/callback/oauth/instagram'
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

	    public function date_format($date, $format = "F j, Y g:i:a")
	    {
	    	// echo $date; die();
	        return date($format, strtotime($date));
	    }

	    public function getName()
	    {
	        return 'acme_extension';
	    }


	    public function activity_name_label($activity_type_id, $acivity_types, $has_goal=null)
	    {
	    	$activity_type = $acivity_types[$activity_type_id];
	    	return '<span class="label label-'.
	    	($activity_type['polarity']>0?"success":"danger").
	    	'" >'.
	    	$activity_type['name'].

	    	($has_goal=="Y" ? ' <span class="glyphicon glyphicon-'.($activity_type['polarity']>0?"star":"star-empty").'"></span>' : null).
	    	'</span>';
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
			global $configs;

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
					setcookie("auth_token", $_SESSION['user']->auth_token, time()+60*60*24*30, "/", $configs['cookie.domain'], false, true);
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

	/**
	*  AUTH
	*/

	$app->get('/', $authCheck($app, $client), function () use ($app) {
	    // $app->redirect('/activity/add');
	    $app->redirect('/goals/activity');
	});


	$app->get('/auth', function () use ($app, $googleClient) {

		$app->render('partials/auth.html.twig', array(
	    	"section"=>$app->environment()->offsetGet("PATH_INFO"),
	    	"googleAuthUrl" => $googleClient->createAuthUrl()
    	));

	});

	$app->get('/logout', function () use ($app, $googleClient) {
		global $configs; 

		//clear cookies and session
		unset($_SESSION['user']);
		setcookie("auth_token", "", time() - 3600, "/", $configs['cookie.domain'], false, true);
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
	    	"section"=>"/activity",
	    	"types" => $typeResponse->data,
	    	"user" => $_SESSION['user']
    	));
	});



		//add new log entry form
	$app->get('/activity/add/type/:id', $authCheck($app, $client), function ($id) use ($app, $client) {
		// var_dump($client);die();
		$typeResponse = json_decode($client->get("activity/type")->send()->getBody(true));
	    $app->render('partials/activity_form.html.twig', array(
	    	"section"=>"/activity",
	    	"types" => $typeResponse->data,
	    	"user" => $_SESSION['user'],
	    	"activity_type_id" => $id
    	));
	});

	// create an activity
	$app->post('/activity', $authCheck($app, $client), function () use ($app, $client) {

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
	$app->post('/activity/delete', $authCheck($app, $client), function () use ($app, $client) {

		$request = $client->delete("activity");
		$request->getQuery()->set('id', $app->request->params('id'));
		$response = $request->send();

		$response = json_decode($response->getBody(true));
		$app->flash("success", "Activity deleted");
		$app->redirect("/activity/report/by/day");

	});

	/**
	*  TYPES
	*/	

	//list activity types
	$app->get('/activity/type', $authCheck($app, $client), function () use ($app, $client) {

		$typeResponse = json_decode($client->get("activity/type")->send()->getBody(true));
		// print_R($typeResponse);
	    $app->render('partials/activity_type_list.html.twig', array(
	    	"section"=>"/activity",
	    	"types" => $typeResponse->data,
	    	"user" => $_SESSION['user']
    	));
	});

	//add activity type form
	$app->get('/activity/type/add', $authCheck($app, $client), function () use ($app, $client) {

	    $app->render('partials/activity_type_form.html.twig', array(
	    	"section"=>"/activity",
	    	"user" => $_SESSION['user']
    	));
	});


	//edit activity type form
	$app->get('/activity/type/:id', $authCheck($app, $client), function ($id) use ($app, $client) {

		//retrieve the record
		$request = $client->get("activity/type/".$id);
		$response = $request->send();
		$response = json_decode($response->getBody(true));

	    $app->render('partials/activity_type_form.html.twig', array(
	    	"section"=>"/activity",
	    	"type" => $response->data[0],
	    	"user" => $_SESSION['user']
    	));
	});

	//add activity type
	$app->post('/activity/type', $authCheck($app, $client), function () use ($app, $client) {

		$response = $client->post("activity/type", array(), $app->request->params())->send();
		$response = json_decode($response->getBody(true));

		if($response->status===true){
			$app->flash("success", "Activity type added");
			$app->redirect("/activity/type");
		} else {
			$app->redirect("/activity/type/add");
		}
	});

	//update activity type
	$app->post('/activity/type/:id', $authCheck($app, $client), function ($id) use ($app, $client) {

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

	//edit log entry form
	$app->get('/activity/:id', $authCheck($app, $client), function ($id) use ($app, $client) {
		// var_dump($client);die();
		$response = json_decode($client->get("activity/".$id)->send()->getBody(true));
		// var_dump($response); die();
		$activity = $response->data[0];
		//
		// var_dump($response);die();

		$typeResponse = json_decode($client->get("activity/type")->send()->getBody(true));
	    $app->render('partials/activity_form.html.twig', array(
	    	"section"=>"/activity",
	    	"activity" => $response->data[0],
	    	"types" => $typeResponse->data,
	    	"user" => $_SESSION['user']
    	));
	});

	// update an activity
	$app->post('/activity/:id', $authCheck($app, $client), function ($id) use ($app, $client) {

		$response = $client->patch("activity/".$id, array(), $app->request->params())->send();
		// var_dump($response->getBody(true)); die();
		$response = json_decode($response->getBody(true));

		if($response->status===true){
			$app->flash("success", "Activity updated");
			$app->redirect("/activity/report/by/day");
		} else {
			$app->redirect("/activity/add");
		}

	});



	/**
	*  GOALS
	*/	

	//list goals
	$app->get('/goals', $authCheck($app, $client), function () use ($app, $client) {

		$response = json_decode($client->get("goals")->send()->getBody(true));
	    $app->render('partials/goal_list.html.twig', array(
	    	"section"=>$app->environment()->offsetGet("PATH_INFO"),
	    	"goals" => $response->data,
	    	"user" => $_SESSION['user']
    	));
	});

	//add goal form
	$app->get('/goals/add', $authCheck($app, $client), function () use ($app, $client) {

		$typeResponse = json_decode($client->get("activity/type")->send()->getBody(true));
	    $app->render('partials/goal_form.html.twig', array(
	    	"section"=>"/goals",
	    	"types" => $typeResponse->data,
	    	"user" => $_SESSION['user']
    	));
	});

	//list goals with button to add activity to that goal
	$app->get('/goals/activity', $authCheck($app, $client), function () use ($app, $client) {

		$response = json_decode($client->get("goals")->send()->getBody(true));

		//get goal activity for last week
		$request = $client->get("/activity");
		$request->getQuery()->set('start_date', strtotime(date("Y-m-d")));

		$activityResponse = json_decode($request->send()->getBody(true));

		// correlate
		$goals = array();

		$today = null;
		foreach ($response->data as $i => $goal) {
			$goal->logs = array();
			foreach($activityResponse->data as $activity){
				if(is_null($today)){
					$today = date("Y-m-d",strtotime($activity->date_added->date));
				}
				if($goal->activity_type_id === $activity->activity_type_id){
					if($goal->timeframe=="week"){
						$goal->logs[] = $activity;
					} else if($goal->timeframe=="day"){
						if(date("Y-m-d",strtotime($activity->date_added->date)) == $today){
							$goal->logs[] = $activity;
						}
					}
				}
			}
		}

	    $app->render('partials/goal_activity.html.twig', array(
	    	"section"=>"/goals",
	    	"goals" => $response->data,
	    	"user" => $_SESSION['user']
    	));
	});	

	//list goals
	$app->get('/goals/:id', $authCheck($app, $client), function ($id) use ($app, $client) {

		$typeResponse = json_decode($client->get("activity/type")->send()->getBody(true));

		$request = $client->get("goals/".$id);
		$response = $request->send();
		$response = json_decode($response->getBody(true));

		// print_r($response); die();
	    $app->render('partials/goal_form.html.twig', array(
	    	"section"=>"/goals",
	    	"types" => $typeResponse->data,
	    	"goal" => $response->data[0],
	    	"user" => $_SESSION['user']
    	));
	});	

	//add goal
	$app->post('/goals', $authCheck($app, $client), function () use ($app, $client) {
		$response = $client->post("goals", array(), $app->request->params())->send();
		$response = json_decode($response->getBody(true));

		if($response->status===true){
			$app->flash("success", "Goal added");
			$app->redirect("/goals");
		} else {
			$app->redirect("/goals/add");
		}
	});	

	//update goal
	$app->post('/goals/:id', $authCheck($app, $client), function ($id) use ($app, $client) {

		$params = $app->request->params();

		$response = $client->patch("goals/".$id, array(), $params)->send();
		$response = json_decode($response->getBody(true));

		if($response->status===true){
			$app->flash("success", "Goal updated");
			$app->redirect("/goals");
		} else {
			$app->flash("error", "Goal not updated check form");
			$app->redirect("/goals/".$id);
		}
	});


	/**
	*  REPORTS 
	*/

	$app->get('/activity/report', $authCheck($app, $client), function () use ($app) {
	    $app->redirect('/activity/report/by/day');
	});


	$app->get('/activity/report/by/day', $authCheck($app, $client), function () use ($app, $client) {

		$response = $client->get("/activity/report/by/day")->send();
		$response = json_decode($response->getBody(true), true);

	    $app->render('partials/activity_report_by_day.html.twig', array(
	    	"section"=>"/reports",
	    	"report"=>$response['data'],
	    	"user" => $_SESSION['user']
    	));

	});


	/**
	*  ACCOUNT 
	*/
	$app->get('/account/social', $authCheck($app, $client), function () use ($app, $client, $instagramClient) {

		$response = $client->get("/user/social")->send();
		$response = json_decode($response->getBody(true));

		if(count($response->data->instagram)>0){
			foreach($response->data->instagram as $instagram){
				$instagramClient->setAccessToken($instagram->access_token);
				$i = $instagramClient->getUser();
				// print_r($i->data);
				$instagram->_data = $i->data;
			}
		}
	    $app->render('partials/account_social.html.twig', array(
	    	"section"=>"/account",
	    	"social"=>$response->data,
	    	"instagram_login_url" => $instagramClient->getLoginUrl(),
	    	"user" => $_SESSION['user']
    	));
	});

	$app->post('/account/social/delete', $authCheck($app, $client), function () use ($app, $client, $instagramClient) {

		$request = $client->delete("/user/social/delete");
		$request->getQuery()->set('id', $app->request->params('id'));
		$request->getQuery()->set('type', $app->request->params('type'));
		$response = $request->send();
		$response = json_decode($response->getBody(true));
		$app->flash("success", "Account removed");
		$app->redirect("/account/social");
	});



	/**
	*  OAUTH CALLBACK 
	*/
	$app->get('/callback/oauth/instagram', function () use ($app, $client, $instagramClient) {
		
		if(!isset($_GET['code'])){
			$app->flash("error", "Instagram not connected");
			$app->redirect("/account/social");
		}
		$data = $instagramClient->getOAuthToken($_GET['code']);

		if(!isset($data->access_token)){
			$app->flash("error", "Instagram not connected");
			$app->redirect("/account/social");
		}

		//post to create the new instagram
		try {
			$response = $client->post("/user/instagram", array(), array(
				"access_token" => $data->access_token,
				"instagram_user_id" => $data->user->id,
				"username" => $data->user->username,
				"profile_picture" => $data->user->profile_picture

			))->send();
		} catch (\Exception $e) {
			$app->flash("error", "Instagram connected to another user");
			$app->redirect("/account/social");
		}

		$response = json_decode($response->getBody(true));

		$app->flash("success", "Instagram user ". $data->user->username ." connected");
		$app->redirect("/account/social");

	});

	$app->get('/callback/oauth/google', function () use ($app, $client, $googleClient) {

		global $configs;

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
		       "auth_token" => $configs['app_auth_token']
	       	)
	    );

		$request = $client->post("/user", array(), $params);
		$response = $request->send();
		$response = json_decode($response->getBody(true));

		// echo "<pre>"; print_r($response); echo "</pre>"; die();

		$_SESSION['user'] = $response->data;
		setcookie("auth_token", $_SESSION['user']->auth_token, time()+60*60*24*30, "/", $configs['cookie.domain'], false, true);
		$_COOKIE['auth_token'] = $_SESSION['user']->auth_token;

		// add flash message
		if(isset($_SESSION['user']->is_new)){
			$app->flash("success", "Account created");
		} else {
			$app->flash("success", "Logged in");
		}

		//create new activity
		if(isset($_SESSION['user']->is_new)){
			$app->redirect("/activity/type/add");
		} else {
			$app->redirect("/");
		}

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