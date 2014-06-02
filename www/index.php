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
	define("APPLICATION_PATH", __DIR__ . "/..");
	date_default_timezone_set('America/Los_Angeles');

	//read env file
	// # just points to environment config yml
	$env = parse_ini_file("../env.ini");
	$configs = parse_ini_file($env['config_file']);

	global $api_url, $user_id, $client;
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
	    	return '<span class="label label-'.($activity_type['polarity']>0?"success":"danger").'">'.$activity_type['name'].'</span>';
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

	$app->get('/', function () use ($app) {
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
			$app->redirect("/activity/report");
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
		$app->redirect("/activity/report");

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

	//add activity type form
	$app->get('/activity/type/add', function () use ($app, $client) {

	    $app->render('partials/activity_type_form.html.twig', array(
	    	"section"=>"/activity/type"
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
	    	"type" => $response->data[0]
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
	    	"report"=>$response['data']
    	));

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