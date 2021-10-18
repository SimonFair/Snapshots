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
	$config = @parse_ini_file($config_file, true);
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
	$config[$sn] = $val ;
	save_ini_file($config_file, $config);
	if ($config[$sn]["snapscheduleenabled"] == "yes") {
	$cron = "# Generated snapshot schedule for:$sn\n".$val["cron"]." /usr/local/emhttp/plugins/snapshots/include/snapping.php \"$sn\" > /dev/null 2>&1 \n\n"; }
	else {
	$cron="" ;
	}
	parse_cron_cfg("snapshots", urlencode($sn), $cron);

	return (isset($config[$sn][$var])) ? $config[$sn] : FALSE;
}

function get_subvol_sch_config($sn, $var) {
	$config_file = $GLOBALS["paths"]["subvol_schedule"];
	$config = @parse_ini_file($config_file, true);
	return (isset($config[$sn][$var])) ? html_entity_decode($config[$sn][$var]) : FALSE;
}




/*

function parse_cron_cfg($plugin, $job, $text = "") {
  $cron = "/boot/config/plugins/$plugin/$job.cron";
  if ($text) file_put_contents($cron, $text); else @unlink($cron);
  exec("/usr/local/sbin/update_cron");
}

function cron_sch($schedule) {

  $cron = "";
  if ($schedule['mode']>0) {
    $time  = $schedule['hour'] ?? '* *';
    $dotm  = $schedule['dotm'] ?? '*';
    $month = $schedule['month'] ?? '*';
    $day   = $schedule['day'] ?? '*';
    $term  = '';
    switch ($dotm) {
      case '28-31': 
        $term = '[[ $(date +%e -d +1day) -eq 1 ]] && ';
        break;
      case 'W1'   :
        $dotm = '*';
        $term = '[[ $(date +%e) -le 7 ]] && ';
        break;
      case 'W2'   :
        $dotm = '*';
        $term = '[[ $(date +%e -d -7days) -le 7 ]] && ';
        break;
      case 'W3'   :
        $dotm = '*';
        $term = '[[ $(date +%e -d -14days) -le 7 ]] && ';
        break;
      case 'W4'   : 
        $dotm = '*';
        $term = '[[ $(date +%e -d -21days) -le 7 ]] && ';
        break;
      case 'WL'   : 
        $dotm = '*';
        $term = '[[ $(date +%e -d +7days) -le 7 ]] && ';
        break;
    }
    $cron = "# Generated parity check schedule:\n$time $dotm $month $day $term/usr/local/sbin/mdcmd check $write &> /dev/null || :\n\n";
  }
  parse_cron_cfg("snapshots", urlencode($schdule["path"]), $cron);
  
} 
} 
 */


function get_unassigned_disks() {
	global $disks;

	$ud_disks = $paths = $unraid_disks = $b =  array();
	/* Get all devices by id. */
	 exec('lsblk -OJ'  ,$tj) ;
	$t=json_decode(implode("", $tj), true);
	$t = $t['blockdevices'] ;	
	foreach (listDir("/dev/disk/by-id/") as $p) {
		$r = realpath($p);
		/* Only /dev/sd*, dev/sr0, /dev/hd*, and /dev/nvme* devices. */
		if (! is_bool(strpos($r, "/dev/sd")) || !is_bool(strpos($r, "/dev/hd")) || !is_bool(strpos($r, "/dev/nvme")) || !is_bool(strpos($r, "/dev/sr")) ) {
			$paths[$r] = $p;
			}
		}		
	natsort($paths);
	
	/* Get all unraid disk devices (array disks, cache, and pool devices) */
	foreach ($disks as $d) {
		if ($d['device']) {
			$unraid_disks[] = "/dev/".$d['device'];
		}
	}
	
	foreach($t as $tr) {
		    
			if ($tr['tran'] != '' ) {   
			$b["/dev/".$tr['name']]=$tr;
		}}
	
	
	$LIOdevices=build_iscsi_devices(get_iscsi_json()) ;

	/* Create the array of unassigned devices. */
	foreach ($paths as $path => $d) {
		if (($d != "")  && (preg_match("#^(.(?!part))*$#", $d))) {
			if (in_array($path, $unraid_disks)) $unraid=true ; else $unraid=false ;
				$m=$b[$path]['children'] ;
				if ($m==null && $b[$path]['type']=="rom" && $b[$path]['fstype']!='') $m=array($b[$path]) ;
                $ro=0 ; $LIOname = "" ;
				if (array_search($d , array_column($LIOdevices, 'dev')) !==false || array_search($path , array_column($LIOdevices, 'dev')) !==false) $defined = true ; else $defined=false; 
				if ($defined) {
					$k=array_search($d , array_column($LIOdevices, 'dev'), true) ;
					if ($k !== false) {
						$ro=$LIOdevices[$k]["readonly"] ;
					    $LIOname=$LIOdevices[$k]["name"] ;
					}
					else $ro = 0 ;
				}
				  

				$ud_disks[$path] = array(
									"device"=>$d,  
									"unraid"=>$unraid, 
									"hctl"=>$b[$path]['hctl'] ,
									"type"=>$b[$path]['type'] ,
									"vendor"=>$b[$path]['vendor'] ,
									"model"=>$b[$path]['model'] ,
									"rev"=>$b[$path]['rev'] ,
									"serial"=>$b[$path]['serial'] ,
									"tran"=>$b[$path]['tran'],
									"size"=>$b[$path]['size'],
									"bpartitions"=>$m,
									"defined"=> $defined,
									"readonly"=> $ro ,
									"nickname"=>$LIOname ,
									"name"=>$b[$path]['name']
		
		) ;

		}
	}
	ksort($ud_disks, SORT_NATURAL) ;
	return $ud_disks ;
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

function get_iscsi_json() {
	global $iSCSI_JSON ;

	$configfile="/etc/target/saveconfig.json";
	/* More than likely this is a symbolic link - check and
	 * reorient if so */
	if (is_link($configfile))
	{
		$configfile=readlink($configfile);
	}
	/* Fill with empty config if it doesn't exist */
	if (!file_exists($configfile))
	{
		file_put_contents($configfile, $string=DEFAULT_TARGETCLI_CONFIG);
	}
	else
	{
		$string = file_get_contents($configfile);
	}
	$tj = json_decode($string, true);
	$t=$iSCSI_JSON=json_decode(implode("", $tj), true);
	
	return $tj ;
}

function build_iscsi_devices($tj) {
	global $iSCSI_Storage ;

	$dev=0 ;
	$sd = $iSCSI_Storage= $tj["storage_objects"] ;
	foreach ($sd as $key=>$sr) {
		unset($sr["alua_tpgs"]) ;
		unset($sr["attributes"]) ;
		$sd[$key] = $sr;	
	}
		
	return $sd ;
}    

function build_fileio($tj) {
	 
	$dev=0 ;
	$sd =  $tj["storage_objects"] ;
	foreach ($sd as $key=>$sr) {
		unset($sr["alua_tpgs"]) ;
		unset($sr["attributes"]) ;
		$sd[$key] = $sr;	
	}
	
		
	
	return $sd ;
}    

function build_lunindex($tluns) {
	 
    
	foreach ($tluns as $lun) {
	  $indexlun[$lun["index"]] = $lun ;
	}
	
	return $indexlun ;
}    


function build_iscsi_initiators($tj) {
	global $targetname ;
	# global $luns ;
	
	
	$dev=0 ;
	
	$sd = $tj["targets"][0] ;
	$tgt=$sd["tpgs"][0] ;
	$luns=(isset($tgt["luns"]) ? $tgt["luns"] : []);
	$node_acls=(isset($tgt["node_acls"]) ? $tgt["node_acls"] : []) ;
	$portals=$tgt["portals"] ;
	$parms=$tgt["parameters"] ;
	$enable=$tgt["enable"] ;
	$targetname=$sd["wwn"] ;

#	sort($luns) ;
	
		return $node_acls ;
}    

function filelock() {
	// file_exists (string $filename ) : bool
    if (!exec('modinfo configfs',$output, $return)) return(2) ;

	$fp = fopen('/var/run/targetcli.lock', 'w');
    if (!flock($fp, LOCK_EX|LOCK_NB, $wouldblock)) {
		if ($wouldblock) {
			// another process holds the lock
			fclose($fp) ;
			if (file_exists("/var/run/iscsi.tab")) 	unlink("/var/run/iscsi.tab") ;
			return false ;
		}
	}
	else {
		fclose($fp) ;
		return true ;
	}
}
 
function alert($msg) {
    echo "<script type='text/javascript'>alert('$msg');</script>";
}

function processTargetcli($cmdstr) {
	# Write command string a process
	# targetctl  /tmp/string > /var/run/targetcli.last
	#exec($cmdstr  ,$tj) ;
	
    $cmd=$cmdstr."\nexit\n"  ;
    exec("echo \"$cmd\" >/tmp/iscsicmd.run", $output, $myreturn );
	$cmd="targetcli </tmp/iscsicmd.run >/var/run/targetcli.last 2>&1";
	exec($cmd, $output, $return) ;
	return($return) ;
   
}
function availstorage() {
	$json=get_iscsi_json() ;
	$storage=(build_fileio($json)) ;
	$rtndevs=array() ;

	foreach ($storage as $dev) 
	{ 
		$rtndevs[] = $dev["plugin"].";".$dev["name"] ; 
	 }

	return($rtndevs) ;
}
function availtgt() {
	$json=get_iscsi_json() ;
	$rtntgt=array() ;

	foreach($json["targets"] as $sd) {
    	$rtntgt[]=$sd["wwn"] ;
	 }

	return($rtntgt) ;
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

