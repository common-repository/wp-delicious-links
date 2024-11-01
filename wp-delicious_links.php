<?php
/*
Plugin Name: Del.icio.us Links
Plugin URI: http://aclog.ionosfera.com/wordpress-plugins/delicious-links/
Description: This plugin provides integration with Del.icio.us links
Version: 1.2
Author: Gregorio Hernandez Caso
Author URI: http://aclog.ionosfera.com
*/

/*  Copyright 2005  Gregorio Hernandez Caso  (email : ioz@ionosfera.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if (!is_plugin_page()) {
  load_plugin_textdomain('ioz_dl');
  add_option('ioz_dl_num_links_default', 10);
  add_option('ioz_dl_num_related_links', 5);
  add_option('ioz_dl_cache_dir', get_option('fileupload_url'));
  add_option('ioz_dl_cache_time', 300);
  add_option('ioz_dl_search_user', false);
  add_option('ioz_dl_user_id', '');
  add_option('ioz_dl_search_popular', true);
  
  if(!class_exists('clsXMLParser')) {
    require_once('clsXMLParser.php');
  }
  
  function ioz_dl_get_links_array($type = 'standard', $tags = 'delicious', $user = 'joshua') {
    // Create XMLParser object
    $objXML = new clsXMLParser();
    $objXML->strCacheDir = dirname(__FILE__).'/../../'.get_option('ioz_dl_cache_dir');
    $objXML->nCacheTimeout = 60 * get_option('ioz_dl_cache_time');
    // Construct feed uri
    switch ($type) {
      case 'user': {
        if ($user == '') { $user = 'joshua'; } // Del.icio.us creator ;)
        $feedURI = 'http://del.icio.us/rss/'.$user.'/'.$tags;
        break;
      }
      case 'popular': {
        $feedURI = 'http://del.icio.us/rss/popular/'.$tags;
        break;
      }
      default: {
        $feedURI = 'http://del.icio.us/rss/tag/'.$tags;
        break;
      }
    }
    // Parse xml and return array
    return $objXML->get($feedURI);
  }

  function ioz_dl_get_links($number = 'default', $before = '', $after = '', $type = 'standard', $tags = 'delicious', $user = 'joshua') {
    $nLinksShown = 0;
    if (is_string($number)) {
      switch (strtolower($number)) {
        case 'all': {
          $number = 100;
          break;
        }
        default: {
          $number = get_option('ioz_dl_num_links_default');
          break;
        }
      }
    }
    if (!is_numeric($number)) { $number = 0; }
    $arrLinks = ioz_dl_get_links_array($type, $tags, $user);
    if ($number > 0) {
      foreach ($arrLinks[0]['children'] as $key => $arrItems) {
        if ($arrItems['name'] == "ITEM" && $number > 0) {
          $sLinkURL = $arrItems['children'][1]['tagData'];
          $sLinkName = $arrItems['children'][0]['tagData'];
          echo $before.'<a href="'.$sLinkURL.'" title="'.$sLinkName.'">'.$sLinkName.'</a>'.$after;
          $number--;
          $nLinksShown++;
        }
      }
    }
    return $nLinksShown;
  }

  function ioz_dl_get_post_related_links($thepostid, $before = '', $after = '') {
    global $wpdb, $tablepostmeta;
    
    $nCount = get_option('ioz_dl_num_related_links');
    $nLinksFound = 0;
    $nLinksShown = 0;
    
    // Get tags
    $q  = "SELECT meta_value FROM $tablepostmeta WHERE post_id=".$thepostid." AND ";
    $q .= "(`meta_key`='ttaglist' OR `meta_key`='technorati' OR `meta_key`='technorati_tags')";
    $tagCols = $wpdb->get_col($q);
    
    if (count($tagCols) > 0 && $tagCols[0] != "") {
      $tagData  = implode(" ", $tagCols);
      $tagArray = preg_split("/[ ]+/", $tagData);
    } else {
      $tagArray = array();
      $aCategories = get_the_category();
      foreach ($aCategories as $aCategory) {
        $tagArray[] = ereg_replace("-", "", $aCategory->category_nicename);
      }
      shuffle($tagArray);
    }
    
    $search_tags = "";
    foreach($tagArray as $i=>$tag) {
      if( !empty($tag) ) {
        if ($search_tags=="") {
          $search_tags = trim($tag);
        }
      }
    }
    
    // Check for user links
    if (get_option('ioz_dl_search_user') && get_option('ioz_dl_user_id')!='') {
      $arrUserLinks = ioz_dl_get_links_array('user', $search_tags, get_option('ioz_dl_user_id'));
      $nLinksFound += count($arrUserLinks[0]['children']) - 1;
    }
    
    // Check for popular links
    if ($nLinksFound < $nCount && get_option('ioz_dl_search_popular')) {
      $arrPopularLinks = ioz_dl_get_links_array('popular', $search_tags);
      $nLinksFound += count($arrPopularLinks[0]['children']) - 1;
    }
    
    // Check for standard links
    if ($nLinksFound < $nCount) {
      $arrStandardLinks = ioz_dl_get_links_array('standard', $search_tags);
      $nLinksFound += count($arrStandardLinks[0]['children']) - 1;
    }
    
    // User links
    if ($nCount > 0 && get_option('ioz_dl_search_user') && isset($arrUserLinks[0]['children'])) {
      foreach ($arrUserLinks[0]['children'] as $key => $arrItems) {
        if ($arrItems['name'] == "ITEM" && $nCount > 0) {
          $sLinkURL = $arrItems['children'][1]['tagData'];
          $sLinkName = $arrItems['children'][0]['tagData'];
          echo $before.'<a href="'.$sLinkURL.'" title="'.$sLinkName.'">'.$sLinkName.'</a>'.$after;
          $nCount--;
          $nLinksShown++;
        }
      }
    }
    
    // Popular links
    if ($nCount > 0 && get_option('ioz_dl_search_popular') && isset($arrPopularLinks[0]['children'])) {
      foreach ($arrPopularLinks[0]['children'] as $key => $arrItems) {
        if ($arrItems['name'] == "ITEM" && $nCount > 0) {
          $sLinkURL = $arrItems['children'][1]['tagData'];
          $sLinkName = $arrItems['children'][0]['tagData'];
          echo $before.'<a href="'.$sLinkURL.'" title="'.$sLinkName.'">'.$sLinkName.'</a>'.$after;
          $nCount--;
          $nLinksShown++;
        }
      }
    }
    
    // Standard links
    if ($nCount > 0 && isset($arrStandardLinks[0]['children'])) {
      foreach ($arrStandardLinks[0]['children'] as $key => $arrItems) {
        if ($arrItems['name'] == "ITEM" && $nCount > 0) {
          $sLinkURL = $arrItems['children'][1]['tagData'];
          $sLinkName = $arrItems['children'][0]['tagData'];
          echo $before.'<a href="'.$sLinkURL.'" title="'.$sLinkName.'">'.$sLinkName.'</a>'.$after;
          $nCount--;
          $nLinksShown++;
        }
      }
    }
    
    return $nLinksShown;
  }
  
  function ioz_dl_add_options_page() {
    add_options_page("Del.icio.us Related Links", "Del.icio.us", 9, "wp-delicious_links.php");
  }
  
  add_action('admin_head', 'ioz_dl_add_options_page');
} else {
  $location = get_option('siteurl') . '/wp-admin/admin.php?page=wp-delicious_links.php'; // Form Action URI
  
  if ($_POST['ioz_dl_action'] == 'save_options') {
    update_option('ioz_dl_num_links_default', $_POST['ioz_dl_num_links_default']);
    update_option('ioz_dl_num_related_links', $_POST['ioz_dl_num_related_links']);
    update_option('ioz_dl_cache_dir', $_POST['ioz_dl_cache_dir']);
    update_option('ioz_dl_cache_time', $_POST['ioz_dl_cache_time']);
    if(isset($_POST['ioz_dl_search_user'])) {
      update_option('ioz_dl_search_user', true);
    } else {
      update_option('ioz_dl_search_user', false);
    }
    update_option('ioz_dl_user_id', $_POST['ioz_dl_user_id']);
    if(isset($_POST['ioz_dl_search_popular'])) {
      update_option('ioz_dl_search_popular', true);
    } else {
      update_option('ioz_dl_search_popular', false);
    }
  }
  
  $ioz_dl_num_links_default = stripslashes(get_option('ioz_dl_num_links_default'));
  $ioz_dl_num_related_links = stripslashes(get_option('ioz_dl_num_related_links'));
  $ioz_dl_cache_dir = stripslashes(get_option('ioz_dl_cache_dir'));
  $ioz_dl_cache_time = stripslashes(get_option('ioz_dl_cache_time'));
  $ioz_dl_search_user = get_option('ioz_dl_search_user');
  $ioz_dl_user_id = stripslashes(get_option('ioz_dl_user_id'));
  $ioz_dl_search_popular = get_option('ioz_dl_search_popular');
?>
<div class="wrap">
  <h2>Del.icio.us Links Configuration</h2>
  <form name="ioz_dl_form" method="post" action="<?php echo $location ?>">
    <input type="hidden" name="ioz_dl_action" value="save_options" />
    <fieldset class="options">
      <legend>General Options</legend>
      <table width="100%" cellspacing="2" cellpadding="5" class="editform">
        <tr valign="top">
          <th width="33%" scope="row">Default number of links</th>
          <td><input name="ioz_dl_num_links_default" type="text" id="ioz_dl_num_links_default" value="<?php echo $ioz_dl_num_links_default; ?>" size="4" />
          <br />Default number of links to show.</td>
        </tr>
      </table>
    </fieldset>
    <fieldset class="options">
      <legend>Post Related Links</legend>
      <table width="100%" cellspacing="2" cellpadding="5" class="editform">
        <tr valign="top">
          <th width="33%" scope="row">Number of links</th>
          <td><input name="ioz_dl_num_related_links" type="text" id="ioz_dl_num_related_links" value="<?php echo $ioz_dl_num_related_links; ?>" size="4" />
          <br />Number of post related links to show.</td>
        </tr>
        <tr valign="top">
          <th width="33%" scope="row">Search for user links</th>
          <td>
            <input name="ioz_dl_search_user" type="checkbox" id="ioz_dl_search_user" value="ioz_dl_search_user"
            <?php if($ioz_dl_search_user == true) {?> checked="checked" <?php } ?> />
            <br />Search for post related user links .
          </td>
        </tr>
        <tr valign="top">
          <th width="33%" scope="row">User ID</th>
          <td><input name="ioz_dl_user_id" type="text" id="ioz_dl_user_id" value="<?php echo $ioz_dl_user_id; ?>" size="50" />
          <br />Del.icio.us user identificator (name).</td>
        </tr>
        <tr valign="top">
          <th width="33%" scope="row">Search for popular links</th>
          <td>
            <input name="ioz_dl_search_popular" type="checkbox" id="ioz_dl_search_popular" value="ioz_dl_search_popular"
            <?php if($ioz_dl_search_popular == true) {?> checked="checked" <?php } ?> />
            <br />Search for post related popular links .
          </td>
        </tr>
      </table>
    </fieldset>
    <fieldset class="options">
      <legend>Cache</legend>
      <table width="100%" cellspacing="2" cellpadding="5" class="editform">
        <tr valign="top">
          <th width="33%" scope="row">Directory</th>
          <td><input name="ioz_dl_cache_dir" type="text" id="ioz_dl_cache_dir" value="<?php echo $ioz_dl_cache_dir; ?>" size="50" />
          <br />Directory to save the cache files</td>
        </tr>
        <tr valign="top">
          <th width="33%" scope="row">Update Frequency</th>
          <td><input name="ioz_dl_cache_time" type="text" id="ioz_dl_cache_time" value="<?php echo $ioz_dl_cache_time; ?>" size="4" />
          <br />Cache files update frequency</td>
        </tr>
      </table>
    </fieldset>
    <p class="submit">
      <input type="submit" name="Submit" value="Save Options &raquo;" />
    </p>
  </form>
</div>
<?
}
?>