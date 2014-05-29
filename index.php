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
	global $api_key, $api_url;
	$api_key = "c4ca4238a0b923820dcc509a6f75849b";
	$api_url = "http://artistcontrolbox.com/api";
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

	class AcmeExtension extends \Twig_Extension
	{
	    public function getFilters()
	    {
	        return array(
	            new \Twig_SimpleFilter('resizeImage', array($this, 'resizeImage')),
	            new \Twig_SimpleFilter('print_r', array($this, 'print_r')),
	        );
	    }

	    public function resizeImage($url, $width, $height)
	    {
	        $url = parse_url($url);

	        return $url['scheme'] . "://" . $url['host'] . "/w". $width . "-h" . $height . $url['path'];
	    }

	    public function print_r($output)
	    {
	        return print_r($output,1);
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



	// load site date for the menu
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $api_url."?api_key=".$api_key); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
 	$siteData = json_decode(curl_exec($ch), true);
 	curl_close($ch);

 	function fetchData($endpoint, $id){
 		global $api_key, $api_url;
 		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $api_url."/".$endpoint."?id=".$id."&api_key=".$api_key);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	 	$data = json_decode(curl_exec($ch), true); 
	 	curl_close($ch);
	 	return $data[0];
 	}


	/**
	* __________               __  .__                
	* \______   \ ____  __ ___/  |_|__| ____    ____  
 	* |       _//  _ \|  |  \   __\  |/    \  / ___\ 
 	* |    |   (  <_> )  |  /|  | |  |   |  \/ /_/  >
 	* |____|_  /\____/|____/ |__| |__|___|  /\___  / 
	*         \/                           \//_____/  	
	*/

	$app->get('/', function () use ($app, $siteData) {
	    // $app->render('partials/home.html.twig', array('siteData' => $siteData));
	    $app->redirect('/activity/1');
	});

	$app->get('/activity/:id', function ($id) use ($app, $siteData) {
		// echo $id;
	    $app->render('partials/activity.html.twig', array('siteData' => $siteData, 'data'=>fetchData("activity", $id), 'section'=>'art'));
	});

	$app->get('/activity/list', function ($id) use ($app, $siteData) {
	    $app->render('partials/activity.html.twig', array('siteData' => $siteData, 'data'=>fetchData("activity", $id), 'section'=>'art'));
	});


	// $app->get('/contents/:id', function ($id) use ($app, $siteData) {
	//     $app->render('partials/content.html.twig', array('siteData' => $siteData, 'data'=>fetchData("contents", $id), 'section'=>'art'));
	// });

	// $app->get('/comics/:id', function ($id) use ($app, $siteData) {
	//     $app->render('partials/title.html.twig', array('siteData' => $siteData, 'data'=>fetchData("titles", $id), 'section'=>'comics'));
	// });

	// $app->get('/issues/:id', function ($id) use ($app, $siteData) {
	//     $app->render('partials/issue.html.twig', array('siteData' => $siteData, 'data'=>fetchData("issues", $id), 'section'=>'comics'));
	// });

	// $app->get('/blogs/:id', function ($id) use ($app, $siteData) {
	//     $app->render('partials/feed.html.twig', array('siteData' => $siteData, 'data'=>fetchData("feeds", $id), 'section'=>'blogs'));
	// });



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