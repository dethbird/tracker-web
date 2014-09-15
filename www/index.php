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
		$session,
		$logger,
		$client,
		$currentUri,
		$googleClient,
		$instagramClient,
		$flickrClient,
		$foursquareClient,
		$githubClient;


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
	require_once '../lib/logger.php';

	use OAuth\OAuth1\Service\Twitter;
	use OAuth\OAuth2\Service\GitHub;
	use OAuth\Common\Storage\Session;
	use OAuth\Common\Consumer\Credentials;
	use OAuth\Common\Http\Uri\UriFactory;
	use Guzzle\Http\Client;
	use GuzzleHttp\Subscriber\Oauth\Oauth1;
	use Google\Client as GoogleClient;
	use Jcroll\FoursquareApiClient\Client\FoursquareClient;

	// Basic functional classes
	$session = new Session();
	$uriFactory = new UriFactory();
	$currentUri = $uriFactory->createFromSuperGlobalArray($_SERVER);
	$oauthServiceFactory = new OAuth\ServiceFactory();


	// Google client
	$googleClient = new Google_Client();
	$googleClient->setClientId($configs['google.client_id']);
	$googleClient->setClientSecret($configs['google.client_secret']);
	$googleClient->setScopes($configs['google.scopes']);
	$googleClient->setRedirectUri($configs['google.redirect_url']);
		
	// HTTP client
	$client = new Client($configs['api_url'], array(
	    "request.options" => array(
	       "headers" => array(
		       "auth_token" => isset($_COOKIE['auth_token']) ? $_COOKIE['auth_token'] : false
	       	)
	    )
	));

	// Instagram Client
	require_once("../vendor/cosenary/instagram/instagram.class.php");
	$instagramClient = new Instagram(array(
      'apiKey'      => $configs['instagram.key'],
      'apiSecret'   => $configs['instagram.secret'],
      'apiCallback' => "http://". $_SERVER['HTTP_HOST'] . '/callback/oauth/instagram'
    ));

	// Flickr Client
	$credentials = new Credentials(
		$configs['flickr.key'],
		$configs['flickr.secret'],
		"http://" . $currentUri->getHost() . "/callback/oauth/flickr"
	);
	$flickrClient = $oauthServiceFactory->createService('Flickr', $credentials, $session);

	// Foursquare Client
	$credentials = new Credentials(
		$configs['foursquare.key'],
		$configs['foursquare.secret'],
		"http://" . $currentUri->getHost() . "/callback/oauth/foursquare"
	);
	$foursquareClient = $oauthServiceFactory->createService('Foursquare', $credentials, $session);

	//Github Oauth Client
	$credentials = new Credentials(
	    $configs['github.key'],
	    $configs['github.secret'],
	    "http://" . $currentUri->getHost() . "/callback/oauth/github"
	);

	$githubClient = $oauthServiceFactory->createService('GitHub', $credentials, $session, array('user'));


	//Twitter Oauth Client
	$credentials = new Credentials(
	    $configs['twitter.key'],
	    $configs['twitter.secret'],
	    "http://" . $currentUri->getHost() . "/callback/oauth/twitter"
	);

	$twitterClient = $oauthServiceFactory->createService('twitter', $credentials, $session);

	// var_dump($gitHubClient); die();


	// Twig Extension
	class AcmeExtension extends \Twig_Extension
	{
	    public function getFilters()
	    {
	        return array(
	            new \Twig_SimpleFilter('print_r', array($this, 'print_r')),
	            new \Twig_SimpleFilter('date_format', array($this, 'date_format')),
	            new \Twig_SimpleFilter('activity_name_label', array($this, 'activity_name_label')),
	            new \Twig_SimpleFilter('strip_tags', array($this, 'strip_tags')),
	            new \Twig_SimpleFilter('substr', array($this, 'substr'))
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

	    public function strip_tags($html)
	    {
	        return strip_tags($html);
	    }

	    public function substr($output, $from, $to)
	    {
	        return substr($output,$from,$to);
	    }
  

	    public function getName()
	    {
	        return 'acme_extension';
	    }


	    public function activity_name_label($activity_type_id, $acivity_types, $has_goal=null)
	    {
	    	$activity_type = $acivity_types[$activity_type_id];
	    	return '<span class="label" data-polarity-colorize="'.$activity_type['polarity'].'">'.
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

		$response = json_decode($client->get("activity/".$id)->send()->getBody(true));

		$activity = $response->data[0];

	    $app->render('partials/activity_form.html.twig', array(
	    	"section"=>"/activity",
	    	"activity" => $response->data[0],
	    	"user" => $_SESSION['user']
    	));
	});

	// update an activity
	$app->post('/activity/:id', $authCheck($app, $client), function ($id) use ($app, $client) {

		$response = $client->patch("activity/".$id, array(), $app->request->params())->send();

		if($app->request->isAjax()){
			echo $response->getBody(true);
		} else {
			$response = json_decode($response->getBody(true));
			if($response->status===true){
				$app->flash("success", "Activity updated");
				$app->redirect("/activity/report/by/day");
			} else {
				$app->redirect("/activity/add");
			}
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

	$app->get('/report', $authCheck($app, $client), function () use ($app) {

		if($app->request->isAjax()) {
			echo json_encode(array("farts"));
		} else {
		    $app->render('partials/report.twig', array(
		    	"section"=>"/reports",
		    	"report"=>array(),
		    	"user" => $_SESSION['user']
	    	));
		}
	});


	$app->get('/activity/report/by/day', $authCheck($app, $client), function () use ($app, $client) {



		$request = $app->request;
		$params = $request->params();
		// var_dump($params); die();
		if(!isset($params['from_date'])){
			$params['from_date'] = date("Y-m-d", strtotime("+1 day", strtotime(date("Y-m-d"))));
		}
		if(!isset($params['to_date'])){
			$params['to_date'] = date("Y-m-d", strtotime("-7 day", strtotime(date("Y-m-d"))));
		}
		if(isset($params['activity_type_id'])){
			if($params['activity_type_id']==""){
				unset($params['activity_type_id']);
			}
		}

		$request = $client->get("/activity/report/by/day?" . http_build_query($params));
		$response = $request->send();
		$response = json_decode($response->getBody(true), true);

		//get all activity types
		$request = $client->get("/activity/type?system=true");
		$activity_response = $request->send();
		$activity_response = json_decode($activity_response->getBody(true), true);


	    $app->render('partials/activity_report_by_day.html.twig', array(
	    	"section"=>"/reports",
	    	"report"=>$response['data'],
	    	"activities"=>$activity_response['data'],
	    	"user" => $_SESSION['user'],
	    	"filterParams" => $params
    	));

	});

	$app->get('/proxy/:route', $authCheck($app, $client), function ($route) use ($app, $client) {

		$response = $client->get("/" . $route)->send();
		echo $response->getBody(true);

	});	


	/**
	*  ACCOUNT 
	*/
	$app->get('/account/social', $authCheck($app, $client), function () use ($app, $client, $configs, $instagramClient, $foursquareClient, $githubClient, $twitterClient) {

		$response = $client->get("/user/social")->send();

		Logger::log($response->getBody(true));
		$response = json_decode($response->getBody(true));


		if(count($response->data->instagram)>0){
			foreach($response->data->instagram as $instagram){
				$instagramClient->setAccessToken($instagram->access_token);
				$i = $instagramClient->getUser();
				// print_r($i->data);
				// var_dump($i);die();
				$instagram->_data = isset($i->data) ? $i->data : array();
			}
		}
		if(count($response->data->flickr)>0){
			foreach($response->data->flickr as $flickr){

				$metadata = new Rezzza\Flickr\Metadata($configs['flickr.key'], $configs['flickr.secret']);
				$metadata->setOauthAccess($flickr->oauth_token, $flickr->secret);
				$factory  = new Rezzza\Flickr\ApiFactory($metadata, new Rezzza\Flickr\Http\GuzzleAdapter());
				$xml = $factory->call('flickr.people.getInfo', array(
						"user_id" => $flickr->nsid
					)
				);
				// var_dump($xml);die();
				$flickr->_data = json_decode(json_encode((array)$xml->person));
			}
		}
		if(count($response->data->foursquare)>0){
			foreach($response->data->foursquare as $foursquare){
				$client = FoursquareClient::factory(array(
				    'client_id'     => $configs['foursquare.key'],
				    'client_secret' => $configs['foursquare.secret']
				));
				$client->addToken($foursquare->access_token);
				$command = $client->getCommand('users', array("user_id" => $foursquare->foursquare_user_id));
				$result = $command->execute();
				$foursquare->_data = $result['response'];

				// Logger::log($foursquare);
			}
		}

    	if(count($response->data->github)>0){
			foreach($response->data->github as $github){

				// HTTP client
				$c = new Client("https://api.github.com", array(
				    "request.options" => array(
				       "headers" => array(
					       "Authorization" => "token ".$github->access_token
				       	)
				    )
				));

				$r = $c->get('user')->send();
				$github->_data = json_decode($r->getBody(true));
			}
		}

    	if(count($response->data->twitter)>0){
			foreach($response->data->twitter as $twitter){
				// HTTP client
				$c = new \Guzzle\Http\Client('https://api.twitter.com/{version}', array(
			        'version' => '1.1'
			    ));
				$c->addSubscriber(new Guzzle\Plugin\Oauth\OauthPlugin(array(
				    'consumer_key'    => $configs['twitter.key'],
				    'consumer_secret' => $configs['twitter.secret'],
				    'token'           => $twitter->access_token,
				    'token_secret'    => $twitter->access_token_secret,
				)));

				$r = $c->get('account/verify_credentials.json')->send();

				$twitter->_data = json_decode($r->getBody(true));

			}
		}


		//render
	    $app->render('partials/account_social.html.twig', array(
	    	"section"=>"/account",
	    	"social"=>$response->data,
	    	"instagram_login_url" => $instagramClient->getLoginUrl(),
	    	"user" => $_SESSION['user']
    	));

	});

	$app->post('/account/social/delete', $authCheck($app, $client), function () use ($app, $client, $instagramClient) {
		// var_dump($app->request->params()); die();
		$request = $client->delete("/user/social/delete");
		$request->getQuery()->set('id', $app->request->params('id'));
		$request->getQuery()->set('type', $app->request->params('type'));
		$response = $request->send();
		$response = json_decode($response->getBody(true));
		$app->flash("success", "Account removed");
		$app->redirect("/account/social");
	});

	$app->get('/callback/subscribe/instagram/init', $authCheck($app, $client), function () use ($app, $client, $instagramClient) {
		global $configs;

		$params = array(
			"client_id" => $configs['instagram.key'],
			"client_secret" => $configs['instagram.secret'],
			"object" => "user",
			"aspect" => "media",
			"verify_token" => $configs['instagram.verify_token'],
			"callback_url" => "http://". $_SERVER['HTTP_HOST']  . '/callback/subscribe/instagram'
		);

		$response = $client->post("https://api.instagram.com/v1/subscriptions/", array(), $params)->send();
		echo "<pre>";
		print_r($configs['instagram.verify_token']);
		echo "\n";
		print_r(json_decode($response->getBody(true)));
		echo "</pre>";

	});

	$app->get('/callback/subscribe/instagram', function () use ($app, $client, $instagramClient) {
		echo $_GET['hub_challenge'];

	});

	$app->post('/callback/subscribe/instagram', function () use ($app, $client, $instagramClient) {

		$request = json_decode($app->request->getBody(true));

		//throw this to api to process
		$response = $client->post("/social/activity/instagram", array(), array(
			"social_user_id" => $request[0]->object_id,
			"media_id" => $request[0]->data->media_id
		))->send();
	});

	$app->get('/callback/subscribe/flickr', function () use ($app, $client, $instagramClient) {
		// Logger::log($_GET);
		echo $_GET['challenge'];

	});

	$app->post('/callback/subscribe/flickr', function () use ($app, $client, $instagramClient) {

		// echo $xml;
		$xml = $app->request->getBody(true);
		$xml = preg_replace("/[\r\n]+/", "\n", $xml);
		$xml = preg_replace('~(</?|\s)([a-z0-9_]+):~is', '$1$2_', $xml);
		$xml = new SimpleXMLElement($xml);
		// Logger::log($xml);

		$idBurst = explode("/", $xml->entry->id);
		$id = $idBurst[2];

		$nsid = (string) $xml->entry->author->flickr_nsid;

		//throw this to api to process
		$response = $client->post("/social/activity/flickr", array(), array(
			"social_user_id" => $nsid,
			"media_id" => $id
		))->send();

	});	

	$app->get('/callback/subscribe/instagram', function () use ($app, $client, $instagramClient) {
		echo $_GET['hub_challenge'];

	});

	$app->post('/callback/subscribe/foursquare', function () use ($app, $client) {

		$checkin = json_decode($app->request->params('checkin'));

		//throw this to api to process
		$response = $client->post("/social/activity/foursquare", array(), array(
			"social_user_id" => $checkin->user->id,
			"media_id" => $checkin->id
		))->send();

		// Logger::log($response->getBody(true));
	});

	$app->post('/callback/subscribe/github', function () use ($app, $client) {

		$request = json_decode($app->request->getBody(true));

		Logger::log($request);

		//throw this to api to process
		$response = $client->post("/social/activity/github", array(), array(
			"social_user_id" => $request->pusher->name,
			"media_id" => $request->after,
			"json" => $app->request->getBody(true)
		))->send();
	});

	/**
	*  OAUTH AUTH LINK 
	*/
	$app->get('/social/auth/flickr', function () use ($app, $client, $flickrClient) {
		$token = $flickrClient->requestRequestToken();
		$oauth_token = $token->getAccessToken();
		$secret = $token->getAccessTokenSecret();
		$url = $flickrClient->getAuthorizationUri(array('oauth_token' => $oauth_token, 'perms' => 'read'));
		header('Location: '.$url);
	});

	$app->get('/social/auth/foursquare', function () use ($app, $client, $foursquareClient) {
		// Logger::log($foursquareClient);
		$url = $foursquareClient->getAuthorizationUri();
		header('Location: '.$url);
	});

	$app->get('/social/auth/github', function () use ($app, $client, $githubClient) {
		// Logger::log($foursquareClient);
		$url = $githubClient->getAuthorizationUri();
		header('Location: '.$url);
	});

	$app->get('/social/auth/twitter', function () use ($app, $client, $twitterClient) {
		$token = $twitterClient->requestRequestToken();
   		$url = $twitterClient->getAuthorizationUri(array('oauth_token' => $token->getRequestToken()));
		header('Location: '.$url);
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

	$app->get('/callback/oauth/foursquare', function () use ($app, $client, $foursquareClient) {
		
		if(!isset($_GET['code'])){
			$app->flash("error", "Foursquare not connected");
			$app->redirect("/account/social");
		}
		$data = $foursquareClient->requestAccessToken($_GET['code']);


		$accessToken = $data->getAccessToken();

		if(!isset($accessToken)){
			$app->flash("error", "Foursquare not connected");
			$app->redirect("/account/social");
		}

		// Send a request with it
    	$result = $foursquareClient->request('users/self');
    	$result = json_decode($result);

		//post to create the new foursquare
		try {
			$response = $client->post("/user/foursquare", array(), array(
				"access_token" => $accessToken,
				"foursquare_user_id" => $result->response->user->id,
				"username" => $result->response->user->firstName . " " .$result->response->user->lastName,
				"profile_picture" => $result->response->user->photo->prefix . "150x150" . $result->response->user->photo->suffix

			))->send();
		} catch (\Exception $e) {
			$app->flash("error", "Foursquare connected to another user");
			$app->redirect("/account/social");
		}

		$response = json_decode($response->getBody(true));

		$app->flash("success", "Foursquare user ". $result->response->user->firstName . " " .$result->response->user->lastName ." connected");
		$app->redirect("/account/social");

	});

	$app->get('/callback/oauth/flickr', function () use ($app, $client, $currentUri, $configs, $session, $flickrClient) {

		
		if(!isset($_GET['oauth_token']) || !isset($_GET['oauth_verifier'])){
			$app->flash("error", "Flickr not connected");
			$app->redirect("/account/social");
		}

		$requestToken = $session->retrieveAccessToken('Flickr');
		$secret = $requestToken->getAccessTokenSecret();


		$token = $flickrClient->requestAccessToken($_GET['oauth_token'], $_GET['oauth_verifier'], $secret);

		$oauth_token = $token->getAccessToken();
		$secret = $token->getAccessTokenSecret();
		$userInfo = $token->getExtraParams();

		$session->storeAccessToken('Flickr', $token);


		if(!isset($oauth_token)){
			$app->flash("error", "Flickr not connected");
			$app->redirect("/account/social");
		}

		$metadata = new Rezzza\Flickr\Metadata($configs['flickr.key'], $configs['flickr.secret']);
		$metadata->setOauthAccess($oauth_token, $secret);
		$factory  = new Rezzza\Flickr\ApiFactory($metadata, new Rezzza\Flickr\Http\GuzzleAdapter());
		$xml = $factory->call('flickr.people.getInfo', array(
				"user_id" => $userInfo['user_nsid']
			)
		);

		$xml2 = $factory->call('flickr.push.subscribe', array(
				"topic" => 'my_photos',
				"verify" => "sync",
				"callback" => "http://" . $currentUri->getHost() . "/callback/subscribe/flickr"
			)
		);

		$personAttr = array();
		foreach( $xml->person->attributes() as $k=>$v ){
			$personAttr[$k] = (string) $v;
		}
		$personAttr['photosurl'] = (string) $xml->person->photosurl;
		$personAttr['username'] = (string) $xml->person->username;

		//post to create the new flickr
		try {
			$response = $client->post("/user/flickr", array(), array(
				"oauth_token" => $oauth_token,
				"secret" => $secret,
				"username" =>  $personAttr['username'],
				"nsid" => $personAttr['nsid'],
				"iconserver" => $personAttr['iconserver'],
				"iconfarm" => $personAttr['iconfarm'],
				"photosurl" => $personAttr['photosurl']

			))->send();
		} catch (\Exception $e) {
			// var_dump($e); die();
			$app->flash("error", "flickr connected to another user");
			$app->redirect("/account/social");
		}

		$response = json_decode($response->getBody(true));

		$app->flash("success", "flickr user ". $personAttr['username'] ." connected");
		$app->redirect("/account/social");

	});

	$app->get('/callback/oauth/github', function () use ($app, $client, $githubClient) {
		
		if(!isset($_GET['code'])){
			$app->flash("error", "github not connected");
			$app->redirect("/account/social");
		}
		$data = $githubClient->requestAccessToken($_GET['code']);


		$accessToken = $data->getAccessToken();

		if(!isset($accessToken)){
			$app->flash("error", "github not connected");
			$app->redirect("/account/social");
		}

		// Send a request with it
    	$result = $githubClient->request('user');
    	$result = json_decode($result);


		//post to create the new github
		try {
			$response = $client->post("/user/github", array(), array(
				"access_token" => $accessToken,
				"github_user_id" => $result->id,
				"username" => $result->login
			))->send();
		} catch (\Exception $e) {
			Logger::log($e->getMessage());
			$app->flash("error", "github connected to another user");
			$app->redirect("/account/social");
		}

		$response = json_decode($response->getBody(true));


		$app->flash("success", "github user ". $result->login ." connected");
		$app->redirect("/account/social");

	});

	$app->get('/callback/oauth/twitter', function () use ($app, $client, $twitterClient, $session) {
		
		if(!isset($_GET['oauth_token'])){
			$app->flash("error", "twitter not connected");
			$app->redirect("/account/social");
		}

	    $token = $session->retrieveAccessToken('Twitter');

	    // This was a callback request from twitter, get the token
	    $t = $twitterClient->requestAccessToken(
	        $_GET['oauth_token'],
	        $_GET['oauth_verifier'],
	        $token->getRequestTokenSecret()
	    );


		if(!isset($t)){
			$app->flash("error", "twitter not connected");
			$app->redirect("/account/social");
		}

		// Send a request with it
    	$result =  $result = json_decode($twitterClient->request('account/verify_credentials.json'));

		//post to create the new twitter
		try {
			$response = $client->post("/user/twitter", array(), array(
				"access_token" => $t->getAccessToken(),
				"access_token_secret" => $t->getAccessTokenSecret(),
				"twitter_user_id" => $result->id,
				"username" => $result->name
			))->send();
		} catch (\Exception $e) {
			Logger::log($e->getMessage());
			$app->flash("error", "twitter connected to another user");
			$app->redirect("/account/social");
		}
		$response = json_decode($response->getBody(true));


		$app->flash("success", "twitter user ". $result->name ." connected");
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