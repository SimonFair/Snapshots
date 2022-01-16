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
	$button = "<span><button  onclick='%s(\"%s\")' class='mount' context='%s' role='%s' %s ><i class='%s'></i>%s</button></span>";
	$context = "disk";
   $texts = _($text) ;
	$button = sprintf($button, $function ,$entry, "", 'attach', $disabled, 'fa fa-import', $texts);
	return $button;
}

$unraid = parse_plugin_cfg("dynamix",true);
$display = $unraid["display"];
global $btrfs_path, $btrfs_line ;
#snap_manager_log('snap task'.$_POST['table']) ;
switch ($_POST['table']) {

// sv = BTRFS Volumes Tab Tables  
// zv = ZFS Tab Tables

  

case 'sv2':
  
   $urlpath    =  $_GET['path'] ;

   $config_file = $GLOBALS["paths"]["subvol_settings"];
	$volsettings = @parse_ini_file($config_file, true);
      
   echo "<thead><tr><td>"._("Volume/Sub Volume/Snapshot")."<td>"._('Snapshot prefix')."</td><td>"._('Send To Path')."</td><td>"._('Read only')."</td><td>"._('Remove')."</td><td>"._('Create')."</td><td>"._('Settings')."</td><td>"._('Schedule')."</td><td>"._('Browse')."</td>" ;

   echo "</tr></thead>";
   echo "<tbody><tr>";
   exec(' df -t btrfs --output="target" ',$targetcli);
   $i=1 ;
   $list=build_list3($targetcli) ;
            
            
   $ct = "<td title='"._("Remove Device configuration")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;'  onclick='Create Subvolume(\"{$key}\")'><i class='fa fa-remove hdd'></a>";

   foreach ($list as $key=>$vline) {
      #echo "<tr><td>".preg_replace('/\]  +/',']',$key)."</td><td></td><td></td><td>".make_button("Create Subvolume", "create_subvolume" ,$vline["vol"]["vol"].'/')."</td>tr>";
      ksort($vline) ;
      echo "<tr><td>".preg_replace('/\]  +/',']',$key)."</td><td></td><td></td><td></td><td></td><td>".make_button("Create Subvolume", "create_subvolume" ,$key.'/')."</td><td><td></td></td><td><a href=\"Browse?dir=/mnt/user/".urlencode($name)."\"><i class=\"icon-u-tab\" title=\""._('Browse')." /mnt/user/".urlencode($name)."\"></i></a></td><tr>";
      $ct = "<td title='"._("Remove Subvolume")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;'  onclick='delete_subvolume(\"{$key}\")'><i class='fa fa-remove hdd'></a>";
      if ($vline != NULL) {
         foreach ($vline as $snapkey=>$snapdetail) {
        
         if ($snapdetail["property"]["ro"] == "true" ) $checked = "checked" ; else $checked = "" ;
        
         $snap=$snapdetail["short_vol"] ;
         $savevol = $snapdetail["vol"] ;
         $remove = $snapdetail["vol"]."/".$snap ;
         $path=$snapdetail["vol"].'/'.$snap ; 

         if ($snap != "~RECEIVED" && $snap!= "~INCREMENTAL") {
         echo "<tr><td>\t".$snap.'</td>' ;


      if (isset($volsettings[$path]["default"])) {
         $subvoldft = $volsettings[$path]["default"] ;
      } else { $subvoldft = _("Undefined") ;}
         echo '<td>' ;
         echo $subvoldft ;
         echo "</td>" ;

         if (isset($volsettings[$path]["sendto"])) {
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
      

         $slots=get_subvol_schedule_slots($path) ;
         if ($slots == FALSE) {
            $slots=array() ; 

         }
         ksort($slots) ;
         $slotcount=count($slots) ;

      echo "</td><td> ".make_button("Create Snapshot", "create_snapshot", $parm)."</td>" ;
      echo "<td><a href=\"/Snapshots/SnapshotEditSettings?s=".urlencode($path)."\"><i class='fa fa-cog' title=\""._('Settings').$path."\"></i></a></td>" ;
      if ($slotcount<1) {
         echo "<td><i class=\"fa fa-circle orb grey-orb middle\" title=\"Undefined\"></i><a href=\"/Snapshots/SnapshotSchedule?s=".urlencode($path)."&seq=".urlencode("99")."\"><i class='fa fa-plus' title=\""._('Add Schedule Slot')."\"></i></a></td>" ;
      } else {
      echo "<td></td>" ;
      }
      echo "<td><a href=\"Browse?dir=".urlencode($path)."\"><i class=\"icon-u-tab\" title=\""._('Browse')." ".$path."\"></i></a></td></tr>";

  
      # Show Schedule Slots upto 10.
      foreach($slots as $slot=>$slotdetail) {

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
;
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

      if ($pid) {
         echo make_button("Running", "run_schedule", $parm, 'disabled') ;
      } else  echo make_button("Run Now", "run_schedule", "{$path}\",\"{$slot}", $run_disabled) ;
      echo "</td>" ;


      $seq=$slot ;
      echo "<td><a href=\"/Snapshots/SnapshotSchedule?s=".urlencode($path)."&seq=".urlencode($seq)."\"><i class='fa fa-clock-o' title=\""._('Schedule').$path."\"></i></a></td>" ;

      echo "<td><i class=\"fa fa-circle orb ".$colour."-orb middle\" title=\"".$colour_lable."\"></i>" ;

      if ($slotcount <7) echo "<a href=\"/Snapshots/SnapshotSchedule?s=".urlencode($path)."&seq=".urlencode("99")."\"><i class='fa fa-plus' title=\""._('Add Schedule Slot')."\"></i></a>" ;
      echo "</td>" ;
      echo "<td></td></tr>" ;
      }
   }

      $snapvol=$snap;
      $snapvol=$path ;
      $snapvol=str_replace( "/", "-", $snapvol) ;
      $snapvol=str_replace( "~", "-", $snapvol) ;
      
      if ($_COOKIE[$snapvol] == "false" || !isset($_COOKIE[$snapvol])) {
         $toggle = "<span class='exec toggle-rmtip' snapvol='{$snapvol}'><i class='fa fa-plus-square fa-append'></i></span>" ;
         if (!isset($_COOKIE[$snapvol])) setcookie($snapvol, 'true' ,  3650, '/' );
      } else {
      $toggle = "<span class='exec toggle-rmtip' snapvol='{$snapvol}'><i class='fa fa-minus-square fa-append'></i></span>" ;
      }
      echo "<tr><td>\t  ".$snap._("(Snapshots)").$toggle.' </td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>' ;
      
        
         foreach ($snapdetail["subvolume"] as $subvolname=>$subvoldetail) {
            if ($subvoldetail["property"]["ro"] == "true" ) $checked = "checked" ; else $checked = "" ;

            $style = "style='display:none;'" ;
            $style ="" ;
      
            if ($_COOKIE[$snapvol] == "true" || !isset($_COOKIE[$snapvol])) $style = "  " ; else $style = " hidden "  ;
            $hostport = $snapvol ;
            echo "<tr class='toggle-parts toggle-rmtip-".$hostport."' name='toggle-rmtip-".$hostport."'".$style.">" ;
            echo "<td>\t\t".$subvolname."</td><td></td>" ;

            if ($subvoldetail["incremental"] != "" ) echo 'Parent:'.$subvoldetail["incremental"] ;
            echo "</td>" ;
            echo '<td>' ; echo "</td>" ;
            $remove = $subvoldetail["vol"]."/".$subvolname ;
            $path=$subvoldetail["vol"].'/'.$subvolname ;
            echo '<td><input type="checkbox"'.$checked.' onclick="OnChangeCheckbox (this)" value="'.$path.'">'."</td>" ;


            echo '<td><input type="checkbox" class="removes snaps".$i++." name="snapshot"  value="'.$remove.'" </td>'  ;
            echo '</td>' ;
            $dftsend = "/mnt/VMs/test" ;
            if ($subvolsendto != _("Undefined")) {
               $dftsend=$subvolsendto ;
            } else {
            $dftsend=$path ;
            }
            $parm="{$path}\",\"{$dftsend}" ;
            $parmpath = $subvoldetail["vol"] ;
            $parmvol = $subvoldetail["parent"];
            $parminc="{$path}\",\"{$dftsend}\",\"{$parmpath}\",\"{$parmvol}" ;

            echo "</td><td> ".make_button("Send", "send_snapshot", $parm)."</td><td></td><td></td>" ;
            #echo "</td><td> ".make_button("Send", "send_snapshot", $parm).make_button("Send Inc", "send_inc_snapshot", $parminc)."</td><td></td><td></td>" ;
         
            echo "<td><a href=\"Snapshots/Browse?dir=".urlencode($path)."\"><i class=\"icon-u-tab\" title=\""._('Browse')." /mnt/user/".urlencode($path)."\"></i></a></td></tr>";
         }
      }
         } else {
            echo "<tr><td></td><td></td><td></td><td>"._("No Subvolumes defined")."</td><td></td><td></td><td></td><td></td><td></td></tr>" ;
         }   
   }
   echo '<tr><td><br>';       
   echo '<input id="RmvSnaps" type="submit" disabled value="'._('Remove Selected Entries').'" onclick="removeSnapshot();" '.'>';
   echo '<span id="warning"></span>';
   echo '</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></td><td></td></tr>';
   echo <<<EOT
<script>
$("#sv2 input[type='checkbox']").change(function() {
var matches = document.querySelectorAll("." + this.className);
for (var i=0, len=matches.length|0; i<len; i=i+1|0) {
matches[i].checked = this.checked ? true : false;
}
$("#RmvSnaps").attr("disabled", false);
});
</script>
EOT;

   
   break;
      

       case 'db1':
        
      
        exec(' df -t btrfs --output="target" ',$targetcli);
          
            $list = @parse_ini_file("/tmp/snapshots/config/subvolsch.cfg", true) ;
          $list=get_snapshots("/mnt/cache/vol") ;
          $list=build_list3($targetcli) ;
          $config_file = $GLOBALS["paths"]["subvol_schedule"];
          $config_file_json = $GLOBALS["paths"]["subvol_schedule.json"];
          $config = @parse_ini_file($config_file, true);
          $list= json_decode(file_get_contents($config_file_json), true) ;
           echo "<tr><td>" ;

           echo "</td></tr>" ;
           $list=build_list3($targetcli) ;
           echo "<tr><td>" ;
           var_dump($list) ;
           echo "</td></tr>" ;
           break;

           case 'db2':
        
            exec('zfs list -H ',$targetcli);

            echo "</td></tr>" ;
            $list=build_list_zfs($targetcli) ;
            echo "<tr><td>" ;
            var_dump($list) ;
               
     
                   
 
                break;


           case 'zs1':
        

   $urlpath    =  $_GET['path'] ;

   $config_file = $GLOBALS["paths"]["subvol_settings"];
	$volsettings = @parse_ini_file($config_file, true);
      
   echo "<thead><tr><td>"._("Pool/Filesystem/Snapshot")."<td>"._('')."</td><td>"._('')."</td><td>"._('')."</td><td>"._('')."</td><td>"._('')."</td><td>"._('')."</td><td>"._('')."</td><td>"._('Browse')."</td>" ;

   echo "</tr></thead>";
   echo "<tbody><tr>";
   exec(' df -t btrfs --output="target" ',$targetcli);
   $list=build_list_zfs($targetcli) ;
   # echo "<tr><td>" ;var_dump( $btrfs_volumes) ;echo "</td></tr>" ;

            
            
   $ct = "<td title='"._("Remove Device configuration")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;'  onclick='Create Subvolume(\"{$key}\")'><i class='fa fa-remove hdd'></a>";

   foreach ($list as $key=>$vline) {
      
      #echo "<tr><td>".preg_replace('/\]  +/',']',$key)."</td><td></td><td></td><td>".make_button("Create Subvolume", "create_subvolume" ,$vline["vol"]["vol"].'/')."</td>tr>";
      #echo "<tr><td>".preg_replace('/\]  +/',']',$key)."</td><td></td><td></td><td></td><td></td><td>".make_button("Create Subvolume", "create_subvolume" ,$key.'/')."</td><td><td></td></td><td><a href=\"Browse?dir=/mnt/user/".urlencode($name)."\"><i class=\"icon-u-tab\" title=\""._('Browse')." /mnt/user/".urlencode($name)."\"></i></a></td><tr>";
      echo "<tr><td>".preg_replace('/\]  +/',']',$key)."</td><td>Size:".$vline["~config"]["SIZE"]."</td><td>Status :".$vline["~config"]["HEALTH"]."</td><td></td><td></td><td></td><td><td></td></td><td></td><tr>";
      #$ct = "<td title='"._("Remove Device configuration")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;'  onclick='delete_subvolume(\"{$key}\")'><i class='fa fa-remove hdd'></a>";
      if ($vline != NULL) {
         foreach ($vline as $snapkey=>$snapdetail) {
         # echo "<tr><td>".preg_replace('/\]  +/',']</td><td>',$vline)."</td></tr>";
         if ($snapdetail["property"]["ro"] == "true" ) $checked = "checked" ; else $checked = "" ;
        
         $snap=$snapkey ;
         $remove = $snapdetail["vol"]."/".$snap ;
         $path=$snapdetail["vol"].'/'.$snap ; 
         if ($snap == "~config") continue ;
         if ($snap != "~RECEIVED" && $snap!= "~INCREMENTAL") {
         echo "<tr><td>\t".$snap.'</td>' ;


      if (isset($volsettings[$path]["default"])) {
         $subvoldft = $volsettings[$path]["default"] ;
      } else { $subvoldft = _("Undefined") ;}
         echo '<td>' ;
       #  echo $subvoldft ;
         echo "</td>" ;

         if (isset($volsettings[$path]["sendto"])) {
            $subvolsendto = $volsettings[$path]["sendto"] ;
         } else { $subvolsendto = _("Undefined") ;}
         echo '<td>' ;
        # echo $subvolsendto ;
         echo "</td>" ;


      
        # echo '<td><input type="checkbox" '.$checked.' onclick="OnChangeCheckbox (this)" value="'.$path.'">'."</td>" ;

        # echo "<td title='"._("Delete Subvolume")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;'  onclick='delete_subvolume(\"{$remove}\")'><i class='fa fa-remove hdd'></a>" ;
         $mpoint			.= "<i class='fa fa-pencil partition-hdd'></i><a title='"._("Change Disk Mount Point")."' class='exec' onclick='chg_mountpoint(\"{$partition['serial']}\",\"{$partition['part']}\",\"{$device}\",\"{$partition['fstype']}\",\"{$mount_point}\",\"{$disk_label}\");'>{$mount_point}</a>";
         $mpoint			.= "{$rm_partition}</span>";
         if ($subvoldft != _("Undefined")) {
            $subvol=$subvoldft ;
         } else {
         $subvol=$path ;
         }
         $parm="{$path}\",\"{$subvol}" ;
      

         $slots=get_subvol_schedule_slots($path) ;
         if ($slots == FALSE) {
            $slots=array() ; 
         #  $slots["0"]["snapscheduleenable"] = "" ;
         }
         ksort($slots) ;
         $slotcount=count($slots) ;

      echo "</td><td></td>" ;
      #echo "</td><td> ".make_button("Create Snapshot", "create_snapshot", $parm)."</td>" ;
      #echo "<td><a href=\"/Snapshots/SnapshotEditSettings?s=".urlencode($path)."\"><i class='fa fa-cog' title=\""._('Settings').$path."\"></i></a></td>" ;
   #   if ($slotcount<1) {
    #     echo "<td><i class=\"fa fa-circle orb grey-orb middle\" title=\"Undefined\"></i><a href=\"/Snapshots/SnapshotSchedule?s=".urlencode($path)."&seq=".urlencode("99")."\"><i class='fa fa-plus' title=\""._('Add Schedule Slot')."\"></i></a></td>" ;
    #  } else {
    #  echo "<td></td>" ;
    #  }
    echo "<td></td><td></td>" ;
    echo "<td></td><td></td>" ;
      echo "<td><a href=\"Browse?dir=".urlencode($snapdetail["MOUNTPOINT"])."\"><i class=\"icon-u-tab\" title=\""._('Browse')." ".$snapdetail["MOUNTPOINT"]."\"></i></a></td></tr>";

  

      # Show Schedule Slots upto 10.
      foreach($slots as $slot=>$slotdetail) {

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
   /*   if ($pid) {
         echo make_button("Running", "run_schedule", $parm, 'disabled') ;
      } else  echo make_button("Run Now", "run_schedule", "{$path}\",\"{$slot}", $run_disabled) ; */
      echo "</td>" ;

      #$colour="grey" ;
      $seq=$slot ;
    #  echo "<td><a href=\"/Snapshots/SnapshotSchedule?s=".urlencode($path)."&seq=".urlencode($seq)."\"><i class='fa fa-clock-o' title=\""._('Schedule').$path."\"></i></a></td>" ;

    #  echo "<td><i class=\"fa fa-circle orb ".$colour."-orb middle\" title=\"".$colour_lable."\"></i>" ;
     # echo  "<a onclick='add_schedule_slot(\"{$path}\")'><i title='"._("Add Schedule Slot")." {$slot}' class='fa fa-plus'></a>";
      if ($slotcount <7) echo "<a href=\"/Snapshots/SnapshotSchedule?s=".urlencode($path)."&seq=".urlencode("99")."\"><i class='fa fa-plus' title=\""._('Add Schedule Slot')."\"></i></a>" ;
      echo "</td>" ;
      # echo "<td><a href=\"Browse?dir=".urlencode($path)."\"><i class=\"icon-u-tab\" title=\""._('Browse')." ".$path."\"></i></a></td></tr>";
      #echo "<td>{$slotcount}</td></tr>" ;
      echo "<td></td></tr>" ;
      }
   }

      $snapvol=$snap;
      $snapvol=$snapkey;
      $snapvol=str_replace( "/", "-", $snapvol) ;
      $snapvol=str_replace( "~", "-", $snapvol) ;
      if ($_COOKIE[$snapvol] == "false" || !isset($_COOKIE[$snapvol])) {
         $toggle = "<span class='exec toggle-rmtip' snapvol='{$snapvol}'><i class='fa fa-plus-square fa-append'></i></span>" ;
         if (!isset($_COOKIE[$snapvol])) setcookie($snapvol, 'true' ,  3650, '/' );
      } else {
      $toggle = "<span class='exec toggle-rmtip' snapvol='{$snapvol}'><i class='fa fa-minus-square fa-append'></i></span>" ;
      }
      echo "<tr><td>\t".$snap._("(Snapshots)").$toggle.' </td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>' ;
      
         
         foreach ($snapdetail["snapshots"] as $subvolname=>$subvoldetail) {
            
            if ($subvoldetail["property"]["ro"] == "true" ) $checked = "checked" ; else $checked = "" ;
            #(! $disk['show_partitions']) || $disk['partitions'][0]['pass_through'] ? $style = "style='display:none;'" : $style = "";
            $style = "style='display:none;'" ;
            $style ="" ;
           # echo "<tr class=toggle-parts toggle-snaps-".basename($snapvol)."' name='toggle-snaps-".basename($snapvol)."' $style><td>\t\t".$subvolname.'</td>' ;
            if ($_COOKIE[$snapvol] == "true" || !isset($_COOKIE[$snapvol])) $style = "  " ; else $style = " hidden "  ;
            $hostport = $snapvol ;
            echo "<tr class='toggle-parts toggle-rmtip-".$hostport."' name='toggle-rmtip-".$hostport."'".$style.">" ;
            echo "<td>\t\t".$subvolname."</td><td></td>" ;
            #echo '<td><input type="text" style="width: 150px;" name="'.$iscsinickname.'" placeholder="Send Path" ' ;
            if ($subvoldetail["incremental"] != "" ) echo 'Parent:'.$subvoldetail["incremental"] ;
            echo "</td>" ;
            echo '<td>' ; echo "</td>" ;
            $remove = $subvoldetail["vol"]."/".$subvolname ;
            $path=$subvoldetail["vol"].'/'.$subvolname ;
      #      echo '<td><input type="checkbox"'.$checked.' onclick="OnChangeCheckbox (this)" value="'.$path.'">'."</td>" ;

      #      echo "<td title='"._("Delete Snapshot")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;'  onclick='delete_snapshot(\"{$remove}\")'><i class='fa fa-remove hdd'></a>" ;
            echo '</td>' ;
            $dftsend = "/mnt/VMs/test" ;
            if ($subvolsendto != _("Undefined")) {
               $dftsend=$subvolsendto ;
            } else {
            $dftsend=$path ;
            }
            $parm="{$path}\",\"{$dftsend}" ;
          #  echo "</td><td> ".make_button("Send", "send_snapshot", $parm).make_button("Send Incremental", "send_inc_snapshot", $parm)."</td><td></td><td></td>" ;
            #echo "</td><td> ".make_button("Send", "send_snapshot", $parm).make_button("Send Inc", "send_inc_snapshot", $parm)."</td><td></td><td></td>" ;
           # echo "</td><td> ".make_button("Send", "send_snapshot", $parm)."</td><td></td><td></td>" ;
            echo "</td><td> </td><td></td><td></td>" ;
            echo "<td></td><td></td>" ;
            #<i class='fa fa-usb' aria-hidden=true></i>
         
            echo "<td><a href=\"Snapshots/Browse?dir=".$snapdetail["MOUNTPOINT"]."\"><i class=\"icon-u-tab\" title=\""._('Browse')." ".$snapdetail["MOUNTPOINT"]."\"></i></a></td></tr>";
         }
      }
         } else {
            echo "<tr><td></td><td></td><td></td><td>"._("No Subvolumes defined")."</td><td></td><td></td><td></td><td></td><td></td></tr>" ;
         }   
   }
   

   
   break;

      case 'run_schedule':
         $subvol = urldecode(($_POST['subvol']));
         $slot = urldecode(($_POST['slot']));
         exec('/usr/local/emhttp/plugins/snapshots/include/snapping.php "'.$subvol.'" "'.$slot.'" > /dev/null 2>&1 ', $result, $error) ;
    
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
            $cron="" ;
	      	$file=$subvol."Slot".$slot ;
	         parse_cron_cfg("snapshots", urlencode($file), $cron);
            #if
            snap_manager_log('Removed Schedule Slot "'.$subvol.'" '.$slot.' '.$error.' '.$result[0]) ;
            echo json_encode(TRUE);
            break;
           
        
         case 'delete_subvolume':
            $subvol = urldecode(($_POST['subvol']));
            exec('btrfs subvolume delete '.escapeshellarg($subvol), $result, $error) ;
          
            snap_manager_log('btrfs subvolume delete '.$subvol.' '.$error.' '.$result[0]) ;
            echo json_encode(TRUE);
            break;   

            case 'delete_subvolume_list':
               $snapslist = explode(";" ,urldecode($_POST['snaps']));
       
               foreach ($snapslist as $subvol) {
                  if ($subvol != "") {
                  $result=array() ;
               exec('btrfs subvolume delete '.escapeshellarg($subvol), $result, $error) ;
         
               snap_manager_log('btrfs subvolume delete '.$subvol.' '.$error.' '.snap_return($result)) ;
            }}
               echo json_encode(TRUE);
               break;      

      case 'create_subvolume':
         $subvol = urldecode(($_POST['subvol']));
         exec('btrfs subvolume create '.escapeshellarg($subvol)." 2>&1", $result, $error) ;
         snap_manager_log('btrfs subvolume create '.$subvol.' '.$error.' '.$result[0]) ;
         if ($error=="1") $error_rtn = false ; else $error_rtn=true ;
         echo json_encode(array("success"=>$error_rtn, "error"=>$result));
         break;

      case 'create_snapshot':
           $snapshot = urldecode(($_POST['snapshot']));
           $subvol = urldecode(($_POST['subvol']));
           $readonly = urldecode(($_POST['readonly']));
           if ($readonly == "true")  $readonly = "-r" ; else $readonly="" ;
           $DateTimeF = findText("{", "}", $snapshot) ;
           if ($DateTimeF == "YMD") $DateTime = "YmdHis" ; else $DateTime = $DateTimeF ;

           $ymd = date($DateTime, time());
           $snapshoty = str_replace("{".$DateTimeF."}", $ymd, $snapshot);

#           check_to_dir($snapshoty) ;
           $slashpos = substr(strrchr($snapshot,'/'), 1);
           $directory = substr($snapshot, 0, - strlen($slashpos));
           if (!is_dir($directory)) mkdir($directory, 0777, true) ;

           exec('btrfs subvolume snapshot '.$readonly.' '.escapeshellarg($subvol).' '.escapeshellarg($snapshoty)." 2>&1", $result, $error) ;
           snap_manager_log('btrfs snapshot create '.$snapshot.' '.$error.' '.$result[0]) ;
           if ($error=="1") $error_rtn = false ; else $error_rtn=true ;
           echo json_encode(array("success"=>$error_rtn, "error"=>$result));
           break;

           case 'send_snapshot':
            $snapshot = urldecode(($_POST['snapshot']));
            $subvol = urldecode(($_POST['subvol']));
            exec('btrfs send '.$subvol.' | btrfs receive '.escapeshellarg($snapshot)." 2>&1", $result, $error) ;
            snap_manager_log('btrfs snapshot send '.$snapshot.' '.$error.' '.$result[0]) ;
            if ($error=="1") $error_rtn = false ; else $error_rtn=true ;
            echo json_encode(array("success"=>$error_rtn, "error"=>$result));
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

                  case 'get_previous':
                     $current = $_POST['current'] ;  
                     $path = $_POST['path'] ;  
                     $vol = $_POST['vol'] ;
                     $parts = explode('.', $current);
                     if (count($parts) < 2)  $tag = "" ; else  $tag = array_pop($parts);
                     snap_manager_log('btrfs get previous tag '.$tag ) ;
                     
                     $targetcli=array() ;
                     exec(' df -t btrfs --output="target" ',$targetcli);
                     $list=build_list3($targetcli) ;
                     
                     if (isset($list[$path][$vol]["subvolume"])) $list2=$list[$path][$vol]["subvolume"] ; else $list2=NULL ;

                     #snap_manager_log('btrfs get previous list '.$list[$path] ) ;
          
                     if ($tag != "") $snaps_save_tag=remove_tags($list2, $tag) ; else $snaps_save_tag=$list2 ;

                   
                     if ($snaps_save_tag != "") {
                     $get_previous_array = reset($snaps_save_tag) ;
                     $get_previous =  $get_previous_array["vol"]."/".$get_previous_array["path"] ; 
                     } else $get_previous ="" ;
                     
                     #echo json_encode(htmlspecialchars( $get_previous)) ;
                     echo json_encode( $get_previous) ;
                     break;

                  /*   if ($get_previous == "") {
                        if ($tag != "") $snaps_save_tag=remove_tags($snaps_save, $tag) ; else $snaps_save_tag=$snaps_save ;
                        $get_previous_array = reset($snaps_save_tag) ;
                        $get_previous =  $get_previous_array["vol"]."/".$get_previous_array["path"] ; 
                     } */

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


