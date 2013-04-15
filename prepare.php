<?php 

require_once 'lib.php';

require_once 'config.php';


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
// mapping keys from ASSEMBLA to GITHUB
$keys = array( 
			'assigned_to' 	=> 'assignees',
			'reporter' 		=> 'assignees',
			'milestone' 	=> 'milestone',
			'status'		=> 'state',
			'Type'			=> 'label',
			'priority'		=> 'label'
		);

// Mapper Array
$results = array();
$results['assignees'] = array();
$results['milestone'] = array();
$results['state'] = array();
$results['label']=array();

$tickets = csv_to_array(TICKET_FILE_CSV);

foreach($tickets as $ticket){
	// map this ticket data
	foreach($keys as $from=>$to){
		if(isset($ticket[$from])){
			$results[$to][]=$ticket[$from];
		}
	}
}

$file = array();
foreach($results as $key => $result){
	$tmp = (array_values(array_unique($result)));
	sort($tmp);
	$file[$key] = array_flip($tmp);
}

$content = var_export($file,true);

$content = '<?php '."\n ".' $mapper = '. $content;

file_put_contents(MAPPER_FILE, $content);

echo "Please update mapper file with your expectation of user/label/milestones. Then only execute import.php";
exit;

