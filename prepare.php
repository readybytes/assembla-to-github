<?php 

require_once 'lib.php';


$data = csv_to_array('tickets.csv');

/**
Array
(
    [number] => 1969
    [summary] => Show PayPlan's Icon(Dashboard, Cofigurations, App Manager) on Joomla Control Panel.
    [milestone] => 
    [reporter] => Jitendra Khatri
    [assigned_to] => 
    [priority] => Highest
    [status] => Fixed
    [description] => 
    [Type] => Feature
)

**/
$keys = array( 
			'assigned_to' 	=> 'assignees',
			'reporter' 		=> 'assignees',
			'milestone' 	=> 'milestone',
			'status'		=> 'state',
			'Type'			=> 'label',
			'priority'		=> 'label'
		);

$results = array();

$results['assignees'] = array();
$results['milestone'] = array();
$results['state'] = array();
$results['label']=array();

$i=0;
foreach($data as $record){

	foreach($keys as $key=>$to){
		if(isset($record[$key])){
			$results[$to][]=$record[$key];
		}
	}

//	if($i++ > 10) break;
}

$file = array();
foreach($results as $key => $result){
	$tmp = (array_values(array_unique($result)));
	sort($tmp);
	$file[$key] = array_flip($tmp);
}

file_put_contents('mapper.php', var_export($file,true));

