<?php 

// TESTED WITH PUBLIC REPOSITORY
require_once 'lib.php';

require_once 'mapper.php';
//print_r($mapper);


define('GH_USERNAME','XXX');
define('GH_PASSWORD','XXX');

define('GH_ORG', 'readybytes');
define('GH_REPO','xxxx');
define('GH_OWNER','readybytes');

define('GH_URL', 'https://api.github.com/repos/'.GH_OWNER.'/'.GH_REPO);






$data = csv_to_array('tickets.csv');

//populate data
$github_database=array();
$github_database = github_api_populate($github_database);

file_put_contents('github_database.txt',var_export($github_database,true));
//print_r($github_database['issues']);
//echo "\n****\n Already issues added [".count($github_database['issues'])."] \n";


// Prepare & Create Milestones and Labels in Github if missing
foreach($mapper['milestone'] as $key => $value){

	echo "\n Testing milestone [$value] ...";
	if(isset($github_database['milestones'][$value])==false){
		github_api_create_milestone($value,$github_database);
	}
}

foreach($mapper['label'] as $key => $value){
	echo "\n Testing label [$value] ...";
	if(isset($github_database['labels'][$value])==false){
		github_api_create_label($value,$github_database);
	}
}




// create issues now
foreach($data as $assembla){
	$aNumber = $assembla['number'];
	if(isset($github_database['issues']["$aNumber"])){

		//lets close already created tickets if required.
		$issue = format_issue($assembla, $mapper, $github_database);
		if($issue['state'] != 'open'){
			github_api_close_issue($aNumber, $issue, $github_database);
			continue;
		}

		echo "\n >> skipping : Issue assembla:$aNumber already added as github:".$github_database['issues']["$aNumber"];
		continue;
	}

	echo "\n accidentally came to create issues $aNumber";
	$issue = format_issue($assembla, $mapper, $github_database);
	github_api_create_issue($issue, $github_database);
}


function github_api_populate(&$database)
{
	//  populate milestones
	$url 		= GH_URL.'/milestones';

	$args	= array('state'=>'open');
	$result = github_request_api($url, "GET",  $args);
	foreach($result as $m){
		$database['milestones'][$m->title]=$m->number;
	}

	$args	= array('state'=>'closed');
	$result = github_request_api($url, "GET",  $args);
	foreach($result as $m){
		$database['milestones'][$m->title]=$m->number;
	}

	//populate labels
	$url 		= GH_URL.'/labels';

	$args	= array();
	$result = github_request_api($url, "GET",  $args);
	foreach($result as $m){
		$database['labels'][$m->name]=true;
	}

	// populate already created issues (from assembla)
	$url 		= GH_URL.'/issues';
	$database['issues']=array();
	// infinite loop
	for($i=1; $i<=9999999; $i++){
		$args	= array();
		$url 	= GH_URL.'/issues'."?page=$i&per_page=100";
		$result = github_request_api($url, "GET",  $args);
		
		foreach($result as $m){
			@list($title, $aNumber) = explode(':ASSEMBLA#',$m->title);
			if(isset($aNumber) && !empty($aNumber)){
				if(isset($database['issues']["$aNumber"])){
					echo "\n Duplicate Isses assembla:$aNumber github:{$m->number}";
				}
				$database['issues']["$aNumber"]=$m->number;
			}
		}

		// at a time returns 30, so call again if 30 else stop.
		if(count($result)< 100){
			break;
		}

		echo "\n >> page $i : rcvd issues : ".count($result)." Total: ".count($database['issues']);
	}

	return $database;
}

function github_api_create_milestone($name,&$database)
{
	echo "\n creating milestone [$name] ...";
	//  populate milestones
	$url 		= GH_URL.'/milestones';

	$args	= array('title'=>$name,'state'=>'open');
	$result = github_request_api($url, "POST",  $args);
	$database['milestones'][$result->title]=$result->number;
}

function github_api_create_label($name, &$database)
{
	echo "\n creating label [$name] ...";
	//  populate milestones
	$url 		= GH_URL.'/labels';

	$args	= array('name'=>$name);
	$result = github_request_api($url, "POST",  $args);
	$database['labels'][$result->name]=true;
}

function github_api_create_issue($data, &$database)
{
	echo "\n creating issue ...";
	$url 		= GH_URL.'/issues';

	$args	= $data ; //json_encode($data);
	$result = github_request_api($url, "POST",  $args);
}

function github_api_close_issue($aNumber, $data, $github_database)
{
	$gNumber = $github_database['issues']["$aNumber"];
	echo "\n Assembla: $aNumber Closing issue ...";
	$url 		= GH_URL.'/issues/'.$gNumber;

	// update state, as during creation state will always open
	$args	= array('state'=>'closed');
	$result = github_request_api($url, "PATCH",  $args);
}

	
function format_issue($assembla,$mapper, &$database)
{
	// get mapping items
	$map_assignee	=	$mapper['assignee'];
	$map_label	 	=	$mapper['label'];
	$map_milestone	=	$mapper['milestone'];
	$map_state		=	$mapper['state'];

	// build assembla vars
	//"number","summary","milestone","reporter","assigned_to","priority","status","description","Type"
	extract($assembla);


	$github		  			=  array();
	$github['title']  		=  $assembla['summary']." :ASSEMBLA#$number";
	
	// only set, if not empty
	if(isset($assembla['description']) && !empty($assembla['description'])){
		$github['body']  		=  $assembla['description'];
	}

	//###### 1. ASSIGNED_TO maps to ASSIGNEE
	if($map_assignee[$assigned_to]){
		$github['assignee'] =  $map_assignee[$assigned_to];
	}else{
		// assign to default
		$github['assignee'] =  get_last_value($map_assignee);
	}

	//###### 2. TYPE maps to LABEL
	if(isset($map_label[$Type])){
		$github['labels'][]		=  $map_label[$Type];
	}


	//###### 3. MILESTONE maps to MILESTONE
	if(isset($map_milestone[$milestone])){
		$milestone = $map_milestone[$milestone];
	}else{
		$milestone = get_last_value($map_milestone);
	}

	//find milestone number
	if(isset($database['milestones'][$milestone])){
		$github['milestone']	=  $database['milestones'][$milestone]; 
	}else{
		echo " ERROR: milestone : $milestone not found in database";
		exit;
	}


	//###### 4. STATUS maps to STATE 
	if(isset($map_state[$status]))
	{
		$github['state']	=  $map_state[$status];
	}else{
		//default is open
		$github['state'] = 'open';
	}


	//#### 5. Using multiple labels
	// STATUS might need to be added as LABEL, check if we can
	if(isset($map_label['state:'.$status])){
		echo "\n >> \t applying label (".$map_label['state:'.$status].")";
		$github['labels'][]=  $map_label['state:'.$status];
	}

	// MILESTONE might need to be added as LABEL, check if we can
	if(isset($map_label['milestone:'.$milestone])){
		echo "\n >> \t applying label (". $map_label['milestone:'.$milestone] .")";
		$github['labels'][]=  $map_label['milestone:'.$milestone];
	}

/*//
	//unset empty values
	foreach ($github as $key => $param){
		if(empty($param)){
			unset($github[$key]);
		}
	}

//*/

	return $github;
}


function get_last_value($arr)
{
	if(is_array($arr)){
		return array_pop(array_values($arr));
	}

	echo " You were querying last value of NOT AN ARRAY : ".var_export($arr,true);
	exit;
}


function github_request_api($url, $method, $data=array()) 
{		
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
	    
	    $data=json_encode($data);
	    if($method == "POST" || $method == "PATCH"){
	    	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	    }elseif($method=='GET'){
			$url += '?';
			if(is_array($data) && count($data)>0){
				foreach($data as $key => $value){
					$url += "$key=$value&";
				}
			}
	    }
	    
	    curl_setopt($ch, CURLOPT_HEADER, true);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
	    curl_setopt($ch, CURLOPT_USERPWD, GH_USERNAME.':'.GH_PASSWORD);
	    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);   
	    $response = curl_exec($ch);
	    
	    	$header_size 	 = curl_getinfo($ch,CURLINFO_HEADER_SIZE);
      	    $result['header'] 	 = substr($response, 0, $header_size);
      	    $result['body'] 	 = substr( $response, $header_size );
      	    $result['http_code'] = curl_getinfo($ch,CURLINFO_HTTP_CODE);
       	    $result['last_url']  = curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);
       	
            curl_close($ch);

		// Error out one response
		if(strpos($result['header'],'HTTP/1.1 400') !== false || strpos($result['header'],'HTTP/1.1 422') !== false)
		{
			echo "\n\n\n Error Occured : ";
			echo "\n -------- \n ";
			echo var_export($result['body'], true);
			echo "\n -------- \n ";
			echo "\n --------REQUESTED DATA WAS \n ";
			echo var_export($data, true);
			echo "\n -------- \n ";
			echo $undefined_variable;
			die;
		}

		$return = json_decode($result['body']);

	    return $return;
}

