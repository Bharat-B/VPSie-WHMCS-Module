
<?php

function vpsie_authCall($params = array()){
    $vpsie['grand_type'] = 'bearer';
    $vpsie['client_id'] = ""; // API key
    $vpsie['client_secret'] = ""; // API pass
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_REFERER, $_SERVER ['HTTP_HOST']);
    curl_setopt($curl, CURLOPT_URL, 'https://api.vpsie.com/v1/token');
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 300);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);   
    $json = curl_exec($curl);
    $result = json_decode($json,true);
    return $result;
}

function vpsie_Call($call,$params,$method){
    $k = vpsie_authCall();
    $header = array();
    $header[] = 'Authorization: Bearer '.$k['token']['access_token'];
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_HTTPHEADER,$header);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    if($method == 'GET') {
        foreach($params as $value){
            $call .='/'.$value;
        }
    } 
    if($method == 'DELETE'){
   	curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");	
    } 
    IF($method == 'POST') {
	curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    curl_setopt($curl, CURLOPT_URL, $call); 
    $json =  curl_exec($curl);
    $result = json_decode($json,true);
    return $result;
    curl_close($curl);
}

function vpsie_ConfigOptions() {

	# Should return an array of the module options for each product - maximum of 24

    $configarray = array(
	 "Offer ID" => array( "Type" => "text", "Size" => "35")
	);

	return $configarray;

}

function vpsie_CreateAccount($params) {
    # ** The variables listed below are passed into all module functions **
    $serviceid = $params["serviceid"]; # Unique ID of the product/service in the WHMCS Database
    $pid = $params["pid"]; # Product/Service ID
    $producttype = $params["producttype"]; # Product Type: hostingaccount, reselleraccount, server or other
    $domain = $params["domain"];
    $offerid = $params['configoption1'];
    $clientsdetails = $params["clientsdetails"]; # Array of clients details - firstname, lastname, email, country, etc...
    $osid = $params["configoptions"]['OS'] ? $params["configoptions"]['OS'] : $params["customfields"]['OS']; # Array of custom field values or configurable optons for the product
    $dcid = $params["configoptions"]['DC'] ? $params["configoptions"]['DC'] : $params["customfields"]['DC']; # Array of custom field values or configrable options for the product
    $call = 'https://api.vpsie.com/v1/vpsie';
    $vparams = array(
    	"offer_id" => $offerid,
    	"hostname" => $domain,
    	"datacenter_id" => $dcid,
    	"os_id" => $osid
    );

    $json = vpsie_Call($call,$vparams,'POST');
	if($json['error'] != true){
	// vpsid of vpsie
		$one = select_query("tblcustomfields","id",array("relid"=>$pid, "fieldname"=>'vpsid'));
        	$oneres = mysql_fetch_array($one);
	        if($one){
        	    update_query("tblcustomfieldsvalues",array("value" => $json['vpsie_id']),array("relid" => $serviceid, "fieldid" => $oneres['id']));
	            update_query("tblhosting",array("dedicatedip"=>$json['ipv4'],"domain" => $json['name'],"username"=>'root'),array("id"=>$serviceid));
	        }
	        $two = select_query("tblcustomfields","id",array("relid" => $pid, "fieldname" => 'region'));
        	$twores = mysql_fetch_array($two);
	        if($two){
        	    update_query("tblcustomfieldsvalues",array("value" => $json['region']),array("relid" => $serviceid, "fieldid" => $twores['id']));
                $three = select_query("tblcustomfields","id",array("relid" => $pid, "fieldname" => 'vpspassword'));
                $threeres = mysql_fetch_array($three);
				if($three){
					update_query("tblhosting",array("password" => encrypt($json['password'])),array("id"=>$serviceid));
        		}
			}
	        $result = 'success';
        } else {
            $result = $json['response'];
        }
	logModuleCall('vpsie','create',$result,json_encode($json),'','');
	return $result;
}
function vpsie_AddPTR($params){
	$call = "https://api.vpsie.com/v1/ptr/record";
	$vparams = array( "ip" => $params['dedicatedip'] );
	$json = vpsie_call($call,$vparams,'POST');
	if(!$json['error']){
		$result = 'success';
	}else{
		$result = $json['errorCode'];	
	}
	logModuleCall('vpsie','ptr',$result,json_encode($json),'','');
	return $result;
	
}
function vpsie_TerminateAccount($params) {
	$call = "https://api.vpsie.com/v1/vpsie/".$params['customfields']['vpsid'];
	$vparams = array();
	$serviceid = $params["serviceid"];
	$json = vpsie_Call($call,$vparams,'DELETE');
        if (!$json['error']) {
		// vpsid of virtualizor
		$query = select_query("tblcustomfields","id",array("relid" =>$params["pid"],"fieldname" => 'vpsid'));
		$res = mysql_fetch_array($query);
		update_query("tblcustomfieldsvalues", array("value" => ''), array("relid" => $serviceid, "fieldid" => $res['id']));
		// The Dedicated IP
		update_query("tblhosting",array("dedicatedip" => '',"assignedips" => ''),array("id" => $serviceid));
		$result = "success";
	} else {
		$result = $json['errorCode'];
	}
	logModuleCall('vpsie','delete',$result,json_encode($json),'','');
        return $result;
}


function vpsie_ChangePassword($params) {
	$call = "https://api.vpsie.com/v1/vpsie/password/".$params['customfields']['vpsid'];
	$vparams = array ();
	$serviceid = $params["serviceid"];
	$json = vpsie_Call($call,$vparams,'POST');
        if (!$json['error']) {
		update_query("tblhosting",array("password" => encrypt($json['password'])),array("id" => $serviceid));
		$result = "success";
	} else {
		$result = $json['errorCode'];
	}
	logModuleCall('vpsie','Password Reset',$result,json_encode($json),'','');
	return $result;
}
function vpsie_Rebuild($params){
	$call = "https://api.vpsie.com/v1/vpsie/rebuild/".$params['customfields']['vpsid'];
	$vparams = array ();
	$serviceid = $params["serviceid"];
	$json = vpsie_Call($call,$vparams,'POST');
    	if (!$json['error']) {
		$result = "success";
	} else {
		$result = $json['errorCode'];
	}
	logModuleCall('vpsie','Rebuilt',$result,json_encode($json),'','');
	return $result;
}
function vpsie_Record($params){
	$one = select_query("tblhosting","dedicatedip",array("id" =>$params["serviceid"]));
	$oneres = mysql_fetch_assoc($one);
        $call = "https://api.vpsie.com/v1/ptr/record";
        $vparams = array ( "ip" => $oneres['dedicatedip'] );
        $json = vpsie_Call($call,$vparams,'POST');
        if (!$json['error']) {
                $result = "success";
        } else {
                $result = $params['dedicatedip'];
        }
        logModuleCall('vpsie','PTR',$result,json_encode($json),'','');
        return $result;
}

function vpsie_ChangePackage($params) {

	$call = "https://api.vpsie.com/v1/vpsie/resize/".$params['customfields']['vpsid'];
	$vparams = array (
		"cpu" => $params['customfields']['cpu'],
		"ram" => $params['customfields']['ram'],
		"ssd" => $params['customfields']['ssd']
	);
	$serviceid = $params["serviceid"];
	$json = vpsie_Call($call,$vparams,'POST');
    if (!$json['error']) {
		$result = "success";
	} else {
		$result = $json['errorCode'];
	}
	logModuleCall('vpsie','Resize',$result,json_encode($json),'','');
	return $result;
}
function vpsie_ClientArea($params) {

    # Output can be returned like this, or defined via a clientarea.tpl template file (see docs for more info)
$call = 'https://api.vpsie.com/v1/vpsie/'.$params['customfields']['vpsid'];
$vparams = array();
$json = vpsie_Call($call,$vparams,'GET');
$json = $json['vpsie'];
$status = ( $json['status'] === 'Running' ? true : false ) ? ( '#167a18' ) : ( '#d60000' );
$code = '
	<style type="text/css">
	    .viaction:hover{
	        cursor: pointer;
	        color: #000;
	    }

		table.table{
		}
	</style>
	<div id="overlay"></div>
	<table class="table">
	<tr><td>Status: <span style="color:'.$status.';font-weight:bold;">'.$json['status'].'</span></td></tr>
	<tr><td>Dedicated IP: '.$json['public_ip'].'</td></tr>
	<tr><td>Root Password: '.$params['password'].'</td></tr>
	<tr><td>Operating System: '.$json['os_slug'].'</td></tr>
	<tr><td>Region: '.$json['region'].'</td></tr>
	</table>';
$code .= '<table class="table buttons">
	<tr>
	<td><a class="viaction btn btn-primary" va="shutdown">Power off</a></td>
	<td><a class="viaction btn btn-primary" va="reboot">Reboot</a></td>
	<td><a class="viaction btn btn-primary" va="ChangePassword">Reset Password</a></td>
	<td><a class="viaction btn btn-primary" va="Rebuild">Rebuild</a></td>
	</tr>
	<tr>
	<td><a class="viaction btn btn-primary" va="Record">Add PTR Record</a></td>
	</tr>
	</table>';
$call = 'https://api.vpsie.com/v1/vpsie/statistics/'.$params['customfields']['vpsid'];
$vparams = array(); 
$json = vpsie_Call($call,$vparams,'POST');
$code .= '
		<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/c3/0.4.10/c3.min.css">
		<script src="https://cdnjs.cloudflare.com/ajax/libs/d3/3.5.5/d3.min.js" type="text/javascript"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/c3/0.4.10/c3.min.js" type="text/javascript"></script>
        <script type="text/javascript">
        	$(\'a.viaction\').click(function(e){
        		e.preventDefault();
        		$(".buttons").children().attr("disabled","disabled");
        		$(".buttons").css("opacity","0.4");
        		var action = $(this).attr(\'va\');
        		console.log(action);
        		$.post("clientarea.php?action=productdetails&id=' . $params['serviceid'] . '&modop=custom&a="+action,function(data){
        			$(".buttons").removeAttr("disabled");
        			$(".buttons").css("opacity","1");
        			window.location.href = window.location.href;
        		});
        		return false;
        	});
			$(document).ready(function(){
				$(\'.viaction\').css("curosr","pointer");
				function makedata(data){
				
					var fdata = [];
					i = 0;
					for (x in data){
						fdata.push([i, (data[x])]);
						i++;
					}
				
					return fdata;
					
				}
				
				';
					
				$code .= '


				var chart = c3.generate({
				    bindto: \'#bwband_holder\',
				    data: {
				        columns: [
				            [\'Upload\', 0,'.implode(', ', $json['graph']['netin']).'],
				            [\'Download\', 0,'.implode(', ', $json['graph']['netout']).']
				        ],
				        types: {
				            data1: \'area\',
				            data2: \'area-spline\'
				        }
				    }
				});
				var chart = c3.generate({
				    bindto: \'#drwband_holder\',
				    data: {
				        columns: [
				            [\'Reads\', 0,'.implode(', ', $json['graph']['diskread']).'],
				            [\'Writes\', 0,'.implode(', ', $json['graph']['diskwrite']).']
				        ],
				        types: {
				            data1: \'area\',
				            data2: \'area-spline\'
				        }
				    }
				});
				var chart = c3.generate({
				    bindto: \'#cpuband_holder\',
				    data: {
				        columns: [
				            [\'Usage\', 0,'.implode(', ', $json['graph']['cpu']).']
				        ],
				        types: {
				            data1: \'area\',
				            data2: \'area-spline\'
				        }
				    }
				});
				var chart = c3.generate({
				    bindto: \'#ramband_holder\',
				    data: {
				        columns: [
				            [\'Usage\', 0,'.implode(', ', $json['graph']['ram']).']
				        ],
				        types: {
				            data1: \'area\',
				            data2: \'area-spline\'
				        }
				    }
				});
				
			});
		</script>
';
$code .= '<table class="table">
		<tr><td><center><h3>CPU Usage Stats</h3><br /><div style="width:500px; height:190px;" id="cpuband_holder"></div></center></td></tr>
		<tr><td><center><h3>RAM Usage Stats</h3><br /><div style="width:500px; height:190px;" id="ramband_holder"></div></center></td></tr>
		<tr><td><center><h3>Bandwidth Stats</h3><br /><div style="width:500px; height:190px;" id="bwband_holder"></div></center></td></tr>
		<tr><td><center><h3>Disk Stats</h3><br /><div style="width:500px; height:190px;" id="drwband_holder"></div></center></td></tr>
	</table>';
	return $code;
}

/*function vpsie_AdminLink($params) {

	$code = '<form action=\"http://'.$params["serverip"].'/controlpanel" method="post" target="_blank">
<input type="hidden" name="user" value="'.$params["serverusername"].'" />
<input type="hidden" name="pass" value="'.$params["serverpassword"].'" />
<input type="submit" value="Login to Control Panel" />
</form>';
	return $code;

}

function vpsie_LoginLink($params) {

	echo "<a href=\"http://".$params["serverip"]."/controlpanel?gotousername=".$params["username"]."\" target=\"_blank\" style=\"color:#cc0000\">login to control panel</a>";

}
*/
function vpsie_start($params){

	$call = 'https://api.vpsie.com/v1/vpsie/start/'.$params;
	$vparams = array();
	$json = vpsie_Call($call,$vparams,'POST');
	if($json['error'] == false && $json['status'] === 'Started') {
		$result = "success";
	} else {
		$result = "Error Message Goes Here...";
	}
	logModuleCall('vpsie','start',$result,json_encode($json),'','');
	return $result;
}
function vpsie_stop($params){
	
	$call = 'https://api.vpsie.com/v1/vpsie/shutdown/'.$params['customfields']['vpsid'];
	$vparams = array();
	$json = vpsie_call($call,$vparams,'POST');
	 if($json['error'] == false && $json['status'] === 'Started') {
                $result = "success";
        } else {
                $result = "Error Message Goes Here...";
        }
	logModuleCall('vpsie','start',$result,json_encode($json),'','');
	return $result;
}
function vpsie_force_reboot($params){
	$call = 'https://api.vpsie.com/v1/vpsie/force/restart/'.$params['customfields']['vpsid'];
	$vparams = array();
	$json = vpsie_Call($call,$vparams,'POST');
	if($json['error'] == false && $json['status'] === 'Restarted') {
		$result = "success";
	} else {
		$result = "Error Message Goes Here...";
	}
	return $result;
}
/*function vpsie_hostname($params) {
	$theme = '<h2>Change Hostname</h2>';
	
	if(isset($_POST['vpsie_host'])){
		$call = 'https://api.vpsie.com/v1/vpsie/rename/'.$params['customfields']['vpsid'];
		$vparams = array(
				'hostname' => $_POST['vpsie_newhostname']
		);
		$json = vpsie_Call($call,$vparams,'POST')
		
		if(!$json['error']){
			$theme .= 'The Hostname was changed successfully';
		}else{
			$virt_errors[] = 'There was an error changing the Hostname';
			$theme .= $virt_errors;
		
			// Change the Hostname
			update_query("tblhosting", array("domain" => $_POST['vpsie_newhostname']), array("id" => $params['serviceid']));
			
		}
	}
	
	$theme .= '
	<form method="post" action="">
		<table cellpadding="8" cellspacing="1" border="0" width="100%" class="divroundshad">
		<tr>
			<td colspan="2"><div class="roundheader">Change Hostname</div></td>
		</tr>
		<tr>
			<td width="50%"><b>Hostname : </b></td>
			<td>'.$params['domain'].'</td>
		</tr>
		<tr>
			<td>New Hostname : </td>
			<td><input type="text" name="vpsie_newhostname" id="vpsie_newhostname" value="" /></td>
		</tr>
		<tr>
			<td colspan="2" align="center"><input type="submit" value="Submit" name="virt_changehostname" />
		</td>
	</table>
	</form><br />';
	
	return $theme;
}*/
function vpsie_reboot($params) {

	$call = 'https://api.vpsie.com/v1/vpsie/force/restart/'.$params['customfields']['vpsid'];
	$vparams = array();
	$json = vpsie_Call($call,$vparams,'POST');
    if ($json['status'] === 'Restarted') {
		$result = "success";
	} else {
		$result = $json['errorCode'];
	}
	logModuleCall('vpsie','reboot',$result,json_encode($json),'','');
	return $result;
}

function vpsie_shutdown($params) {

	$call = 'https://api.vpsie.com/v1/vpsie/shutdown/'.$params['customfields']['vpsid'];
	$vparams = array();
	$json = vpsie_Call($call,$vparams,'POST');
	if ($json['error'] == false && $json['status'] === 'running' && $json['action'] == 'VPSie ShutDown') {
		$result = "success";
	} else {
		$result = $json['errorCode'];
	}
	logModuleCall('vpsie','shutdown',$result,json_encode($json),'','');
	return $result;
}

function vpsie_ClientAreaCustomButtonArray() {
    $buttonarray = array(
	"PTR Records" => "extrapage",
	);
	return $buttonarray;
}
function vpsie_Records($params){
	$call = 'https://api.vpsie.com/v1/ptr/records';
        $vparams = array();
        $json = vpsie_Call($call,$vparams,'GET');
        $json = $json['ptrRecords'];
	$code = "<table class=\"table\"><thead><tr><th>Host</th><th>PTR Record</th></tr><tbody>";
	foreach($json as $record){
		$code .="<tr><td>".$record['host']."</td><td>".$record['content_data']."</td></tr>";
	}
	$code .="</tbody></table>";
        return $code;

}
function vpsie_extrapage($params) {
  $pagearray = array(
     'templatefile' => 'records',
     'breadcrumb' => '<a href="#">PTR Records</a>',
     'vars' => array( 'records' => vpsie_Records($params) )
    );
	return $pagearray;
}
function vpsie_AdminCustomButtonArray() {
    $buttonarray = array(
	 "Force Reboot Server" => "reboot",
	 "Shutdown Server" => "shutdown",
	 "Add PTR Record" => "Record"
	);
	return $buttonarray;
}

/*function vpsie_UsageUpdate($params) {

	$serverid = $params['serverid'];
	$serverhostname = $params['serverhostname'];
	$serverip = $params['serverip'];
	$serverusername = $params['serverusername'];
	$serverpassword = $params['serverpassword'];
	$serveraccesshash = $params['serveraccesshash'];
	$serversecure = $params['serversecure'];

	# Run connection to retrieve usage for all domains/accounts on $serverid

	# Now loop through results and update DB

	foreach ($results AS $domain=>$values) {
        update_query("tblhosting",array(
         "diskused"=>$values['diskusage'],
         "dislimit"=>$values['disklimit'],
         "bwused"=>$values['bwusage'],
         "bwlimit"=>$values['bwlimit'],
         "lastupdate"=>"now()",
        ),array("server"=>$serverid,"domain"=>$values['domain']));
    }

}*/

function vpsie_AdminServicesTabFields($params) {
	$call = 'https://api.vpsie.com/v1/vpsie/'.$params['customfields']['vpsid'];
	$vparams = array();
	$json = vpsie_Call($call,$vparams,'GET');
	$json = $json['vpsie'];
	$status = ( $json['status'] === 'Running' ? true : false ) ? ( '#167a18' ) : ( '#d60000' );

    $fieldsarray = array();
    $fieldsarray['VPS Information'] = 'Status: <span style="color:'.$status.';font-weight:bold;">'.$json['status'].'</span>';
 
    $call = 'https://api.vpsie.com/v1/vpsie/statistics/'.$params['customfields']['vpsid'];
    $vparams = array(); 
    $json = vpsie_Call($call,$vparams,'POST');
    $code .= '
    		<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/c3/0.4.10/c3.min.css">
    		<script src="https://cdnjs.cloudflare.com/ajax/libs/d3/3.5.5/d3.min.js" type="text/javascript"></script>
    		<script src="https://cdnjs.cloudflare.com/ajax/libs/c3/0.4.10/c3.min.js" type="text/javascript"></script>
            <script type="text/javascript">
            	$(\'a.viaction\').click(function(e){
            		e.preventDefault();
            		$(".buttons").children().attr("disabled","disabled");
            		$(".buttons").css("opacity","0.4");
            		var action = $(this).attr(\'va\');
            		console.log(action);
            		$.post("clientarea.php?action=productdetails&id=' . $params['serviceid'] . '&modop=custom&a="+action,function(data){
            			$(".buttons").removeAttr("disabled");
            			$(".buttons").css("opacity","1");
            			window.location.href = window.location.href;
            		});
            		return false;
            	});
    			$(document).ready(function(){
    				$(\'.viaction\').css("curosr","pointer");
    				function makedata(data){
    				
    					var fdata = [];
    					i = 0;
    					for (x in data){
    						fdata.push([i, (data[x])]);
    						i++;
    					}
    				
    					return fdata;
    					
    				}
    				
    				';
    					
    				$code .= '


    				var chart = c3.generate({
    				    bindto: \'#bwband_holder\',
    				    data: {
    				        columns: [
    				            [\'Upload\', 0,'.implode(', ', $json['graph']['netin']).'],
    				            [\'Download\', 0,'.implode(', ', $json['graph']['netout']).']
    				        ],
    				        types: {
    				            data1: \'area\',
    				            data2: \'area-spline\'
    				        }
    				    }
    				});
    				var chart = c3.generate({
    				    bindto: \'#drwband_holder\',
    				    data: {
    				        columns: [
    				            [\'Reads\', 0,'.implode(', ', $json['graph']['diskread']).'],
    				            [\'Writes\', 0,'.implode(', ', $json['graph']['diskwrite']).']
    				        ],
    				        types: {
    				            data1: \'area\',
    				            data2: \'area-spline\'
    				        }
    				    }
    				});
    				var chart = c3.generate({
    				    bindto: \'#cpuband_holder\',
    				    data: {
    				        columns: [
    				            [\'Usage\', 0,'.implode(', ', $json['graph']['cpu']).']
    				        ],
    				        types: {
    				            data1: \'area\',
    				            data2: \'area-spline\'
    				        }
    				    }
    				});
    				var chart = c3.generate({
    				    bindto: \'#ramband_holder\',
    				    data: {
    				        columns: [
    				            [\'Usage\', 0,'.implode(', ', $json['graph']['ram']).']
    				        ],
    				        types: {
    				            data1: \'area\',
    				            data2: \'area-spline\'
    				        }
    				    }
    				});
    				
    			});
    		</script>
    ';
    $code .= '<table class="table">
    			<tr><td><center><h3>CPU Usage Stats</h3><br /><div style="width:300px; height:90px;" id="cpuband_holder"></div></center></td>
    			<td><center><h3>RAM Usage Stats</h3><br /><div style="width:300px; height:90px;" id="ramband_holder"></div></center></td></tr>
    			<tr><td><center><h3>Bandwidth Stats</h3><br /><div style="width:300px; height:90px;" id="bwband_holder"></div></center></td>
    			<td><center><h3>Disk Stats</h3><br /><div style="width:300px; height:90px;" id="drwband_holder"></div></center></td></tr>
    		 </table>';
    $fieldsarray['VPS Information'] .= $code;
    return $fieldsarray;

}
/*
function vpsie_AdminServicesTabFieldsSave($params) {
    update_query("mod_customtable",
    array(
        "var1"=>$_POST['modulefields'][0],
        "var2"=>$_POST['modulefields'][1],
        "var3"=>$_POST['modulefields'][2]
    ),
    array(
       "serviceid"=>$params['serviceid']
   )
);
}
*/
?>
