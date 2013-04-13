<?php


// reads CSV files 
// return all tickets = array of tickets
function csv_to_array($filename='', $delimiter=',')
{
    if(!file_exists($filename) || !is_readable($filename))
        return FALSE;

    $header = NULL;
    $data = array();
    if (($handle = fopen($filename, 'r')) !== FALSE)
    {
        while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE)
        {
            if(!$header)
                $header = $row;
            else
                $data[] = array_combine($header, $row);
        }
        fclose($handle);
    }
    return $data;
}


// populate given variable with 
// already created milestones/labels/issues in given repo
function github_api_populate(&$database)
{
	//  populate milestones
	$url 		= GH_URL.'/milestones';
	$args	= array('state'=>'open');
	$result = github_request_api($url, "GET",  $args);
	foreach($result as $m){
		if(isset($m->title)){
			$database['milestones'][$m->title]=$m->number;
		}
	}

	$args	= array('state'=>'closed');
	$result = github_request_api($url, "GET",  $args);
	foreach($result as $m){
		if(isset($m->title)){
			$database['milestones'][$m->title]=$m->number;
		}
	}

	//populate labels
	$url 		= GH_URL.'/labels';

	$args	= array();
	$result = github_request_api($url, "GET",  $args);
	foreach($result as $m){
		if(isset($m->name)){
			$database['labels'][$m->name]=true;
		}
	}

	// populate already created issues (from assembla)
	$url 		= GH_URL.'/issues';

	// infinite loop
	for($i=1; $i<=9999999; $i++)
	{
		$args	= array();
		$url 	= GH_URL.'/issues'."?page=$i&per_page=100";
		$result = github_request_api($url, "GET",  $args);
		
		foreach($result as $m)
		{
			@list($title, $aNumber) = explode(':ASSEMBLA#',$m->title);
			// is it a issue generated by us ?
			if(isset($aNumber) && !empty($aNumber))
			{
				if(isset($database['issues']["$aNumber"]))
				{
					// do some work for duplicates Or simply ignore :-)
				}
				$database['issues']["$aNumber"]=$m->number;
			}
		}

		// at a time returns 30, so call again if 30 else stop.
		if(count($result)< 100){
			break;
		}

		echo "\n >> Received page $i #  Issues found : ".count($result)." # Total Issues loaded: ".count($database['issues']);
	}

	return $database;
}

function github_api_create_milestone($name,&$database)
{
	echo "\n Creating milestone [$name] ...";
	$url 	= GH_URL.'/milestones';
	$args	= array('title'=>$name,'state'=>'open');
	$result = github_request_api($url, "POST",  $args);

	// update database
	$database['milestones'][$result->title]=$result->number;
}

function github_api_create_label($name, &$database)
{
	echo "\n creating label [$name] ...";

	$url 		= GH_URL.'/labels';
	$args	= array('name'=>$name);

	$result = github_request_api($url, "POST",  $args);

	// update database
	$database['labels'][$result->name]=true;
}

function github_api_create_issue($aNumber, $data, &$database)
{
	echo "\n Creating issue (assembla id: $aNumber)...";

	$url 	= GH_URL.'/issues';
	$args	= $data ; 
	$result = github_request_api($url, "POST",  $args);
	
	// update database
	$github_database['issues']["$aNumber"]=$result->number;

	// if state is closed, then update issue again.
	if($data['state'] != 'open')
	{
		$gNumber= $result->number;
		$url 	= GH_URL.'/issues/'.$gNumber;
		$args	= array('state'=>'closed');
		$result = github_request_api($url, "PATCH",  $args);
	}

}

function github_request_api($url, $method, $data=array()) 
{		
	    $ch = curl_init();
	    
	    if($method == "POST" || $method == "PATCH"){
	    	$data=json_encode($data);
	    	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	    }elseif($method=='GET'){
			$url .= '?';
			if(is_array($data) && count($data)>0){
				foreach($data as $key => $value){
					$url .= "$key=$value&";
				}
			}
	    }
	    
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
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

