#!/usr/bin/php
<?PHP
/* Copyright 2020-2020, Simon Fairweather
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
$plugin = "snapshots";
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
$translations = file_exists("$docroot/webGui/include/Translations.php");

if ($translations) {
	/* add translations */
	$_SERVER['REQUEST_URI'] = 'snapshots';
	require_once "$docroot/webGui/include/Translations.php";
} else {
	/* legacy support (without javascript) */
	$noscript = true;
	require_once "$docroot/plugins/$plugin/include/Legacy.php";
}

require_once "plugins/snapshots/include/lib.php";
require_once("webGui/include/Helpers.php");
$arg1 = $argv[1];
$arg2 = $argv[2];
$dummyrun = false ;
$dummyrundel = true ;


/*

Start processing Snapshots read config.

Options

[/mnt/cache/snaps]
snapscheduleenabled = "yes"
snapSchedule = 1
hour1 = 13
min = 0
hour2 = "/1"
snaplogging = "yes"
snapsend = "local"
remotehost = "Ip"
snapincremental = "yes"
mastersnap = "Previous"
vmselection = "Windows 10 test 6"
hostoption = "shutdown"
shutdowntimeout = 60
Removal = "yes"
days = 10
occurences = 5
snapsendopt = "none"
cron = "0 13 * * *"

*/


$subvol = $arg1 ;
$snapshot=get_subvol_config($subvol, "default") ;
$sendshot=get_subvol_config($subvol, "sendto") ;
$readonly = true ;
$slot= $arg2 ;

#$schedule = get_subvol_schedule($subvol) ;
$schedule = get_subvol_schedule_json($subvol,$slot) ;
$tag=$schedule["tag"] ;

$logging = $schedule['snaplogging'] ;

if ($schedule["subvolprefix"] != "") $snapshot = $schedule["subvolprefix"] ;
if ($schedule["subvolsendto"] != "") $sendshot = $schedule["subvolsendto"] ;

/* is logging set 
if yes log steps, otherwise just log start/stop.
*/

# Is snapping already running.
$pidfile= '/var/run/snap'.urlencode($subvol).'.pid' ;
$pid = file_exists($pidfile) ;
if ($pid) {
	snap_manager_log('Snapping process already running for'.$arg1." Slot:".$slot ) ;
	return(-1) ;
}

snap_manager_log('Start snapping process '.$arg1." Slot:".$slot ) ;

# Write pid file
file_put_contents($pidfile, "Pid") ;

/* Process VM Options if VMs defined 
Shutdown, Suspend, Hibernate.

*/
$vms=explode(",", $schedule["vmselection"]) ;
$vms_running = false ;

if ($libvirt_up) {
if ($vms[0] != "") {
$vm_state=array() ;
foreach($vms as $vm) {
	$vm_output=NULL ;
	exec ('virsh domstate "'.$vm.'"'." 2>&1", $vm_output) ;
	if ($logging == "yes") snap_manager_log("VM ".$vm.' State is :'.$vm_output[0]);
	$vm_state[$vm] = $vm_output[0] ;
}

$hostoption=$schedule["hostoption"] ;


foreach($vms as $vm) {
	if ($vm_state[$vm] == "shut off") continue ;
	$vms_running = true ;
	switch ($hostoption) {
		case "shutdown":
			exec ('virsh shutdown "'.$vm.'"'." 2>&1", $vm_output) ;
			if ($logging == "yes") snap_manager_log("VM ".$vm.' State is :'.$vm_output[0]." Being Shutdown");
			break ;
		case "suspend":
			exec ('virsh suspend "'.$vm.'"'." 2>&1", $vm_output) ;
			if ($logging == "yes") snap_manager_log("VM ".$vm.' State is :'.$vm_output[0]." Being Suspended");
			break ;
		case "hibernate":
			exec ('virsh dompmsuspend "'.$vm.'" disk'." 2>&1", $vm_output) ;
			if ($logging == "yes") snap_manager_log("VM ".$vm.' State is :'.$vm_output[0]." Being Hibernated");
			break ;
		}				

	}
} 
}
/* 
#root@computenode:/usr/local/emhttp/plugins/snapshots/include# virsh suspend "Windows 11"
#Domain 'Windows 11' suspended

#root@computenode:/usr/local/emhttp/plugins/snapshots/include# virsh resume "Windows 11"
#Domain 'Windows 11' resumed

#root@computenode:/usr/local/emhttp/plugins/snapshots/include# virsh shutdown "Windows 11"
#Domain 'Windows 11' is being shutdown

#root@computenode:/usr/local/emhttp/plugins/snapshots/include# virsh start "Windows 11"
#Domain 'Windows 11' started

*/
if ($vms != NULL && $vms_running) {
	
#if ($logging == "yes") snap_manager_log("Waiting For VMs to be processed. Sleep(".$schedule['shutdowntimeout'].")");
$waitcheck = ($schedule["shutdowntimeout"]/10) ;
if ($logging == "yes") snap_manager_log("Waiting For VMs to be processed. Sleep(".$schedule['shutdowntimeout'].") Checks $waitcheck");
for ($wait = 0 ; $wait <= $waitcheck ; $wait++)
{
	sleep(10) ;
	$vm_stillrunning = false ;
	foreach($vm_state as $vm=>$state) {
		$vm_output=NULL ;
		exec ('virsh domstate "'.$vm.'"'." 2>&1", $vm_output) ;
	    if ($vm_output[0]=="running") {$vm_stillrunning = true ; break ; }
		
	}


	if ($vm_stillrunning == false) {
		if ($logging == "yes") snap_manager_log("Waiting For VMs to be processed. $wait All VMs in correct state continue");
		break ;
	}
}
#sleep($schedule["shutdowntimeout"]) ;
}

if ($dummyrun == true)
  {
	# Process with no Actions but write logging.
	#if ($logging == "yes") snap_manager_log('Action') ;
  } else {
	# Perform Action.  
	
  }

/* Save Snapshot list before new snapshot. */

$parents=subvol_parents() ;
$parent=$parents[$subvol]["vol"].'/' ;
$lines[]=$parent ;
exec(' df -t btrfs --output="target" ',$df);

$list = build_list3($df) ;

$list=$list[$parents[$subvol]["vol"]][$subvol]["subvolume"] ;

$snaps_save=array_reverse($list) ;

/* Create Snapshot Readonly */

if ($readonly == "true")  $readonly = "-r" ; else $readonly="" ;

$DateTimeF = findText("{", "}", $snapshot) ;
if ($DateTimeF == "YMD") $DateTime = "YmdHis" ; else $DateTime = $DateTimeF ;

$ymd = date($DateTime, time());
$snapshoty = str_replace("{".$DateTimeF."}", $ymd, $snapshot);

if ($tag != "") { $snapshoty .= ".".$tag ;}  

if ($dummyrun == true)
  {
	/* Process with no Actions but write logging.*/
	if ($logging == "yes")	snap_manager_log('btrfs subvolume snapshot '.$readonly.' '.escapeshellarg($subvol).' '.escapeshellarg($snapshoty)) ;
  } else {
	exec('btrfs subvolume snapshot '.$readonly.' '.escapeshellarg($subvol).' '.escapeshellarg($snapshoty)." 2>&1", $result, $error) ;
	if ($logging == "yes") snap_manager_log('btrfs subvolume snapshot '.$readonly.' '.escapeshellarg($subvol).' '.escapeshellarg($snapshoty).' '.snap_error($error).' '.snap_return($result)) ;
  }

 /* Restart VMs */ 
if ($vms_running) {
 foreach($vms as $vm) {
	if ($vm_state[$vm] == "shut off") continue ;
	
	switch ($hostoption) {
		case "shutdown":
			exec ('virsh start "'.$vm.'"'." 2>&1", $vm_output) ;
			if ($logging == "yes") snap_manager_log("VM ".$vm.' State is :'.$vm_state[$vm]." Being Started");
			break ;
		case "suspend":
			if ($vm_state[$vm] == "paused") break ;
			exec ('virsh resume "'.$vm.'"'." 2>&1", $vm_output) ;
			if ($logging == "yes") snap_manager_log("VM ".$vm.' State is :'.$vm_state[$vm]." Being Resumed");
			break ;
		case "hibernate":
			exec ('virsh start "'.$vm.'"'." 2>&1", $vm_output) ;
			if ($logging == "yes") snap_manager_log("VM ".$vm.' State is :'.$vm_state[$vm]." Being Woken");
			break ;
		}				
 }	

} 


/* Send Snapshot */

# If incremental get 1st 

if ($schedule["snapincremental"] == "yes") {
	if (isset($schedule["mastersnap"])) $get_previous = $schedule["mastersnap"] ; else $get_previous = "" ;
	if ($get_previous == "") {
		if ($tag != "") $snaps_save_tag=remove_tags($snaps_save, $tag) ; else $snaps_save_tag=$snaps_save ;
		$get_previous_array = reset($snaps_save_tag) ;
		$get_previous =  $get_previous_array["vol"]."/".$get_previous_array["path"] ; 
	}

}
/* Send Local */

if ($schedule["snapsend"] == "local" || $schedule["snapsend"] == "both") 
  {
	  	$result = "" ;
		if ($schedule["snapincremental"] == "yes") $inc_cmd = "-p ".$get_previous." " ; else $inc_cmd = "" ; 
		exec('btrfs send '.$inc_cmd.$snapshoty.' | btrfs receive '.$sendshot." 2>&1" , $result, $error) ;
		snap_manager_log('btrfs snapshot send '.$inc_cmd.$snapshoty.' To '.$sendshot.' '.snap_error($error).' '.snap_return($result)) ;
  }


/* Send Remote */


if ($schedule["snapsend"] == "remote" || $schedule["snapsend"] == "both")  
{
		$host = $schedule["remotehost"] ;
		$result = array() ;
		if ($schedule["snapincremental"] == "yes") $inc_cmd = "-p ".$get_previous." " ; else $inc_cmd = "" ; 
	  exec('btrfs send '.$inc_cmd.$snapshoty.' | ssh root@'.$host.' "btrfs receive '.$sendshot.'" 2>&1' , $result, $error) ;
	
	  snap_manager_log('btrfs snapshot send remote '.$inc_cmd.$snapshoty.' To root@'.$host.' ' .$sendshot.' '.snap_error($error).' '.snap_return($result)) ;

}  


/* Delete old snaps */

if ($schedule["Removal"]!="no") {
$parents=subvol_parents() ;
$parent=$parents[$subvol]["vol"].'/' ;
$lines[]=$parent ;
exec(' df -t btrfs --output="target" ',$df);

$list = build_list3($df) ;

$list=$list[$parents[$subvol]["vol"]][$subvol]["subvolume"] ;

if ($tag != "") {

$snaps=remove_tags($list, $tag) ;
} else $snaps = $list ;
$snaps=array_reverse($snaps) ;

if ($logging == "yes") snap_manager_log('Tag: '.$tag.' Count: '.count($snaps).' Occurences: '.$schedule["occurences"].' Days: '.$schedule["days"].' Percentage:'.$schedule["volumeusage"]) ;




if ($schedule["days"] > 0) {
	$prevdatelog = date('l jS F (Y-m-d-H-i-s)', strtotime('-'.$schedule["days"].' days'));
	$prevdate = date('Y-m-d-H-i-s', strtotime('-'.$schedule["days"].' days'));
	if ($logging == "yes") snap_manager_log('Date to remove to:'.$prevdatelog) ;
	foreach($snaps as $path=>$snap) {
		if ($snap['odate'].'-'.$snap['otime'] > $prevdate )  continue ;
        $path = $parent.$path ;
		if ($schedule["Removal"] == "dry")
  		{
			/* Process with no Actions but write logging.*/
			if ($logging == "yes") snap_manager_log('Dry Run Delete by date '.$path) ;
  		} else {
			exec('btrfs subvolume delete '.escapeshellarg($path)." 2>&1", $result, $error) ;
			if ($logging == "yes") snap_manager_log('Deleted Snapshot by date: '.$path) ;
  		}
	}
}


$count = 0 ;


if ($schedule["occurences"] > 0)
	{
  	foreach($snaps as $path=>$snap) {
		if ($count < $schedule["occurences"] ) { $count++ ; continue ;}
        $path = $parent.$path ;
		if ($schedule["Removal"] == "dry")
  		{
			/* Process with no Actions but write logging.*/
			if ($logging == "yes") snap_manager_log('Dry Run Delete by occurence'.$path) ;
  		} else {
			exec('btrfs subvolume delete '.escapeshellarg($path)." 2>&1", $result, $error) ;
			if ($logging == "yes") snap_manager_log('Deleted Snapshot by occurence: '.$path) ;
  		}
		  $count++ ;
	}
}



if ($schedule["volumeusage"] > 0)
	{

	$dryrunsize =0;
	
	$freespace = disk_free_space($parents[$subvol]["vol"]) ;
	$totalspace = disk_total_space($parents[$subvol]["vol"]) ;
	$usedspace =  $spacepostdelete = ($totalspace - $freespace);
	
  	foreach($snaps as $path=>$snap) {
		$percent = "" ;
		$totalspace = disk_total_space($parents[$subvol]["vol"]) ;
		if ($schedule["Removal"] == "dry")
		{
			$percent = round(($spacepostdelete / $totalspace * 100) , 0) ;
		}
		else {
			$percentdf =array() ;
			#shell_exec("df | grep /mnt/cache | awk '/[0-9]%/{print $(NF-1)}' | sed 's/%//'",$percentdf,$error)  ;	
			exec("df | grep ".$parents[$subvol]["vol"]." | awk '/[0-9]%/{print $(NF-1)}' | sed 's/%//'"  ,$percentdfv,$error)  ;	 
			
			$freespace = disk_free_space($parents[$subvol]["vol"]) ;
			$usedspace = ($totalspace - $freespace);
			$percent = round(($usedspace / $totalspace * 100) , 0) ;
			$percentdf=$percentdfv[0] ;

		}

		if ($percent < $schedule["volumeusage"] )  break ;
        $path = $parent.$path ;
		if ($schedule["Removal"] == "dry")
  		{
			$file_removed=filesize($path) ;
			/* Process with no Actions but write logging.*/
			if ($logging == "yes") snap_manager_log('Dry Run Delete by usage'.$path." Actual %:".$percent) ;
			$spacepostdelete -= $file_removed ;
  		} else {
			#exec('btrfs subvolume delete '.escapeshellarg($path)." 2>&1", $result, $error) ;
			if ($logging == "yes") snap_manager_log('Deleted Snapshot by usage: '.$path." Actual %:".$percent." df %: ".$percentdf) ;
  		}
	}
}
}
sleep(10) ;
unlink($pidfile) ;
snap_manager_log('End snapping process '.$arg1 ) ;
?>