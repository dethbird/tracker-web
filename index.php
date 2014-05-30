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
	ini_set('error_reporting', E_ALL);
	ini_set('display_errors', 1);
	define("APPLICATION_PATH", __DIR__);
	date_default_timezone_set('America/Los_Angeles');

	// Ensure src/ is on include_path
	set_include_path(implode(PATH_SEPARATOR, array(
		__DIR__ ,
	    __DIR__ . '/src',
	    get_include_path(),
	)));
	global $api_key, $api_url, $user_id, $client;
	// $api_key = "c4ca4238a0b923820dcc509a6f75849b";
	$user_id = 1;
	$api_url = "http://tracker-api.rishisatsangi.com";
	$siteData = null;

	/**
	* __________               __                                
	* \______   \ ____   _____/  |_  __________________  ______  
 	* |    |  _//  _ \ /  _ \   __\/  ___/\_  __ \__  \ \____ \ 
 	* |    |   (  <_> |  <_> )  |  \___ \  |  | \// __ \|  |_> >
 	* |______  /\____/ \____/|__| /____  > |__|  (____  /   __/ 
	*         \/                        \/             \/|__|    
	*/
	
	require 'vendor/autoload.php';

	use Guzzle\Http\Client;

	$client = new Client($api_url, array(
	    "request.options" => array(
	       "headers" => array("user_id" => $user_id)
	    )
	));

	class AcmeExtension extends \Twig_Extension
	{
	    public function getFilters()
	    {
	        return array(
	            new \Twig_SimpleFilter('print_r', array($this, 'print_r')),
	            new \Twig_SimpleFilter('date_format', array($this, 'date_format')),
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
	}

	$app = new \Slim\Slim(array(
    	'view' => new Slim\Views\Twig(),
    	'templates.path' => __DIR__ . '/view',
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

	$app->get('/', function () use ($app, $siteData) {
	    $app->redirect('/activity/add');
	});


	/**
	*  ACTIVITY
	*/
	$app->get('/activity/log', function () use ($app, $client) {

		$response = $client->get("/activity/log")->send();
		$response = json_decode($response->getBody(true));

	    $app->render('partials/activity_log.html.twig', array(
	    	"section"=>$app->environment()->offsetGet("PATH_INFO"),
	    	"activity"=>$response->data
    	));

	});

	//add new log entry form
	$app->get('/activity/add', function () use ($app, $client) {

		$typeResponse = json_decode($client->get("activity/type")->send()->getBody(true));
	    $app->render('partials/activity_form.html.twig', array(
	    	"section"=>$app->environment()->offsetGet("PATH_INFO"),
	    	"types" => $typeResponse->data
    	));
	});

	// create an activity
	$app->post('/activity/add', function () use ($app, $client) {

		$response = $client->post("activity", array(), $app->request->params())->send();
		$response = json_decode($response->getBody(true));

		if($response->status===true){
			$app->redirect("/activity/log");
		} else {
			$app->redirect("/activity/add");
		}

	});

	//list activity types
	$app->get('/activity/type', function () use ($app, $client) {

		$typeResponse = json_decode($client->get("activity/type")->send()->getBody(true));
		// print_R($typeResponse);
	    $app->render('partials/activity_type_list.html.twig', array(
	    	"section"=>$app->environment()->offsetGet("PATH_INFO"),
	    	"types" => $typeResponse->data
    	));
	});

	//list activity types
	$app->get('/activity/type/add', function () use ($app, $client) {

	    $app->render('partials/activity_type_form.html.twig', array(
	    	"section"=>"/activity/type"
    	));
	});

	//list activity types
	$app->post('/activity/type/add', function () use ($app, $client) {

		var_dump($app->request->params());
		// die();

		$response = $client->post("activity/type", array(), $app->request->params())->send();
		$response = json_decode($response->getBody(true));

		if($response->status===true){
			$app->redirect("/activity/type");
		} else {
			$app->redirect("/activity/type/add");
		}
	});

	/**
	*  ACTIVITY TYPE
	*/

	$app->get('/activity/type', function () use ($app, $siteData) {
	    $app->render('partials/activity_type.html.twig', array());
	});

	$app->get('/activity/type/add', function () use ($app, $siteData) {
	    $app->render('partials/activity_type_form.html.twig', array());
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