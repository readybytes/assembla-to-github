<?php 

// add configs
require_once 'config.php';

// load functions
require_once 'lib.php';

// load mapping (should be generated before running this script)
require_once MAPPER_FILE;

//populate github repo's database 
$github_database=array();
$github_database = github_api_populate($github_database);

// 1: Check & Create Milestones in Github if missing
foreach($mapper['milestone'] as $key => $value){
	echo "\n Checking milestone [$value] ...";
	if(isset($github_database['milestones'][$value])==false){
		github_api_create_milestone($value,$github_database);
	}
}

// 2: Check & Create Labels in Github if missing
foreach($mapper['label'] as $key => $value){
	echo "\n Testing label [$value] ...";
	if(isset($github_database['labels'][$value])==false){
		github_api_create_label($value,$github_database);
	}else{
		echo " [Y] already created.";
	}
}

// get all tickets
$tickets = csv_to_array(TICKET_FILE_CSV);

// 3: Check & Create issues now
foreach($tickets as $aTicket){
	$aNumber = $aTicket['number'];
	$gIssue = format_issue($aTicket, $mapper, $github_database);

	// github-issue already created for this assembla-ticket
	if(isset($github_database['issues']["$aNumber"])){
		echo "\n >> Skip : aTicket:$aNumber in github:".$github_database['issues']["$aNumber"];
		continue;
	}

	//$issue = format_issue($aTicket, $mapper, $github_database);
	github_api_create_issue($aNumber, $gIssue, $github_database);
}

echo "Successfully Imported Tickets. \n Enjoy";
exit;
	
function format_issue($assembla,$mapper, &$database)
{
	// get mapping items
	$map_assignee	=	$mapper['assignees'];
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
	$assigned_to = $assembla['assigned_to'];
	if($map_assignee[$assigned_to]){
		$github['assignee'] =  $map_assignee[$assigned_to];
	}else{
		// assign to default
		$github['assignee'] =  get_last_value($map_assignee);
	}

	//###### 2. TYPE maps to LABEL
	$Type = $assembla['Type'];
	if(isset($map_label[$Type])){
		$github['labels'][]		=  $map_label[$Type];
	}


	//###### 3. MILESTONE maps to MILESTONE
	$milestone = $assembla['milestone'];
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
		die;
	}


	//###### 4. STATUS maps to STATE 
	$github['state'] = 'open';
	$status = $assembla['status'];
	if(isset($map_state[$status]))
	{
		$github['state']	=  $map_state[$status];
	}

	//#### 5. Using multiple labels
	// STATUS might need to be added as LABEL, check if we can
	if(isset($map_label['state:'.$status])){
		//echo "\n >> Adding label (".$map_label['state:'.$status].")";
		$github['labels'][]=  $map_label['state:'.$status];
	}

	// MILESTONE might need to be added as LABEL, check if we can
	if(isset($map_label['milestone:'.$milestone])){
		//echo "\n >> \t applying label (". $map_label['milestone:'.$milestone] .")";
		$github['labels'][]=  $map_label['milestone:'.$milestone];
	}

	return $github;
}


function get_last_value($arr)
{
	if(is_array($arr)){
		return array_pop(array_values($arr));
	}

	echo " You were querying last value of NOT AN ARRAY : ".var_export($arr,true);
	die;
}
