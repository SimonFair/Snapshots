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

$cfg     = parse_plugin_cfg($plugin);
$excluded_folders = $cfg['exclude'] ? array_map('trim',explode(',',rtrim($cfg['exclude'], ','))) : [];
// $excluded_folders = array_map('addslash',$excluded_folders);

#########################################################
#############        MISC FUNCTIONS        ##############
#########################################################

function addslash($thing) {
    return rtrim($thing, '/') . '/';
}

function is_excluded($path) {
	global $excluded_folders;

	$path = addslash($path);

	foreach ($excluded_folders as &$value) {
		if(strpos($path, $value) !== false) {
			return true;                
		}
	}

	return false;
}

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

function snap_return($return) {
	$return_value="" ;
	foreach($return as $line) {
		$return_value.=" ".$line ;
	}
	return($return_value) ;
}

function snap_error($error) {
	if ($error) return "Error" ; else return("OK") ;
}

function findText($start_limiter,$end_limiter,$haystack)
{
   $start_pos = strpos($haystack,$start_limiter);
   if ($start_pos === FALSE)
   {
       return FALSE;
   }

   $end_pos = strpos($haystack,$end_limiter,$start_pos);

   if ($end_pos === FALSE)
   {
      return FALSE;
   }

   return substr($haystack, $start_pos+1, ($end_pos-1)-$start_pos);
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
	$val['rund'] = implode("," , $val['rund']) ;
	$rund   = $val['rund'] ;
	#$rund = "Sun" ;
		
	switch ($val["snapSchedule"]) {
		case "0": 
			$val["cron"] = "0 $hour2 * * $rund" ;
			break;
		case "1": 
			$val["cron"] = "$min $hour * * $rund" ;
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

function get_subvol_schedule_slots($sn) {
	$config_file_json = $GLOBALS["paths"]["subvol_schedule.json"];
	$config =  @json_decode(file_get_contents($config_file_json) , true);
	#var_dump($config[$sn], $sn) ;
	#return (isset($config[$sn])) ? html_entity_decode($config[$sn]) : FALSE;
	return (isset($config[$sn])) ? $config[$sn] : FALSE;
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
	$rundarray=  $val['rund'] ;
	$val['rund'] = implode("," , $val['rund']) ;

/* Process new Slot if slot = 99 */
	$seq_count=0 ;
	if ($schedule_seq == "99")
	{
		$slots=  get_subvol_schedule_slots($sn) ;
		ksort($slots) ;
		foreach ($slots as $slotseq=>$slot) {
			if ($slotseq == $seq_count ) {
				 $seq_count++; 
				 continue ;
				} else {
					$schedule_seq = $seq_count ;
					break ;
				}
		}
		if ($schedule_seq == 99) $schedule_seq = $seq_count ;


	}

	#$rund   = $val['rund'] ?? '*';


	$rund = "" ;
	foreach ($rundarray as $daytorun) {
		switch ($daytorun) {
			case _("Sunday"):
				$rund.= "0," ;
				break ;
			case _("Monday"):
				$rund.= "1," ;
				break ;
			case _("Tuesday"):
				$rund.= "2," ;
				break ;
			case _("Wednesday"):
				$rund.= "3," ;
				break ;
			case _("Thursday"):
				$rund.= "4," ;
				break ;
			case _("Friday"):
				$rund.= "5," ;
				break ;	
			case _("Saturday"):
				$rund.= "6," ;
				break;
		}
	}
		
	switch ($val["snapSchedule"]) {
		case "0": 
			$val["cron"] = "0 $hour2 * * $rund" ;
			break;
		case "1": 
			$val["cron"] = "$min $hour * * $rund" ;
			break;
		case "2": 
			$val["cron"] = "$min $hour * * $day" ;
			$rund="*" ;
			break;	
		case "3": 
			$val["cron"] = "$min $hour $dotm * *" ;
			$rund="*" ;
			break;	
		}
	#var_dump($val) ;
	if (is_array($val['vmselection'])) $val['vmselection'] = implode("," , $val['vmselection']) ;
	#var_dump($val['vmselection']) ;
	$config[$sn][$schedule_seq] = $val ;

	save_json_file($config_file_json, $config) ;
	if ($config[$sn][$schedule_seq]["snapscheduleenabled"] == "yes" && $rund !="") {
	$cron = "# Generated snapshot schedule for:$sn\n".$val["cron"]." /usr/local/emhttp/plugins/snapshots/include/snapping.php \"$sn\" \"$schedule_seq\" > /dev/null 2>&1 \n\n"; }
	else {
	$cron="" ;
	}
	$file=$sn."Slot".$schedule_seq ;
	parse_cron_cfg("snapshots", urlencode($file), $cron);

	return $schedule_seq ;
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


function alert($msg) {
    echo "<script type='text/javascript'>alert('$msg');</script>";
}


function get_snapshots($subvol){
	# Process Snapshots
	#
		$vol=NULL ;
		exec('btrfs subvolume list  -s '.$subvol,$vol);
		$btrfs_snaps_path = NULL ;
	
		foreach ($vol as $vline) {
	
	

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


function build_list3($lines,$hideroot=false,$hidedocker=false,$hideexcluded=false) {
	global $docker_path ;
	$btrfs_list = array() ;
	$btrfs_uuid = array() ;
	foreach ($lines as $line) {
		if ($line == "/etc/libvirt" || $line == "/var/lib/docker" ||$line == "Mounted on") continue ;
#btrfs  sub show /mnt/disk1  | /bin/grep 'UUID' | /bin/awk '{print $2}'

		$s	= shell_exec("/sbin/btrfs sub show ".escapeshellarg($line)." | /bin/grep 'UUID' | /bin/awk '{print $2}'");
		$rc	= explode("\n", $s);
		$btrfs_uuid[$rc[0]] = $line;
		if ($hideroot==false) {
			$btrfs_list[$line][$line] = [		
			#	'uuid' =>$arrMatch['uuid'],
			#	'puuid' =>$arrMatch['puuid'],
			#	'ruuid' => $arrMatch['ruuid'],
			'uuid' => $rc[0],
			'puuid' => '-',
			'ruuid' => '-',
				'snap' => false,
				'vol' => $line,
				'path' => $line ,
				'root' => true ,
				'short_vol' => "/"
				];	
			
		}
		
		$vol=NULL ;

		exec('btrfs subvolume list  -puqcgR '.$line,$vol);
		$btrfs_path = NULL ;

		if ($vol != NULL) {
			foreach ($vol as $vline) {

				$docker_path = $docker_path = str_replace($line."/", "" ,$docker_path) ;
				if (preg_match('/^ID \d{1,25} gen \d{1,25} cgen \d{1,25} parent \d{1,25} top level \d{1,25} parent_uuid (?P<puuid>\S+) * received_uuid (?P<ruuid>\S+) * uuid (?P<uuid>\S+) path (?P<path>[\S\D]+)/', $vline, $arrMatch)) {
					if (substr($arrMatch["path"] , 0, strlen($docker_path)) == $docker_path && $hidedocker == true) continue ;
					$path=$line.'/'.$arrMatch["path"] ;
					if (($hideexcluded == true) && is_excluded($path)) continue ;

					$btrfs_list[$line][$path] = [		
					'uuid' =>$arrMatch['uuid'],
					'puuid' =>$arrMatch['puuid'],
					'ruuid' => $arrMatch['ruuid'],
					'snap' => false,
					'vol' => $line,
					'path' => $path ,
					'short_vol' => $arrMatch["path"] ,
					'root' => false
					];

					$btrfs_uuid[$arrMatch['uuid']] = $path;

					# Get ro status
					$ro=null ;
					exec('btrfs property get  "'.$path.'"',$ro);
					foreach ($ro as $roline) {
						$rosplit=explode("=", $roline)	 ;
						$btrfs_list[$line][$path]["property"][$rosplit[0]] = $rosplit[1] ;
					}
				}
			}
		} else 
		{
			if ($hideroot==true) {
			$btrfs_list[$line] =  NULL ;
			}

		}
	;
		$btrfs_list = process_subvolumes3($btrfs_list,$line,$btrfs_uuid,$hidedocker,$hideexcluded) ;
		$btrfs_list = process_received($btrfs_list,$line) ;
	}
	ksort($btrfs_list, SORT_NATURAL) ;
return($btrfs_list) ;

}
function process_subvolumes3($btrfs_list,$line, $uuid, $hidedocker=false,$hideexcluded=false){
	global $docker_path ;
	# Process Snapshots
	#
		$vol=NULL ;
		exec('btrfs subvolume list  -spuqcgR '.$line,$vol);
		$btrfs_path = NULL ;
	
		foreach ($vol as $vline) {
	
	
	
			if (preg_match('/^ID \d{1,25} gen \d{1,25} cgen \d{1,25} parent \d{1,25} top level \d{1,25} otime (?P<odate>\S+) (?P<otime>\S+) parent_uuid (?P<puuid>\S+) * received_uuid (?P<ruuid>\S+) * uuid (?P<uuid>\S+) path (?P<path>\S+)/', $vline, $arrMatch)) {
				if (substr($arrMatch["path"] , 0, strlen($docker_path)) == $docker_path && $hidedocker == true) continue ;
				if (($hideexcluded == true) && is_excluded($line.'/'.$arrMatch["path"])) continue ;

				unset(  $btrfs_list[$line][$line.'/'.$arrMatch["path"]] );
	
				$subvol = $uuid[$arrMatch['puuid']] ;
				$ruuid =  $arrMatch['ruuid'] ;
				if ($subvol == NULL) $subvol = "~NONE" ; 
				if ($ruuid != "-" ) {
					$incremental = $subvol ;
					$subvol = "~INCREMENTAL" ;
					$btrfs_list[$line][$subvol]["short_vol"] = $subvol ;
				} else { $incremental = NULL ;}
	
			
				
				 $btrfs_list[$line][$subvol]["subvolume"][$arrMatch["path"]] = [		
				'uuid' =>$arrMatch['uuid'],
				'puuid' =>$arrMatch['puuid'],
				'ruuid' => $arrMatch['ruuid'],
				'snap' => true,
				'odate' => 	$arrMatch['odate'],
				'otime' => $arrMatch['otime'],
				'vol' => $line,
				'incremental' => $incremental,
				'path' => $arrMatch["path"] ,
				'parent' => $subvol 
				  ];
	
				# Get ro status
				$ro=null ;
				exec('btrfs property get  "'.$line.'/'.$arrMatch["path"].'"',$ro);
					foreach ($ro as $roline) {
						$rosplit=explode("=", $roline)	 ;
						$btrfs_list[$line][$subvol]["subvolume"][$arrMatch["path"]]["property"][$rosplit[0]] = $rosplit[1] ;
					} 
				
			}
		}
		return($btrfs_list) ;
	}

	function process_received($btrfs_list,$line){
		# Process Receieved Snapshots
		#
		$volume=$btrfs_list[$line] ;
	    if (is_array($volume)) {
			foreach ($volume as $vkey=>$vline) {
				if ($volume["root"] == true) continue ;
		

				$puuid=$vline["puuid"] ;
				$ruuid=$vline["ruuid"] ;

				if ($parent = '-' && $ruuid != '-' && $vline["short_vol"] != "~INCREMENTAL") {
						unset($btrfs_list[$line][$vline["path"]]) ;
		
					$subvol = "~RECIEVED" ; 
					$incremental = NULL ;

		
					$btrfs_list[$line][$subvol]["short_vol"] = "~RECEIVED" ;
					 $btrfs_list[$line][$subvol]["subvolume"][$vline["short_vol"]] = [		
					'uuid' =>$vline['uuid'],
					'puuid' =>$vline['puuid'],
					'ruuid' => $vline['ruuid'],
					'snap' => true,
					'odate' => 	$vline['odate'],
					'otime' => $vline['otime'],
					'vol' => $line,
					'incremental' => $incremental,
					'property' => $vline["property"] ,
					'path' => $vline["path"] ,
					'parent' => $subvol 
					  ];
		
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

		exec('btrfs subvolume list  -puqcgaR '.$line,$vol);
		$btrfs_path = NULL ;

		if ($vol != NULL) {
			foreach ($vol as $vline) {

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

function build_list_zfs($lines) {
	$zfs_list = array() ;
	$zfs_uuid = array() ;
	$pools=null ;
	exec('zpool list -H',$pools);
	$pools = preg_replace('/\s+/', ' ', $pools);
	foreach ($pools as $pool_detail) {

		$pool=explode(" ",$pool_detail) ; 
		$pool_name = $pool[0] ;
		$pool_status = $pool[10] ;
		$zfs_list[$pool_name]["~config"]["SIZE"] = $pool[1] ;
		$zfs_list[$pool_name]["~config"]["ALLOC"] = $pool[2] ;
		$zfs_list[$pool_name]["~config"]["FREE"] = $pool[3] ;
		$zfs_list[$pool_name]["~config"]["CKPOINT"] = $pool[4] ;
		$zfs_list[$pool_name]["~config"]["EXPANDSZ"] = $pool[5] ;
		$zfs_list[$pool_name]["~config"]["FRAG"] = $pool[6] ;
		$zfs_list[$pool_name]["~config"]["CAP"] = $pool[7] ;
		$zfs_list[$pool_name]["~config"]["DEDUP"] = $pool[8] ;
		$zfs_list[$pool_name]["~config"]["HEALTH"] = $pool[9] ;
		$zfs_list[$pool_name]["~config"]["ALTROOT"] = $pool[10] ;

		$vol=NULL ;

		$zfs = null ;
		exec('zfs list -H -r '.$pool_name,$zfs);
		$zfs = preg_replace('/\s+/', ' ', $zfs);
	
		if ($pool_name != NULL) {
			foreach ($zfs as $zfs_detail) 
			{
			$zfsline=explode(" ",$zfs_detail) ; 
			$zfs_name = $zfsline[0] ;	
			$zfs_list[$pool_name][$zfs_name] =	[	
				'USED' =>$zfsline[1],
				'AVAIL' =>$zfsline[2],
				'REFER' => $zfsline[3],
				'MOUNTPOINT' => $zfsline[4],
				'string' => $zfs_detail,
			] ;
				}
			
		} else 
		{
			$zfs_list[$pol_name] =  NULL ;	


		}


		$zfs=null ;
		exec('zfs list -H -t snapshot -r '.$pool_name,$zfs);
		$zfs = preg_replace('/\s+/', ' ', $zfs);
	
		if ($pool_name != NULL) {
			foreach ($zfs as $zfs_detail) 
			{
			$zfsline=explode(" ",$zfs_detail) ; 
			$zfs_name = explode("@",$zfsline[0]) ;	
			
			$zfs_list[$pool_name][$zfs_name[0]]["snapshots"][$zfs_name[1]] =	[	
				'USED' =>$zfsline[1],
				'AVAIL' =>$zfsline[2],
				'REFER' => $zfsline[3],
				'MOUNTPOINT' => $zfsline[4],
				'string' => $zfs_detail,
			] ;
				}
		}

	}
	ksort($zfs_list, SORT_NATURAL) ;
return($zfs_list) ;

}
function zfs_list()
{
	exec('zfs list -H ',$targetcli);
	$output = preg_replace('/\s+/', ' ', $targetcli);
	echo "<tr><td>" ;
var_dump($output) ;
echo "</td></tr>" ;
echo "<tr><td>" ;
var_dump(explode(" ",$output[0] )) ;
echo "</td></tr>" ;


exec('zpool list -H ',$targetcli);

$output = preg_replace('/\s+/', ' ', $targetcli);
		echo "<tr><td>" ;
 var_dump($output) ;
 echo "</td></tr>" ;
 echo "<tr><td>" ;
 var_dump(explode(" ",$output[0] )) ;
 echo "</td></tr>" ;

 
exec('zfs list -H -t snapshot ',$targetcli);

$output = preg_replace('/\s+/', ' ', $targetcli);
		echo "<tr><td>" ;
 var_dump($output) ;
 echo "</td></tr>" ;
 echo "<tr><td>" ;
 var_dump(explode(" ",$output[0] )) ;
 echo "</td></tr>" ;



}

function remove_tags($snaps, $tag="")
{
	foreach($snaps as $key=>$snap) {
		
	
		$parts = explode('.', $key);
		if (count($parts) < 2)  {unset($snaps[$key]) ;continue ; }
		$last = array_pop($parts);
		if ($tag != $last) {unset($snaps[$key]) ; continue ;}
	

	}
	return $snaps ;
}
