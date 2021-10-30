<?php
/* Copyright 2020, Simon Fairweather
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */

$plugin = "snapshots";
$docroot = $docroot ?: @$_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
$disks = @parse_ini_file("$docroot/state/disks.ini", true);
$VERBOSE=FALSE; 
require_once "$docroot/webGui/include/Wrappers.php";

/* For when the config file doesn't exist
 * on flash storage */
define('DEFAULT_TARGETCLI_CONFIG', '{
  "fabric_modules": [],
  "storage_objects": [],
  "targets": []
}');

$paths = [  "device_log"		=> "/tmp/{$plugin}/",
			"subvol_settings"	=> "/tmp/{$plugin}/config/subvol.cfg",
			"subvol_schedule"	=> "/tmp/{$plugin}/config/subvolsch.cfg",
			"subvol_schedule.json"	=> "/tmp/{$plugin}/config/subvolsch.cfg",
		];

		
#########################################################
#############        MISC FUNCTIONS        ##############
#########################################################



function is_ip($str) {
	return filter_var($str, FILTER_VALIDATE_IP);
}

function _echo($m) { echo "<pre>".print_r($m,TRUE)."</pre>";}; 

function save_ini_file($file, $array) {
	global $plugin;

	$res = array();
	foreach($array as $key => $val) {
		if(is_array($val)) {
			$res[] = PHP_EOL."[$key]";
			foreach($val as $skey => $sval) $res[] = "$skey = ".(is_numeric($sval) ? $sval : '"'.$sval.'"');
		} else {
			$res[] = "$key = ".(is_numeric($val) ? $val : '"'.$val.'"');
		}
	}

	/* Write changes to tmp file. */
	file_put_contents($file, implode(PHP_EOL, $res));

	/* Write changes to flash. */
	$file_path = pathinfo($file);
	if ($file_path['extension'] == "cfg") {
		file_put_contents("/boot/config/plugins/".$plugin."/".basename($file), implode(PHP_EOL, $res));
	}
}

function save_json_file($file, $array) {
	global $plugin;



	/* Write changes to tmp file. */
	file_put_contents($file,  json_encode($array,JSON_PRETTY_PRINT));

	/* Write changes to flash. */
	$file_path = pathinfo($file);
	if ($file_path['extension'] == "cfg") {
		file_put_contents("/boot/config/plugins/".$plugin."/".basename($file), json_encode($array, JSON_PRETTY_PRINT));
	}
}

#########################################################
############        CONFIG FUNCTIONS        #############
#########################################################

function get_config($sn, $var) {
	$config_file = $GLOBALS["paths"]["config_file"];
	$config = @parse_ini_file($config_file, true);
	return (isset($config[$sn][$var])) ? html_entity_decode($config[$sn][$var]) : FALSE;
}

function set_config($sn, $var, $val) {
	$config_file = $GLOBALS["paths"]["config_file"];
	$config = @parse_ini_file($config_file, true);
	$config[$sn][$var] = htmlentities($val, ENT_COMPAT);
	save_ini_file($config_file, $config);
	return (isset($config[$sn][$var])) ? $config[$sn][$var] : FALSE;
}


function get_subvol_config($sn, $var) {
	$config_file = $GLOBALS["paths"]["subvol_settings"];
	$config = @parse_ini_file($config_file, true);
	return (isset($config[$sn][$var])) ? html_entity_decode($config[$sn][$var]) : FALSE;
}

function set_subvol_config($sn, $var, $val) {
	$config_file = $GLOBALS["paths"]["subvol_settings"];
	$config = @parse_ini_file($config_file, true);
	$config[$sn][$var] = htmlentities($val, ENT_COMPAT);
	save_ini_file($config_file, $config);
	return (isset($config[$sn][$var])) ? $config[$sn][$var] : FALSE;
}

function get_subvol_schedule($sn) {
	$config_file = $GLOBALS["paths"]["subvol_schedule"];
	$config = @parse_ini_file($config_file, true);
	#var_dump($config[$sn], $sn) ;
	#return (isset($config[$sn])) ? html_entity_decode($config[$sn]) : FALSE;
	return (isset($config[$sn])) ? $config[$sn] : FALSE;
}

function set_subvol_schedule($sn, $val) {
	$config_file = $GLOBALS["paths"]["subvol_schedule"];
	#$config_file_json = $GLOBALS["paths"]["subvol_schedule.json"];
	$config = @parse_ini_file($config_file, true);
	#$config_json = @json_decode(file_get_contents($config_file_json) ,true) ;
	#$val = htmlentities($val, ENT_COMPAT);
	$hour2 = $val['hour2'] ?? '*';
    $dotm  = $val['dotm'] ?? '*';
    $month = $val['month'] ?? '*';
    $day   = $val['day'] ?? '*';
	$hour  = $val['hour1'] ?? '*';
	$min   = $val['min'] ?? '*';
		
	switch ($val["snapSchedule"]) {
		case "0": 
			$val["cron"] = "0 $hour2 * * *" ;
			break;
		case "1": 
			$val["cron"] = "$min $hour * * *" ;
			break;
		case "2": 
			$val["cron"] = "$min $hour * * $day" ;
			break;	
		case "3": 
			$val["cron"] = "$min $hour $dotm * *" ;
			break;	
		}
	#var_dump($val) ;
	$val['vmselection'] = implode("," , $val['vmselection']) ;
	$val['excd'] = implode("," , $val['excd']) ;
	$config[$sn] = $val ;
	#$config_json[$sn] = $val ;
	save_ini_file($config_file, $config);
	#save_json_file($config_file_json, $config_json) ;
	if ($config[$sn]["snapscheduleenabled"] == "yes") {
	$cron = "# Generated snapshot schedule for:$sn\n".$val["cron"]." /usr/local/emhttp/plugins/snapshots/include/snapping.php \"$sn\" > /dev/null 2>&1 \n\n"; }
	else {
	$cron="" ;
	}
	parse_cron_cfg("snapshots", urlencode($sn), $cron);

	return (isset($config[$sn][$var])) ? $config[$sn] : FALSE;
}

function get_subvol_schedule_json($sn,$seq=0) {
	$config_file_json = $GLOBALS["paths"]["subvol_schedule.json"];
	$config =  @json_decode(file_get_contents($config_file_json) , true);
	#var_dump($config[$sn], $sn) ;
	#return (isset($config[$sn])) ? html_entity_decode($config[$sn]) : FALSE;
	return (isset($config[$sn][$seq])) ? $config[$sn][$seq] : FALSE;
}

function set_subvol_schedule_json($sn, $val, $schedule_seq=0) {
	
	$config_file_json = $GLOBALS["paths"]["subvol_schedule.json"];
	
	$config = @json_decode(file_get_contents($config_file_json) ,true) ;
	#$val = htmlentities($val, ENT_COMPAT);
	$hour2 = $val['hour2'] ?? '*';
    $dotm  = $val['dotm'] ?? '*';
    $month = $val['month'] ?? '*';
    $day   = $val['day'] ?? '*';
	$hour  = $val['hour1'] ?? '*';
	$min   = $val['min'] ?? '*';
		
	switch ($val["snapSchedule"]) {
		case "0": 
			$val["cron"] = "0 $hour2 * * *" ;
			break;
		case "1": 
			$val["cron"] = "$min $hour * * *" ;
			break;
		case "2": 
			$val["cron"] = "$min $hour * * $day" ;
			break;	
		case "3": 
			$val["cron"] = "$min $hour $dotm * *" ;
			break;	
		}
	#var_dump($val) ;
	$val['vmselection'] = implode("," , $val['vmselection']) ;
	$val['excd'] = implode("," , $val['excd']) ;
	$config[$sn][$schedule_seq] = $val ;

	save_json_file($config_file_json, $config) ;
	if ($config[$sn]["snapscheduleenabled"] == "yes") {
	$cron = "# Generated snapshot schedule for:$sn\n".$val["cron"]." /usr/local/emhttp/plugins/snapshots/include/snapping.php \"$sn\" > /dev/null 2>&1 \n\n"; }
	else {
	$cron="" ;
	}
	parse_cron_cfg("snapshots", urlencode($sn), $cron);

	return (isset($config[$sn][$var])) ? $config[$sn] : FALSE;
}

function get_subvol_sch_config_json($sn, $var,$seq=0) {
	$config_file_json = $GLOBALS["paths"]["subvol_schedule.json"];
	
	$config = @json_decode(file_get_contents($config_file_json) ,true) ;
	return (isset($config[$sn][$seq][$var])) ? html_entity_decode($config[$sn][$seq][$var]) : FALSE;
}

function get_subvol_sch_config($sn, $var) {
	$config_file = $GLOBALS["paths"]["subvol_schedule"];
	$config = @parse_ini_file($config_file, true);
	return (isset($config[$sn][$var])) ? html_entity_decode($config[$sn][$var]) : FALSE;
}



function listDir($root) {
	$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($root, 
			RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST,
			RecursiveIteratorIterator::CATCH_GET_CHILD);
	$paths = array();
	foreach ($iter as $path => $fileinfo) {
		if (! $fileinfo->isDir()) $paths[] = $path;
	}
	return $paths;
}	

function snap_manager_log($m, $type = "NOTICE") {
	global $plugin;

	if ($type == "DEBUG" && ! $GLOBALS["VERBOSE"]) return NULL;
	$m		= print_r($m,true);
	$m		= str_replace("\n", " ", $m);
	$m		= str_replace('"', "'", $m);
	$cmd	= "/usr/bin/logger ".'"'.$m.'"'." -t".$plugin;
	exec($cmd);
}




function build_lunindex($tluns) {
	 
    
	foreach ($tluns as $lun) {
	  $indexlun[$lun["index"]] = $lun ;
	}
	
	return $indexlun ;
}    




function alert($msg) {
    echo "<script type='text/javascript'>alert('$msg');</script>";
}



function build_volume($line) {
         		#if (preg_match('/^.+: ID (?P<id>\S+)(?P<name>.*)$/', $strUSBDevice, $arrMatch)) {
					 global $btrfs_uuid, $btrfs_path, $btrfs_volumes , $btrfs_line;
					 $volume="" ;
             #exec('btrfs subvolume list  -uqcga '.$line,$vol);
			 exec(' cat /mnt/cache/appdata/snapcmd/'.$line ,$vol);
			 $btrfs_path = NULL ;

	foreach ($vol as $vline) {


		#echo "<tr><td>" ;echo preg_match('/^ID parent_uuid (?P<puuid>\S+) uuid (?P<uuid>\S+): path (?P<path>\S+)(?P<name>.*)$/', $vline, $arrMatch) ; echo "</td></tr>" ;
		if (preg_match('/^ID \d{1,25} gen \d{1,25} cgen \d{1,25} top level \d{1,25} parent_uuid (?P<puuid>\S+) * uuid (?P<uuid>\S+) path (?P<path>\S+)/', $vline, $arrMatch)) {
				#if (stripos($GLOBALS['var']['flashGUID'], str_replace(':', '-', $arrMatch['id'])) === 0) {
				#	// Device id matches the unraid boot device, skip device
				#	continue;
			
             
				#echo "<tr><td>" ;var_dump($arrMatch) ;echo "</td></tr>" ;
				
				
				$btrfs_uuid[$arrMatch["uuid"]] = [

						'path' => $arrMatch['path'],
						'puuid' =>$arrMatch['puuid'],
					];

				$btrfs_path[$arrMatch["path"]] = [

						'uuid' =>$arrMatch['uuid'],
						'puuid' =>$arrMatch['puuid'],
					];
				#	$btrfs_volumes[$volume][] = 
		}
	}	

	foreach ($btrfs_path as $key=>$vline) {
		if ($vline["puuid"] == "-")  $btrfs_volumes[$line][$key]["uuid"] = $vline["uuid"] ;
	}

	foreach ($btrfs_volumes[$line] as $key=>$vline) {
		$paths=NULL ;
		foreach ($btrfs_path as $pathkey=>$path) {
				#if ($path["puuid"] == $vline["uuid"])   {
				  $paths[] = $pathkey ;
				  
				#  #echo "<tr><td>" ; var_dump($pathkey ) ; echo "</td></tr>" ;
				#}	
		}	
		
		ksort($paths, SORT_NATURAL) ;
		$btrfs_volumes[$line][$key]["snapshots"] = $paths ; 
	}	 	
}



function build_list($lines) {
	$btrfs_list = array() ;
	foreach ($lines as $line) {
		if ($line == "/etc/libvirt" || $line == "/var/lib/docker" ||$line == "Mounted on") continue ;
		
		$vol=NULL ;
		#exec(' cat /mnt/cache/appdata/snapcmd/'.$line ,$vol);
		exec('btrfs subvolume list  -puqcgaR '.$line,$vol);
		$btrfs_path = NULL ;
        var_dump($line,$vol) ;
		if ($vol != NULL) {
		foreach ($vol as $vline) {


			#echo "<tr><td>" ;echo preg_match('/^ID parent_uuid (?P<puuid>\S+) uuid (?P<uuid>\S+): path (?P<path>\S+)(?P<name>.*)$/', $vline, $arrMatch) ; echo "</td></tr>" ;
			if (preg_match('/^ID \d{1,25} gen \d{1,25} cgen \d{1,25} parent \d{1,25} top level \d{1,25} parent_uuid (?P<puuid>\S+) * received_uuid (?P<ruuid>\S+) * uuid (?P<uuid>\S+) path (?P<path>\S+)/', $vline, $arrMatch)) {
  
  		 		#echo "<tr><td>" ;var_dump($arrMatch) ;echo "</td></tr>" ;

  				$btrfs_list[$line][$arrMatch["path"]] = [		
		   		'uuid' =>$arrMatch['uuid'],
		   		'puuid' =>$arrMatch['puuid'],
				'ruuid' => $arrMatch['ruuid'],
				'snap' => false,
				'vol' => $line,
  				];

			# Get ro status
			$ro=null ;
			exec('btrfs property get  '.$line.'/'.$arrMatch["path"],$ro);
			foreach ($ro as $roline) {
			$rosplit=explode("=", $roline)	 ;
			$btrfs_list[$line][$arrMatch["path"]]["property"][$rosplit[0]] = $rosplit[1] ;
			}
			}
		}
	} else {
		$btrfs_list[$line] =  NULL ;/*[		

		 'snap' => false,
		 'vol' => $line,
		]; */
	}
# Process Snapshots
#
			$vol=NULL ;
			exec('btrfs subvolume list  -spuqcgaR '.$line,$vol);
			$btrfs_path = NULL ;
	
			foreach ($vol as $vline) {
	
	
				#echo "<tr><td>" ;echo preg_match('/^ID parent_uuid (?P<puuid>\S+) uuid (?P<uuid>\S+): path (?P<path>\S+)(?P<name>.*)$/', $vline, $arrMatch) ; echo "</td></tr>" ;
				if (preg_match('/^ID \d{1,25} gen \d{1,25} cgen \d{1,25} parent \d{1,25} top level \d{1,25} otime (?P<odate>\S+) (?P<otime>\S+) parent_uuid (?P<puuid>\S+) * received_uuid (?P<ruuid>\S+) * uuid (?P<uuid>\S+) path (?P<path>\S+)/', $vline, $arrMatch)) {
	  
					   #echo "<tr><td>" ;var_dump($arrMatch) ;echo "</td></tr>" ;
	
					  $btrfs_list[$line][$arrMatch["path"]] = [		
					   'uuid' =>$arrMatch['uuid'],
					   'puuid' =>$arrMatch['puuid'],
					   'ruuid' => $arrMatch['ruuid'],
					   'snap' => true,
					'odate' => 	$arrMatch['odate'],
					'otime' => $arrMatch['otime'],
					'vol' => $line,
					  ];

			# Get ro status
			$ro=null ;
			exec('btrfs property get  '.$line.'/'.$arrMatch["path"],$ro);
			foreach ($ro as $roline) {
			$rosplit=explode("=", $roline)	 ;
			$btrfs_list[$line][$arrMatch["path"]]["property"][$rosplit[0]] = $rosplit[1] ;
			}
				  
				}
		}
	}

#	foreach ($btrfs_list as $key=>$list) {	
#		ksort($btrfs_list[$key],SORT_NATURAL ) ;
#	} 

	
#ksort($btrfs_list,SORT_NATURAL ) ;
return($btrfs_list) ;

}

function get_snapshots($subvol){
	# Process Snapshots
	#
		$vol=NULL ;
		exec('btrfs subvolume list  -s '.$subvol,$vol);
		$btrfs_snaps_path = NULL ;
	
		foreach ($vol as $vline) {
	
	
			#echo "<tr><td>" ;echo preg_match('/^ID parent_uuid (?P<puuid>\S+) uuid (?P<uuid>\S+): path (?P<path>\S+)(?P<name>.*)$/', $vline, $arrMatch) ; echo "</td></tr>" ;
			if (preg_match('/^ID \d{1,25} gen \d{1,25} cgen \d{1,25} top level \d{1,25} otime (?P<odate>\S+) (?P<otime>\S+) path (?P<path>\S+)/', $vline, $arrMatch)) {
	

				if (substr($arrMatch["path"] ,0, 9) == "<FS_TREE>")  $arrMatch["path"] = substr($arrMatch["path"] ,9 ) ;				
			 
				 $btrfs_snaps_path[$arrMatch["path"]] = [		
				'odate' => 	$arrMatch['odate'],
				'otime' => $arrMatch['otime'],
				 ];
	

				
			}
		}
		return($btrfs_snaps_path) ;
	}
	
	

function process_subvolumes($btrfs_list,$line, $uuid){
# Process Snapshots
#
	$vol=NULL ;
	exec('btrfs subvolume list  -spuqcgaR '.$line,$vol);
	$btrfs_path = NULL ;

	foreach ($vol as $vline) {


		#echo "<tr><td>" ;echo preg_match('/^ID parent_uuid (?P<puuid>\S+) uuid (?P<uuid>\S+): path (?P<path>\S+)(?P<name>.*)$/', $vline, $arrMatch) ; echo "</td></tr>" ;
		if (preg_match('/^ID \d{1,25} gen \d{1,25} cgen \d{1,25} parent \d{1,25} top level \d{1,25} otime (?P<odate>\S+) (?P<otime>\S+) parent_uuid (?P<puuid>\S+) * received_uuid (?P<ruuid>\S+) * uuid (?P<uuid>\S+) path (?P<path>\S+)/', $vline, $arrMatch)) {

		   	#echo "<tr><td>" ;var_dump($arrMatch) ;echo "</td></tr>" ;
		 	unset(  $btrfs_list[$line][$arrMatch["path"]] );

			$subvol = $uuid[$arrMatch['puuid']] ;
			$ruuid =  $arrMatch['ruuid'] ;
			if ($subvol == NULL) $subvol = "~NONE" ; 
			if ($ruuid != "-" ) {
				$incremental = $subvol ;
				$subvol = "~INCREMENTAL" ;
			} else { $incremental = NULL ;}

			if (substr($arrMatch["path"] ,0, 9) == "<FS_TREE>")  $arrMatch["path"] = substr($arrMatch["path"] ,10 ) ;				
		 
		 	$btrfs_list[$line][$subvol]["subvolume"][$arrMatch["path"]] = [		
		 	'uuid' =>$arrMatch['uuid'],
		   	'puuid' =>$arrMatch['puuid'],
		   	'ruuid' => $arrMatch['ruuid'],
		   	'snap' => true,
			'odate' => 	$arrMatch['odate'],
			'otime' => $arrMatch['otime'],
			'vol' => $line,
			'incremental' => $incremental,
			'path' => $arrMatch["path"] 
		  	];

			# Get ro status
			$ro=null ;
			exec('btrfs property get  '.$line.'/'.$arrMatch["path"],$ro);
				foreach ($ro as $roline) {
					$rosplit=explode("=", $roline)	 ;
					$btrfs_list[$line][$subvol]["subvolume"][$arrMatch["path"]]["property"][$rosplit[0]] = $rosplit[1] ;
				} 
			
		}
	}
	return($btrfs_list) ;
}


function build_list2($lines) {
	$btrfs_list = array() ;
	$btrfs_uuid = array() ;
	foreach ($lines as $line) {
		if ($line == "/etc/libvirt" || $line == "/var/lib/docker" ||$line == "Mounted on") continue ;
		
		$vol=NULL ;
		#exec(' cat /mnt/cache/appdata/snapcmd/'.$line ,$vol);
		exec('btrfs subvolume list  -apuqcgR '.$line,$vol);
		$btrfs_path = NULL ;
		#$vol = NULL ;
		if ($vol != NULL) {
			foreach ($vol as $vline) {

				#echo "<tr><td>" ;echo preg_match('/^ID parent_uuid (?P<puuid>\S+) uuid (?P<uuid>\S+): path (?P<path>\S+)(?P<name>.*)$/', $vline, $arrMatch) ; echo "</td></tr>" ;
				if (preg_match('/^ID \d{1,25} gen \d{1,25} cgen \d{1,25} parent \d{1,25} top level \d{1,25} parent_uuid (?P<puuid>\S+) * received_uuid (?P<ruuid>\S+) * uuid (?P<uuid>\S+) path (?P<path>\S+)/', $vline, $arrMatch)) {

					#echo "<tr><td>" ;var_dump($arrMatch) ;echo "</td></tr>" ;

			#		if (substr($arrMatch["path"] ,0, 9) == "<FS_TREE>")  $arrMatch["path"] = substr($arrMatch["path"] ,10 ) ;
					#$arrMatch["path"][0] = "A" ; 

					$btrfs_list[$line][$arrMatch["path"]] = [		
					'uuid' =>$arrMatch['uuid'],
					'puuid' =>$arrMatch['puuid'],
					'ruuid' => $arrMatch['ruuid'],
					'snap' => false,
					'vol' => $line,
					'path' => $arrMatch["path"] 
					];

					$btrfs_uuid[$arrMatch['uuid']] = $arrMatch["path"] ;

					# Get ro status
					$ro=null ;
					exec('btrfs property get  '.$line.'/'.$arrMatch["path"],$ro);
					foreach ($ro as $roline) {
						$rosplit=explode("=", $roline)	 ;
						$btrfs_list[$line][$arrMatch["path"]]["property"][$rosplit[0]] = $rosplit[1] ;
					}
				}
			}
		} else 
		{
			$btrfs_list[$line] =  NULL ;/*[		

			'snap' => false,
			'vol' => $line,
			]; */
		}
		$btrfs_list = process_subvolumes($btrfs_list,$line,$btrfs_uuid) ;
	}
	ksort($btrfs_list, SORT_NATURAL) ;
return($btrfs_list) ;

}

function build_list3($lines) {
	$btrfs_list = array() ;
	$btrfs_uuid = array() ;
	foreach ($lines as $line) {
		if ($line == "/etc/libvirt" || $line == "/var/lib/docker" ||$line == "Mounted on") continue ;
		
		$vol=NULL ;
		#exec(' cat /mnt/cache/appdata/snapcmd/'.$line ,$vol);
		exec('btrfs subvolume list  -opuqcgR '.$line,$vol);
		$btrfs_path = NULL ;
		#$vol = NULL ;
		if ($vol != NULL) {
			foreach ($vol as $vline) {

				#echo "<tr><td>" ;echo preg_match('/^ID parent_uuid (?P<puuid>\S+) uuid (?P<uuid>\S+): path (?P<path>\S+)(?P<name>.*)$/', $vline, $arrMatch) ; echo "</td></tr>" ;
				if (preg_match('/^ID \d{1,25} gen \d{1,25} cgen \d{1,25} parent \d{1,25} top level \d{1,25} parent_uuid (?P<puuid>\S+) * received_uuid (?P<ruuid>\S+) * uuid (?P<uuid>\S+) path (?P<path>\S+)/', $vline, $arrMatch)) {

					#echo "<tr><td>" ;var_dump($arrMatch) ;echo "</td></tr>" ;

					#if (substr($arrMatch["path"] ,0, 9) == "<FS_TREE>")  $arrMatch["path"] = substr($arrMatch["path"] ,10 ) ;
					#$arrMatch["path"][0] = "A" ; 
					$path=$line.'/'.$arrMatch["path"] ;
					$btrfs_list[$line][$path] = [		
					'uuid' =>$arrMatch['uuid'],
					'puuid' =>$arrMatch['puuid'],
					'ruuid' => $arrMatch['ruuid'],
					'snap' => false,
					'vol' => $line,
					'path' => $path ,
					'short_vol' => $arrMatch["path"]
					];

					$btrfs_uuid[$arrMatch['uuid']] = $path;

					# Get ro status
					$ro=null ;
					exec('btrfs property get  '.$path,$ro);
					foreach ($ro as $roline) {
						$rosplit=explode("=", $roline)	 ;
						$btrfs_list[$line][$path]["property"][$rosplit[0]] = $rosplit[1] ;
					}
				}
			}
		} else 
		{
			$btrfs_list[$line] =  NULL ;/*[		

			'snap' => false,
			'vol' => $line,
			]; */
		}
		#var_dump($btrfs_uuid) ;
		$btrfs_list = process_subvolumes3($btrfs_list,$line,$btrfs_uuid) ;
	}
	ksort($btrfs_list, SORT_NATURAL) ;
return($btrfs_list) ;

}
function process_subvolumes3($btrfs_list,$line, $uuid){
	# Process Snapshots
	#
		$vol=NULL ;
		exec('btrfs subvolume list  -spuqcgR '.$line,$vol);
		$btrfs_path = NULL ;
	
		foreach ($vol as $vline) {
	
	
			#echo "<tr><td>" ;echo preg_match('/^ID parent_uuid (?P<puuid>\S+) uuid (?P<uuid>\S+): path (?P<path>\S+)(?P<name>.*)$/', $vline, $arrMatch) ; echo "</td></tr>" ;
			if (preg_match('/^ID \d{1,25} gen \d{1,25} cgen \d{1,25} parent \d{1,25} top level \d{1,25} otime (?P<odate>\S+) (?P<otime>\S+) parent_uuid (?P<puuid>\S+) * received_uuid (?P<ruuid>\S+) * uuid (?P<uuid>\S+) path (?P<path>\S+)/', $vline, $arrMatch)) {
	
				   #echo "<tr><td>" ;var_dump($arrMatch) ;echo "</td></tr>" ;
				 unset(  $btrfs_list[$line][$line.'/'.$arrMatch["path"]] );
	
				$subvol = $uuid[$arrMatch['puuid']] ;
				$ruuid =  $arrMatch['ruuid'] ;
				if ($subvol == NULL) $subvol = "~NONE" ; 
				if ($ruuid != "-" ) {
					$incremental = $subvol ;
					$subvol = "~INCREMENTAL" ;
				} else { $incremental = NULL ;}
	
				#if (substr($arrMatch["path"] ,0, 9) == "<FS_TREE>")  $arrMatch["path"] = substr($arrMatch["path"] ,10 ) ;				
			 
				 $btrfs_list[$line][$subvol]["subvolume"][$arrMatch["path"]] = [		
				 'uuid' =>$arrMatch['uuid'],
				   'puuid' =>$arrMatch['puuid'],
				   'ruuid' => $arrMatch['ruuid'],
				   'snap' => true,
				'odate' => 	$arrMatch['odate'],
				'otime' => $arrMatch['otime'],
				'vol' => $line,
				'incremental' => $incremental,
				'path' => $arrMatch["path"] 
				  ];
	
				# Get ro status
				$ro=null ;
				exec('btrfs property get  '.$line.'/'.$arrMatch["path"],$ro);
					foreach ($ro as $roline) {
						$rosplit=explode("=", $roline)	 ;
						$btrfs_list[$line][$subvol]["subvolume"][$arrMatch["path"]]["property"][$rosplit[0]] = $rosplit[1] ;
					} 
				
			}
		}
		return($btrfs_list) ;
	}
	

function subvol_parents() {
	$btrfs_list = array() ;
	$btrfs_uuid = array() ;
	exec(' df -t btrfs --output="target" ',$lines);
	foreach ($lines as $line) {
		if ($line == "/etc/libvirt" || $line == "/var/lib/docker" ||$line == "Mounted on") continue ;
		
		$vol=NULL ;
		#exec(' cat /mnt/cache/appdata/snapcmd/'.$line ,$vol);
		exec('btrfs subvolume list  -puqcgaR '.$line,$vol);
		$btrfs_path = NULL ;
		#$vol = NULL ;
		if ($vol != NULL) {
			foreach ($vol as $vline) {

				#echo "<tr><td>" ;echo preg_match('/^ID parent_uuid (?P<puuid>\S+) uuid (?P<uuid>\S+): path (?P<path>\S+)(?P<name>.*)$/', $vline, $arrMatch) ; echo "</td></tr>" ;
				if (preg_match('/^ID \d{1,25} gen \d{1,25} cgen \d{1,25} parent \d{1,25} top level \d{1,25} parent_uuid (?P<puuid>\S+) * received_uuid (?P<ruuid>\S+) * uuid (?P<uuid>\S+) path (?P<path>\S+)/', $vline, $arrMatch)) {
                    $key=$line.'/'.$arrMatch["path"] ;
					$btrfs_list[$key] = [		
					'vol' => $line,
						];


	
				}
			}
		} 
	}
	ksort($btrfs_list, SORT_NATURAL) ;
return($btrfs_list) ;

}

