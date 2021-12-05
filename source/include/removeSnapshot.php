<?PHP
/* Copyright 2020,Simon Fairweather

 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?
// add translations
$_SERVER['REQUEST_URI'] = '';
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

require_once "$docroot/plugins/$plugin/include/lib.php";
require_once("webGui/include/Helpers.php");
?>
<!DOCTYPE html>
<html <?=$display['rtl']?>lang="<?=strtok($locale,'_')?:'en'?>">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta http-equiv="Content-Security-Policy" content="block-all-mixed-content">
<meta name="format-detection" content="telephone=no">
<meta name="viewport" content="width=1600">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="same-origin">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-fonts.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-popup.css")?>">
<style>
span.key{width:104px;display:inline-block;font-weight:bold}
span.key.link{text-decoration:underline;cursor:pointer}
div.box{margin-top:8px;line-height:30px;margin-left:40px}
div.closed{display:none}
</style>
<script src="<?autov('/webGui/javascript/translate.'.($locale?:'en_US').'.js')?>"></script>
</head>
<body>
<div class="box">
<div></div>

</div>
<?


    $new = $_GET["Snaps"] ;
    $csrf_token = $_GET["csrf_token"] ;
    $newe=$x=explode(";", $new) ;
    $previqn=$prevtgt="" ;
    $cmd="" ;
    $c = count($newe) -1 ;
    $i = $ii = 0 ;
    do {
       $snap=$newe[$i] ;

       echo '<div><div><span class="key"></span>';
        print("Snapshot:".$snap)  ; 
        $ii++ ; 
    
    $i=$i+1 ;
    } while ($i<$c) ;

    echo '<input type="hidden" id="snaps" name="snaps" value="'.$new.'"' ;

?>
</div>
<div style="margin-top:24px;margin-bottom:12px"><span class="key"></span>
<input type="button" value="<?=_('Cancel')?>" onclick="top.Shadowbox.close()">
<input type="button" value="<?=_('Confirm')?>" onclick="RemoveSnap()">


</div></div>

<script type="text/javascript" src="<?autov('/webGui/javascript/dynamix.js')?>"></script>
<script>
function RemoveSnap(){
    var deletesnaps = document.getElementById('snaps').value ;
    
    
    $.post( "/plugins/snapshots/include/snapshots.php", { table:"delete_subvolume_list",snaps:deletesnaps,csrf_token:'<?=$csrf_token?>' } )
    .done(function(d) {
        parent.window.location.reload();
    }
  );
   
}

</script>
</body>
</html>
