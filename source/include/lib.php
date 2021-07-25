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

/* For when the config file doesn't exist
 * on flash storage */
define('DEFAULT_TARGETCLI_CONFIG', '{
  "fabric_modules": [],
  "storage_objects": [],
  "targets": []
}');


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

function unassigned_log($m, $type = "NOTICE") {
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
	$btrfs_list = NULL ;
	foreach ($lines as $line) {
		if ($line == "/etc/libvirt" || $line == "/var/lib/docker" ||$line == "Mounted on") continue ;
		
		$vol=NULL ;
		#exec(' cat /mnt/cache/appdata/snapcmd/'.$line ,$vol);
		exec('btrfs subvolume list  -puqcgaR '.$line,$vol);
		$btrfs_path = NULL ;

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

	 
	
ksort($btrfs_list,SORT_NATURAL ) ;
return($btrfs_list) ;

}
