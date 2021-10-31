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

function make_button($text, $function, $entry,$disabled="") {
#	global $paths, $Preclear , $loaded_vhci_hcd, $usbip_cmds_exist ;

	$button = "<span><button  onclick='%s(\"%s\")' class='mount' context='%s' role='%s' %s ><i class='%s'></i>%s</button></span>";

#	if ($loaded_vhci_hcd == "0")
#		{
		#	$disabled = "disabled <a href=\"#\" title='"._("vhci_hcd module not loaded")."'" ;
	#	} else {
	#		$disabled = "enabled"; 
	#	}

	$context = "disk";
   $texts = _($text) ;
  
	$button = sprintf($button, $function ,$entry, "", 'attach', $disabled, 'fa fa-import', $texts);
   #"<button onclick='add_remote_host()'>"._('Add Remote System')."</button>";
	
	return $button;
}

$unraid = parse_plugin_cfg("dynamix",true);
$display = $unraid["display"];
global $btrfs_path, $btrfs_line ;
#snap_manager_log('snap task'.$_POST['table']) ;
switch ($_POST['table']) {
// sv = BTRFS Volumes Tab Tables  
// it = Initiator Tab Tables
// st = Status Tab Tables
// ft = Fileio Tab Tables
// lt = LUN Tab Tables 
// xt = Diag Tables
   case 'sv1':
   #$path    = unscript($_GET['path']??'');
   $urlpath    =  $_GET['path'] ;

        echo "<thead><tr><td>"._("Volume/Sub Volume/Snapshot")."<td>"._('Snapshot prefix')."</td><td>"._('Send To Path')."</td><td>"._('Read only')."</td><td>"._('Remove')."</td><td>"._('Create')."</td><td>"._('Settings')."</td><td>"._('Schedule')."</td><td>"._('Browse')."</td>" ;

         echo "</tr></thead>";
         echo "<tbody><tr>";
        exec(' df -t btrfs --output="target" ',$targetcli);
            $list=build_list($targetcli) ;
                # echo "<tr><td>" ;var_dump( $btrfs_volumes) ;echo "</td></tr>" ;
                
                
                $ct = "<td title='"._("Remove Device configuration")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;'  onclick='Create Subvolume(\"{$key}\")'><i class='fa fa-remove hdd'></a>";

                foreach ($list as $key=>$vline) {
                  #echo "<tr><td>".preg_replace('/\]  +/',']',$key)."</td><td></td><td></td><td>".make_button("Create Subvolume", "create_subvolume" ,$vline["vol"]["vol"].'/')."</td>tr>";
                  echo "<tr><td>".preg_replace('/\]  +/',']',$key)."</td><td></td><td></td><td></td><td></td><td>".make_button("Create Subvolume", "create_subvolume" ,$key.'/')."</td><td><td></td></td><td><a href=\"Browse?dir=/mnt/user/".urlencode($name)."\"><i class=\"icon-u-tab\" title=\""._('Browse')." /mnt/user/".urlencode($name)."\"></i></a></td><tr>";
                  $ct = "<td title='"._("Remove Device configuration")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;'  onclick='delete_subvolume(\"{$key}\")'><i class='fa fa-remove hdd'></a>";
                  if ($vline != NULL) {
                  foreach ($vline as $snap=>$snapdetail) {
                  # echo "<tr><td>".preg_replace('/\]  +/',']</td><td>',$vline)."</td></tr>";
               if ($snapdetail["property"]["ro"] == "true" ) $checked = "checked" ; else $checked = "" ;
               if ($snapdetail["snap"] == false) {

                  echo "<tr><td>\t".$snap.'</td>' ;
                  #echo "<td>" ;
                  #echo '   <input type="checkbox" class="iscsi'.$dname.'" value="'.$iscsiset.'" </td>'  ;
           
                 
                  echo '<td>' ;
                  echo "</td>" ;

                                    echo '<td>' ;
                                    echo "</td>" ;

                  $remove = $snapdetail["vol"]."/".$snap ;
                  $path=$snapdetail["vol"].'/'.$snap ; 
                  
                  echo '<td><input type="checkbox" '.$checked.' onclick="OnChangeCheckbox (this)" value="'.$path.'">'."</td>" ;
 
                  echo "<td title='"._("Delete Subvolume")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;'  onclick='delete_subvolume(\"{$remove}\")'><i class='fa fa-remove hdd'></a>" ;
                  $mpoint			.= "<i class='fa fa-pencil partition-hdd'></i><a title='"._("Change Disk Mount Point")."' class='exec' onclick='chg_mountpoint(\"{$partition['serial']}\",\"{$partition['part']}\",\"{$device}\",\"{$partition['fstype']}\",\"{$mount_point}\",\"{$disk_label}\");'>{$mount_point}</a>";
		            $mpoint			.= "{$rm_partition}</span>";
                  $subvol=$path.'{YMD}' ;
                  $parm="{$path}\",\"{$subvol}" ;
                  
                  echo "</td><td> ".make_button("Create Snapshot", "create_snapshot", $parm)."</td>" ;
                  echo "<td><a href=\"Snapshots/SnapshotEditSettings\"><i class='fa fa-cog' title=\""._('Settings')." /mnt/user/".urlencode($path)."\"></i></a></td>" ;
                  echo "<td><a href=\"Snapshots/Browse?dir=".urlencode($path)."\"><i class='fa fa-clock-o' title=\""._('Schedule')." /mnt/user/".urlencode($path)."\"></i></a></td>" ;
                  echo "<td><a href=\"Browse?dir=".urlencode($path)."\"><i class=\"icon-u-tab\" title=\""._('Browse')." ".urlencode($path)."\"></i></a></td></tr>";
               } else {

                  echo "<tr><td>\t\t".$snap.'</td>' ;
                  echo '<td>' ;
                  #echo '<td><input type="text" style="width: 150px;" name="'.$iscsinickname.'" placeholder="Send Path" ' ;
                  if ($device["name"] != "") echo 'value="'.$device["nickname"].'" ' ;
                  echo "</td>" ;
                  echo '<td>' ; echo "</td>" ;
                  $remove = $snapdetail["vol"]."/".$snap ;
                  $path=$snapdetail["vol"].'/'.$snap ;
                  echo '<td><input type="checkbox"'.$checked.' onclick="OnChangeCheckbox (this)" value="'.$path.'">'."</td>" ;

                  echo "<td title='"._("Delete Snapshot")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;'  onclick='delete_snapshot(\"{$remove}\")'><i class='fa fa-remove hdd'></a>" ;
                  echo '</td>' ;
                  $dftsend = "/mnt/VMs/test" ;
                  $parm="{$path}\",\"{$dftsend}" ;
                  echo "</td><td> ".make_button("Send/Receive", "send_snapshot", $parm)."</td><td></td><td></td>" ;
                  #<i class='fa fa-usb' aria-hidden=true></i>
               
                  echo "<td><a href=\"Snapshots/Browse?dir=".urlencode($path)."\"><i class=\"icon-u-tab\" title=\""._('Browse')." /mnt/user/".urlencode($path)."\"></i></a></td></tr>";
               }
            }
              } else {
                 echo "<tr><td></td><td></td><td></td><td>"._("No Subvolumes defined")."</td><td></td><td></td><td></td><td></td><td></td></tr>" ;
              }}
       #echo "<tr><td>" ;
       #var_dump($btrfs_paths) ;
       #echo "</td></tr>" ;
       
       break;

case 'sv2':
   #$path    = unscript($_GET['path']??'');
   $urlpath    =  $_GET['path'] ;

   $config_file = $GLOBALS["paths"]["subvol_settings"];
	$volsettings = @parse_ini_file($config_file, true);
      
   echo "<thead><tr><td>"._("Volume/Sub Volume/Snapshot")."<td>"._('Snapshot prefix')."</td><td>"._('Send To Path')."</td><td>"._('Read only')."</td><td>"._('Remove')."</td><td>"._('Create')."</td><td>"._('Settings')."</td><td>"._('Schedule')."</td><td>"._('Browse')."</td>" ;

   echo "</tr></thead>";
   echo "<tbody><tr>";
   exec(' df -t btrfs --output="target" ',$targetcli);
   $list=build_list3($targetcli) ;
   # echo "<tr><td>" ;var_dump( $btrfs_volumes) ;echo "</td></tr>" ;

            
            
   $ct = "<td title='"._("Remove Device configuration")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;'  onclick='Create Subvolume(\"{$key}\")'><i class='fa fa-remove hdd'></a>";

   foreach ($list as $key=>$vline) {
      #echo "<tr><td>".preg_replace('/\]  +/',']',$key)."</td><td></td><td></td><td>".make_button("Create Subvolume", "create_subvolume" ,$vline["vol"]["vol"].'/')."</td>tr>";
      echo "<tr><td>".preg_replace('/\]  +/',']',$key)."</td><td></td><td></td><td></td><td></td><td>".make_button("Create Subvolume", "create_subvolume" ,$key.'/')."</td><td><td></td></td><td><a href=\"Browse?dir=/mnt/user/".urlencode($name)."\"><i class=\"icon-u-tab\" title=\""._('Browse')." /mnt/user/".urlencode($name)."\"></i></a></td><tr>";
      $ct = "<td title='"._("Remove Device configuration")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;'  onclick='delete_subvolume(\"{$key}\")'><i class='fa fa-remove hdd'></a>";
      if ($vline != NULL) {
         foreach ($vline as $snapkey=>$snapdetail) {
         # echo "<tr><td>".preg_replace('/\]  +/',']</td><td>',$vline)."</td></tr>";
         if ($snapdetail["property"]["ro"] == "true" ) $checked = "checked" ; else $checked = "" ;
        
         $snap=$snapdetail["short_vol"] ;
         echo "<tr><td>\t".$snap.'</td>' ;
         #echo "<td>" ;
         #echo '   <input type="checkbox" class="iscsi'.$dname.'" value="'.$iscsiset.'" </td>'  ;
         $remove = $snapdetail["vol"]."/".$snap ;
         $path=$snapdetail["vol"].'/'.$snap ; 

      if (isset($volsettings[$path])) {
         $subvoldft = $volsettings[$path]["default"] ;
      } else { $subvoldft = _("Undefined") ;}
         echo '<td>' ;
         echo $subvoldft ;
         echo "</td>" ;

         if (isset($volsettings[$path])) {
            $subvolsendto = $volsettings[$path]["sendto"] ;
         } else { $subvolsendto = _("Undefined") ;}
         echo '<td>' ;
         echo $subvolsendto ;
         echo "</td>" ;


      
         echo '<td><input type="checkbox" '.$checked.' onclick="OnChangeCheckbox (this)" value="'.$path.'">'."</td>" ;

         echo "<td title='"._("Delete Subvolume")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;'  onclick='delete_subvolume(\"{$remove}\")'><i class='fa fa-remove hdd'></a>" ;
         $mpoint			.= "<i class='fa fa-pencil partition-hdd'></i><a title='"._("Change Disk Mount Point")."' class='exec' onclick='chg_mountpoint(\"{$partition['serial']}\",\"{$partition['part']}\",\"{$device}\",\"{$partition['fstype']}\",\"{$mount_point}\",\"{$disk_label}\");'>{$mount_point}</a>";
         $mpoint			.= "{$rm_partition}</span>";
         if ($subvoldft != _("Undefined")) {
            $subvol=$subvoldft ;
         } else {
         $subvol=$path ;
         }
         $parm="{$path}\",\"{$subvol}" ;
      
      echo "</td><td> ".make_button("Create Snapshot", "create_snapshot", $parm)."</td>" ;
      echo "<td><a href=\"/Snapshots/SnapshotEditSettings?s=".urlencode($path)."\"><i class='fa fa-cog' title=\""._('Settings').$path."\"></i></a></td>" ;
      echo "<td></td><td><a href=\"Browse?dir=".urlencode($path)."\"><i class=\"icon-u-tab\" title=\""._('Browse')." ".$path."\"></i></a></td></tr>";

      # Show Schedule Slots upto 10.
      foreach(get_subvol_schedule_slots($path) as $slot=>$slotdetail) {

       echo "<tr><td>\t  Schedule Slot: {$slot}</td>" ;

       if (isset($slotdetail["subvolprefix"]) && $slotdetail["subvolprefix"] != "" ) {
         $slotsubvoldft = $slotdetail["subvolprefix"];
      } else { $slotsubvoldft = _("Default") ;}
         echo '<td>' ;
         echo $slotsubvoldft ;
         echo "</td>" ;

         if (isset($slotdetail["subvolsendto"]) && $slotdetail["subvolsendto"] != "") {
            $slotsubvolsendto = $slotdetail["subvolsendto"] ;
         } else { $slotsubvolsendto = _("Default") ;}
         echo '<td>' ;
         echo $slotsubvolsendto ;
         echo "</td>" ;
         echo "<td></td>" ;
       echo  "<td title='"._("Remove Schedule Slot")." {$slot}'><a style='color:#CC0000;font-weight:bold;cursor:pointer;'  onclick='delete_schedule_slot(\"{$path}\",\"{$slot}\")'><i class='fa fa-remove hdd'></a>";



      # Set Orb Colour based on Schedule status, Green enabled, Red Disabled, Grey not definded.

      #$schedule_state=get_subvol_sch_config($path, "snapscheduleenabled") ;
      $schedule_state=$slotdetail["snapscheduleenabled"] ;
      switch($schedule_state)  {
         case 'yes' :
               $colour = "green" ; 
               $colour_lable="Enabled" ;
               $run_disabled = "" ;
               break ;
         case 'no' :   
            $colour = "red" ;
            $colour_lable="Disabled";
            $run_disabled = "disabled";
             break ;
         default :   
            $colour = "grey" ;
            $colour_lable="Undefined";
            $run_disabled = "disabled";
            break ;
      } 

      echo "<td>" ;
      $pid = file_exists('/var/run/snap'.urlencode($path).'.pid') ;
      #$pid =true ;
      if ($pid) {
         echo make_button("Running", "run_schedule", $parm, 'disabled') ;
      } else  echo make_button("Run Now", "run_schedule", "{$path}\",\"{$slot}", $run_disabled) ;
      echo "</td>" ;

      #$colour="grey" ;
      $seq=$slot ;
      echo "<td><a href=\"/Snapshots/SnapshotSchedule?s=".urlencode($path)."&seq=".urlencode($seq)."\"><i class='fa fa-clock-o' title=\""._('Schedule').$path."\"></i></a></td>" ;

      echo "<td><i class=\"fa fa-circle orb ".$colour."-orb middle\" title=\"".$colour_lable."\"></i>" ;
     # echo  "<a onclick='add_schedule_slot(\"{$path}\")'><i title='"._("Add Schedule Slot")." {$slot}' class='fa fa-plus'></a>";
      echo "<a href=\"/Snapshots/SnapshotSchedule?s=".urlencode($path)."&seq=".urlencode("99")."\"><i class='fa fa-plus' title=\""._('Add Schedule Slot')."\"></i></a>" ;
      echo "</td>" ;
      # echo "<td><a href=\"Browse?dir=".urlencode($path)."\"><i class=\"icon-u-tab\" title=\""._('Browse')." ".$path."\"></i></a></td></tr>";

      }
      echo "<td></td></tr>" ;

      $snapvol=$snap;
      $snapvol=str_replace( "/", "-", $snapvol) ;
      $toggle = "<span class='exec toggle-rmtip' snapvol='{$snapvol}'><i class='fa fa-minus-square fa-append'></i></span>" ;
      echo "<tr><td>\t".$snap.$toggle.' </td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>' ;
      
         
         foreach ($snapdetail["subvolume"] as $subvolname=>$subvoldetail) {
            if ($subvoldetail["property"]["ro"] == "true" ) $checked = "checked" ; else $checked = "" ;
            #(! $disk['show_partitions']) || $disk['partitions'][0]['pass_through'] ? $style = "style='display:none;'" : $style = "";
            $style = "style='display:none;'" ;
            $style ="" ;
           # echo "<tr class=toggle-parts toggle-snaps-".basename($snapvol)."' name='toggle-snaps-".basename($snapvol)."' $style><td>\t\t".$subvolname.'</td>' ;
            #$style = "style='display:none;' " ;
            $hostport = $snapvol ;
            echo "<tr class='toggle-parts toggle-rmtip-".$hostport."' name='toggle-rmtip-".$hostport."'".$style.">" ;
            echo "<td>\t\t".$subvolname."</td><td></td>" ;
            #echo '<td><input type="text" style="width: 150px;" name="'.$iscsinickname.'" placeholder="Send Path" ' ;
            if ($subvoldetail["incremental"] != "" ) echo 'Parent:'.$subvoldetail["incremental"] ;
            echo "</td>" ;
            echo '<td>' ; echo "</td>" ;
            $remove = $subvoldetail["vol"]."/".$subvolname ;
            $path=$subvoldetail["vol"].'/'.$subvolname ;
            echo '<td><input type="checkbox"'.$checked.' onclick="OnChangeCheckbox (this)" value="'.$path.'">'."</td>" ;

            echo "<td title='"._("Delete Snapshot")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;'  onclick='delete_snapshot(\"{$remove}\")'><i class='fa fa-remove hdd'></a>" ;
            echo '</td>' ;
            $dftsend = "/mnt/VMs/test" ;
            if ($subvolsendto != _("Undefined")) {
               $dftsend=$subvolsendto ;
            } else {
            $dftsend=$path ;
            }
            $parm="{$path}\",\"{$dftsend}" ;
          #  echo "</td><td> ".make_button("Send", "send_snapshot", $parm).make_button("Send Incremental", "send_inc_snapshot", $parm)."</td><td></td><td></td>" ;
            echo "</td><td> ".make_button("Send", "send_snapshot", $parm)."</td><td></td><td></td>" ;
            #<i class='fa fa-usb' aria-hidden=true></i>
         
            echo "<td><a href=\"Snapshots/Browse?dir=".urlencode($path)."\"><i class=\"icon-u-tab\" title=\""._('Browse')." /mnt/user/".urlencode($path)."\"></i></a></td></tr>";
         }
      }
         } else {
            echo "<tr><td></td><td></td><td></td><td>"._("No Subvolumes defined")."</td><td></td><td></td><td></td><td></td><td></td></tr>" ;
         }   
   }
   #echo "<tr><td>" ;
   #var_dump($btrfs_paths) ;
   #echo "</td></tr>" ;
   
   break;
      

       case 'db1':
        
       # exec(' cat /mnt/cache/appdata/snapcmd/dflist ',$targetcli);
        exec(' df -t btrfs --output="target" ',$targetcli);
          
            $list = @parse_ini_file("/tmp/snapshots/config/subvolsch.cfg", true) ;
          $list=get_snapshots("/mnt/cache/vol") ;
          $list=build_list3($targetcli) ;
          $config_file = $GLOBALS["paths"]["subvol_schedule"];
          $config_file_json = $GLOBALS["paths"]["subvol_schedule.json"];
          $config = @parse_ini_file($config_file, true);
          $list= json_decode(file_get_contents($config_file_json), true) ;
           echo "<tr><td>" ;
           var_dump(($list)) ;
           echo "</td></tr>" ;
           $list=build_list2($targetcli) ;
           echo "<tr><td>" ;
           var_dump($config) ;
           echo "</td></tr>" ;
           break;

      case 'run_schedule':
         $subvol = urldecode(($_POST['subvol']));
         $slot = urldecode(($_POST['slot']));
         exec('/usr/local/emhttp/plugins/snapshots/include/snapping.php "'.$subvol.'" "'.$slot.'" > /dev/null 2>&1 ', $result, $error) ;
         #if
         snap_manager_log('Manual Run "'.$subvol.'" '.$error.' '.$result[0]) ;
         echo json_encode(TRUE);
         break;

         case 'delete_schedule_slot':
            $subvol = urldecode(($_POST['subvol']));
            $slot = urldecode(($_POST['slot']));
            $config_file_json = $GLOBALS["paths"]["subvol_schedule.json"];
            $config =  @json_decode(file_get_contents($config_file_json) , true);
            
            unset($config[$subvol][$slot]) ;
            save_json_file($config_file_json, $config) ;
            #if
            snap_manager_log('Removed Schedule Slot "'.$subvol.'" '.$slot.' '.$error.' '.$result[0]) ;
            echo json_encode(TRUE);
            break;
           
        
         case 'delete_subvolume':
            $subvol = urldecode(($_POST['subvol']));
            exec('btrfs subvolume delete '.escapeshellarg($subvol), $result, $error) ;
            #if
            snap_manager_log('btrfs subvolume delete '.$subvol.' '.$error.' '.$result[0]) ;
            echo json_encode(TRUE);
            break;   

      case 'create_subvolume':
         $subvol = urldecode(($_POST['subvol']));
         exec('btrfs subvolume create '.escapeshellarg($subvol), $result, $error) ;
         snap_manager_log('btrfs subvolume create '.$subvol.' '.$error.' '.$result[0]) ;
         echo json_encode(TRUE);
         break;

      case 'create_snapshot':
           $snapshot = urldecode(($_POST['snapshot']));
           $subvol = urldecode(($_POST['subvol']));
           $readonly = urldecode(($_POST['readonly']));
           if ($readonly == "true")  $readonly = "-r" ; else $readonly="" ;
           $ymd = date('YmdHis', time());
           $snapshoty = str_replace("{YMD}", $ymd, $snapshot);
           exec('btrfs subvolume snapshot '.$readonly.' '.escapeshellarg($subvol).' '.escapeshellarg($snapshoty), $result, $error) ;
           snap_manager_log('btrfs snapshot create '.$snapshot.' '.$error.' '.$result[0]) ;
           echo json_encode(TRUE);
           break;

           case 'send_snapshot':
            $snapshot = urldecode(($_POST['snapshot']));
            $subvol = urldecode(($_POST['subvol']));
            exec('btrfs send '.$subvol.' | btrfs receive '.escapeshellarg($snapshot), $result, $error) ;
            snap_manager_log('btrfs snapshot send '.$snapshot.' '.$error.' '.$result[0]) ;
            echo json_encode(TRUE);
            break;

           case 'change_ro':
            $checked = urldecode(($_POST['checked']));
            $path = urldecode(($_POST['path']));
            snap_manager_log('btrfs property set '.$path) ;
            exec('btrfs property set '.escapeshellarg($path).' ro '.escapeshellarg($checked), $result, $error) ;
            snap_manager_log('btrfs property set '.$path.' '.$checked.' '.$error.' '.$result[0]) ;
            echo json_encode(TRUE);
            break;

            case 'updatedefault':
               $vol = urldecode(($_POST['vol']));
               $path = urldecode(($_POST['path']));
               set_subvol_config($vol, "default", $path) ;
               snap_manager_log('btrfs update default path '.$vol ) ;
               echo json_encode(TRUE);
               break;

               case 'updatesendto':
                  $vol = urldecode(($_POST['vol']));
                  $path = urldecode(($_POST['path']));
                 set_subvol_config($vol, "sendto", $path) ;
                  snap_manager_log('btrfs update sendto path ' );
                  echo json_encode(TRUE);
                  break;

               case 'applySchedule':
                     $schedules = $_POST['schedule'];
                     
                     foreach ($schedules as $schedule) {
                       $script = str_replace('"',"",$schedule[0]);
                       $scriptSchedule['script'] = $script;
                       $scriptSchedule['frequency'] = $schedule[1];
                       $scriptSchedule['id'] = $schedule[2];
                       $scriptSchedule['custom'] = $schedule[3];
                       $newSchedule[$script] = $scriptSchedule;
                       
                       if ( $scriptSchedule['frequency'] == "custom" && $scriptSchedule['custom'] ) {
                         $cronSchedule .= trim($scriptSchedule['custom'])." /usr/local/emhttp/plugins/snapshots/include/snapping.php $vol > /dev/null 2>&1\n";
                       }
                     }
                     file_put_contents("/boot/config/plugins/user.scripts/schedule.json",json_encode($newSchedule,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                     file_put_contents("/tmp/user.scripts/schedule.json",json_encode($newSchedule,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                     if ( $cronSchedule ) {
                       $cronSchedule ="# Generated cron schedule for user.scripts\n$cronSchedule\n";
                       file_put_contents("/boot/config/plugins/user.scripts/customSchedule.cron",$cronSchedule);
                     } else {
                       @unlink("/boot/config/plugins/user.scripts/customSchedule.cron");
                     }
                     exec("/usr/local/sbin/update_cron");
                     
                     echo "Schedule Applied";
                     break;
       

    
}

/*
function remove_vm_mapping($source) {
	$config_file = $GLOBALS["paths"]["vm_mappings"];;
	$config = @parse_ini_file($config_file, true);
	if ( isset($config[$source]) ) {
		usb_manager_log("Removing configuration '$source'.");
	}	
	unset($config[$source]);
	save_ini_file($config_file, $config);
	return (! isset($config[$source])) ? TRUE : FALSE;
	}
   */
