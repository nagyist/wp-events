<?php
/*
Copyright (c) 2011 Lorenzo De Tomasi, 2005-2008 Alex Tingle.

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/


/** Utility class used by upgrade_database().
 *  Breaks apart a version string into an array of comparable parts. */
class ec3_Version {
  var $part; ///< Array of version parts.

  function ec3_Version($str) {
    $s=preg_replace('/([-a-z]+)([0-9]+)/','\1.\2',$str);
    $v=explode('.',$s);
    $this->part=array();
    foreach($v as $i) {
      if(preg_match('/^[0-9]+$/',$i))
          $this->part[]=intval($i);
      elseif(preg_match('/^dev/',$i))
          $this->part[]=-1000;
      elseif(preg_match('/^_/',$i))
          $this->part[]=-500;
      elseif(preg_match('/^a(lpha)?/',$i))
          $this->part[]=-3;
      elseif(preg_match('/^b(eta)?/',$i))
          $this->part[]=-2;
      elseif(preg_match('/^rc?/',$i))
          $this->part[]=-1;
      elseif(empty($i))
          $this->part[]=0;
      else
          $this->part[]=$i;
    }
  }

  /** Compares this version with $other. */
  function cmp($other) {
    for($i=0; $i < max(count($this->part),count($other->part)); $i++) {
      // Fill in empty pieces.
      if( !isset($this->part[$i]) )
          $this->part[$i] = 0;
      if( !isset($other->part[$i]) )
          $other->part[$i] = 0;
      // Compare
      if( $this->part[$i] > $other->part[$i] )
          return 1;
      if( $this->part[$i] < $other->part[$i] )
          return -1;
    }
    // They really are equal.
    return 0;
  }
};


class ec3_Admin {
  function filter_admin_head() {
    global $ec3;

    // Turn OFF advanced mode when we're in the admin screens.
    $ec3->advanced=false;

    ?>
    <!-- Added by eventcalendar3/admin.php -->
    <style type="text/css" media="screen">
    @import url(<?php echo $ec3->myfiles; ?>/css/admin.css);
    </style>
    <!-- These scripts are only needed by edit_form screens. -->
    <script type="text/javascript" src="<?php echo $ec3->myfiles; ?>/js/addEvent.js"></script>
    <script type="text/javascript" src="<?php echo $ec3->myfiles; ?>/js/edit_form.js"></script>
    <script type="text/javascript"><!--
    Ec3EditForm.event_cat_id='<?php echo $ec3->wp_in_category.$ec3->event_category; ?>';
    Ec3EditForm.start_of_week=<?php echo intval( get_option('start_of_week') ); ?>;
    // --></script>
    <!-- jscalendar 1.0 JavaScripts and css locations --> 
    <style type="text/css">@import url(<?php echo $ec3->myfiles; ?>/css/calendar-blue.css);</style>
    <script type="text/javascript" src="<?php echo $ec3->myfiles; ?>/js/calendar.js"></script>
    <script type="text/javascript" src="<?php echo $ec3->myfiles; ?>/js/calendar-en.js"></script>
    <script type="text/javascript" src="<?php echo $ec3->myfiles; ?>/js/calendar-setup.js"></script>
    <?php
  }


  //
  // EDIT FORM
  //


  /** Only for pre WP2.5. Inserts the Event Editor into the Write Post page. */
  function filter_edit_form()
  { ?>
    
    <!-- Build the user interface for Event-Calendar. -->
    <div class="dbx-b-ox-wrapper">
    <fieldset id='ec3_schedule_editor' class="dbx-box">
    <div class="dbx-h-andle-wrapper">
    <h3 class="dbx-handle"><?php _e('Event Editor','ec3'); ?></h3>
    </div>
    <div class="dbx-c-ontent-wrapper">
    <div class="dbx-content">

    <?php $this->event_editor_box() ?>

    </div>
    </div>
    </fieldset>
    </div>

    <?php
  }


  function event_editor_box()
  {
    global $ec3,$wp_version,$wpdb,$post_ID;
    if(isset($post_ID))
      $schedule = $wpdb->get_results(
        "SELECT
           sched_id,
           DATE_FORMAT(start,'%Y-%m-%d %H:%i') AS start,
           DATE_FORMAT(end,'%Y-%m-%d %H:%i') AS end,
           allday,
           rpt,
           location
         FROM $ec3->schedule WHERE post_id=$post_ID ORDER BY start");
    else
      $schedule = false;

    if(function_exists('wp_create_nonce')) {
      echo '<input type="hidden" name="ec3_nonce" id="ec3_nonce" value="' . 
        wp_create_nonce( plugin_basename(__FILE__) ) . '" />';
    }
    ?>

    <!-- Event-Calendar: Event Editor -->
    <p><input type="checkbox" name="ec3_hasmultiplelocations" value="1"<?php if($hasmultiplelocations){ echo ' checked="checked"'; } ?> /><label for=""><?php _e('Enable multiple locations','wpevents'); ?></label></p>
    <table width="100%" cellspacing="2" cellpadding="5" class="editform">
     <thead>
      <tr>
        <th></th>
        <th><?php _e('Start','ec3'); ?></th>
        <th><?php _e('End','ec3'); ?></th>
        <th style="text-align:center"><?php _e('All Day','ec3'); ?></th>
        <th><?php _e('Repeat','ec3'); ?></th>
        <th><?php _e('Location','wpevents'); ?></th>
        <th><?php _e('Registration','wpevents'); ?></th>
      </tr>
     </thead>
     <tbody>
    <?php
      $ec3_rows=0;
      if($schedule)
      {
        foreach($schedule as $s)
            $this->schedule_row(
              $s->start,$s->end,$s->sched_id,'update',$s->allday,$s->location
            );
        $ec3_rows=count($schedule);
      }
      $default=ec3_strftime('%Y-%m-%d %H:00',3600+time());
      $this->schedule_row($default,$default,'_','create',False,'');
    ?>
      <tr> 
       <td colspan="4">
        <p>
         <input type="button" name="ec3_new_row" class="add" 
          value=" + "
          title="<?php _e('Add a new event','ec3'); ?>"
          onclick="Ec3EditForm.add_row()" />
         <input type="hidden" id="ec3_rows" name="ec3_rows"
          value="<?php echo $ec3_rows; ?>" />
        </p>
       </td>
      </tr> 
     </tbody>
    </table>

    <?php
  }

  /** Utility function called by event_editor_box(). */
  function schedule_row($start,$end,$sid,$action,$allday,$location) {
    global $ec3;
    $s="ec3_start_$sid";
    $e="ec3_end_$sid";
    ?>
      <tr class="ec3_schedule_row"<?php if('create'==$action){ echo ' style="display:none"'; } ?>>
        <td>
          <p>
           <input type="button" name="ec3_del_row_<?php echo $sid;?>" class="delete"
            value="&mdash;"
            title="<?php _e('Delete this event','ec3'); ?>"
            onclick="Ec3EditForm.del_row(this)" />
          </p>
        </td>
        <td class="date">
          <input type="hidden" name="ec3_action_<?php echo $sid;
           ?>" value="<?php echo $action; ?>" />
          <input type="text" name="<?php echo $s; if('update'==$action){ echo "\" id=\"$s"; } ?>" value="<?php echo $start; ?>" /><button type="reset" id="trigger_<?php echo $s; ?>"><img src="<?php echo $ec3->myfiles; ?>/images/ical-icon_12x12px.gif" alt="…" /></button>
        </td>
        <td class="date">
          <input type="text" name="<?php echo $e; if('update'==$action){ echo "\" id=\"$e"; } ?>" value="<?php echo $end; ?>" /><button type="reset" id="trigger_<?php echo $e; ?>"><img src="<?php echo $ec3->myfiles; ?>/images/ical-icon_12x12px.gif" alt="…" /></button>
        </td>
        <td style="text-align:center">
          <input type="checkbox" name="ec3_allday_<?php echo $sid; ?>" value="1"<?php if($allday){ echo ' checked="checked"'; } ?> />
        </td>
        <td>
          <input type="text" name="ec3_repeat_<?php echo $sid; ?>" value="<?php echo $s->rpt; ?>" />
        </td>
<?php
    if(is_plugin_active('wp-locations/wp-locations.php')) {
?>
        <td>
          <select name="ec3_location_<?php echo $sid; ?>">
           <option value="false" selected="selected">Select a location</option>
           <option value="http://cittadellaltraeconomia.it">Citt&agrave; dell'altraeconomia</option>
           <option value="http://linuxclub.it">Linuxclub</option>
          </select>
          <button type="reset" id="trigger_ec3_location_<?php echo $sid; ?>">…</button>
        </td>
<?php
    } else {
?>
        <td>
          <input type="text" name="ec3_location_<?php echo $sid;
            if('update'==$action){ echo "\" id=\"ec3_location_$s"; }//#verify if this row needs to be deleted
            ?>" value="<?php echo $location; ?>" />
          <button type="reset" id="trigger_ec3_location_<?php echo $sid; ?>">…</button>
        </td>
<?php
    }
?>
        <td>
          <input type="checkbox" name="ec3_has_registration_<?php echo $sid; ?>" id="ec3_has_registration_<?php echo $sid; ?>" value="1"<?php if($has_registration){ echo ' checked="checked"'; } ?> title="<?php _e('Enable registration for this event','wpevents'); ?>" />
          <button type="reset" id="trigger_ec3_registration_<?php echo $sid; ?>">…</button>
        </td>
      </tr>
<?php
  }


  function action_save_post($post_ID) {
    if(!$_POST)
        return;

    if(function_exists('wp_verify_nonce')) {
      if(!wp_verify_nonce($_POST['ec3_nonce'], plugin_basename(__FILE__) ))
          return;
    }

    // Ensure that we only save each post once.
    if(isset($this->save_post_called) && $this->save_post_called[$post_ID])
        return;
    if(!isset($this->save_post_called))
       $this->save_post_called=array();
    $this->save_post_called[$post_ID]=true;

    global $ec3,$wpdb;
    // Use this to check the DB before DELETE/UPDATE. Should use
    // ...IGNORE, but some people insist on using ancient version of MySQL.
    $count_where="SELECT COUNT(0) FROM $ec3->schedule WHERE";

    // If this post is no longer an event, then purge all schedule records.
    if(isset($_POST['ec3_rows']) && '0'==$_POST['ec3_rows'])
    {
      if($wpdb->get_var("$count_where post_id=$post_ID"))
         $wpdb->query("DELETE FROM $ec3->schedule WHERE post_id=$post_ID");
      return;
    }

    // Find all of our parameters
    $sched_entries=array();
    $fields =array('start','end','allday','rpt','location');
    foreach($_POST as $k => $v)
    {
      if(preg_match('/^ec3_(action|'.implode('|',$fields).')_(_?)([0-9]+)$/',$k,$match))
      {
        $sid=intval($match[3]);
        if(!isset( $sched_entries[$sid] ))
            $sched_entries[ $sid ]=array('allday' => 0);
        $sched_entries[ $sid ][ $match[1] ] = $v;
      }
    }

    foreach($sched_entries as $sid => $vals)
    {
      // Bail out if the input data looks suspect.
      if(!array_key_exists('action',$vals) || count($vals)<3)
        continue;
      // Save the value of 'action' and remove it. Leave just the column vals.
      $action=$vals['action'];
      unset($vals['action']);
      // Reformat the column values for SQL:
      foreach($vals as $k => $v)
          if('allday'==$k)
              $vals[$k]=intval($v);
          else
              $vals[$k]="'".$wpdb->escape($v)."'";
      $sid_ok=$wpdb->get_var("$count_where post_id=$post_ID AND sched_id=$sid");
      // Execute the SQL.
      if($action=='delete' && $sid>0 && $sid_ok):
        $wpdb->query(
         "DELETE FROM $ec3->schedule
          WHERE post_id=$post_ID
            AND sched_id=$sid"
        );
      elseif($action=='update' && $sid>0 && $sid_ok):
        $wpdb->query(
         "UPDATE $ec3->schedule
          SET sequence=sequence+1, ".$this->implode_assoc(', ',$vals)."
          WHERE post_id=$post_ID
            AND sched_id=$sid"
        );
      elseif($action=='create'):
        $wpdb->query(
         "INSERT INTO $ec3->schedule
          (post_id, ".implode(', ',array_keys($vals)).")
          VALUES ($post_ID, ".implode(', ',array_values($vals)).")"
        );
      endif;
    }
    // Force all end dates to be >= start dates.
    $wpdb->query("UPDATE $ec3->schedule SET end=start WHERE end<start");
  } // end function action_save_post()

  /** Utility function called by action_save_post(). */
  function implode_assoc($glue,$arr)
  {
    $result=array();
    foreach($arr as $key=>$value)
        $result[]=$key."=".$value;
    return implode($glue,$result);
  }
  
  /** Clear events for the post. */
  function action_delete_post($post_ID) {
    global $ec3,$wpdb;
    $wpdb->query("DELETE FROM $ec3->schedule WHERE post_id=$post_ID");
  }


  //
  // OPTIONS
  //


  /** Upgrade the installation, if necessary. */
  function upgrade_database() {
    global $ec3,$wpdb;
    // Check version - return if no upgrade required.
    $installed_version=get_option('ec3_version');
    if($installed_version==$ec3->version)
      return;

    $v0 = new ec3_Version($installed_version);
    $v1 = new ec3_Version($ec3->version);
    if( $v0->cmp($v1) > 0 )
      return; // Installed version later than this one ?!?!

    // Upgrade.
    $message = sprintf(__('Upgraded database to %1$s Version %2$s','ec3'),
        'Event-Calendar',$ec3->version
      ) . '.';

    $tables=$wpdb->get_results('SHOW TABLES',ARRAY_N);
    if(!$tables) {
      die(sprintf(__('Error upgrading database for %s plugin.','ec3'),
          'Event-Calendar'
        ));
    }

    $table_exists=false;
    foreach($tables as $t)
        if(preg_match("/$ec3->schedule/",$t[0]))
            $table_exists=true;

    if($table_exists)
    {
      $message .= '<br />'.__('Table already existed','ec3').'.';
    }
    else
    {
      $message .= '<br />'
        . sprintf(__('Created table %s','ec3'),$ec3->schedule).'.';
      $wpdb->query(
        "CREATE TABLE $ec3->schedule (
           sched_id BIGINT(20) AUTO_INCREMENT,
           post_id  BIGINT(20),
           sequence BIGINT(20),
           start    DATETIME,
           end      DATETIME,
           allday   BOOL,
           rpt      VARCHAR(64),
           location VARCHAR(255)
           PRIMARY KEY(sched_id)
         )");
      // Force the special upgrade page if we are coming from v3.0
      if( $ec3->event_category &&
          ( empty($v0) || $v0[0]<3 || ($v0[0]==3 && $v0[1]==0) ) )
      {
        update_option('ec3_upgrade_posts',1);
      }
    } // end if(!$table_exists)

    // Sequence column is new in v3.2.dev-01
    $v32dev01 = new ec3_Version('3.2.dev-01');
    if( $v0->cmp($v32dev01) < 0 )
    {
      $message .= '<br />'
        . sprintf(__('Added SEQUENCE column to table %s','ec3'),$ec3->schedule)
        . '.';
      $wpdb->query(
        "ALTER TABLE $ec3->schedule ADD COLUMN sequence BIGINT(20) DEFAULT 1"
      );
    }

    // Option ec3_show_event_box is new in v3.2.dev-02
    $hide_event_box=get_option('ec3_hide_event_box');
    if($hide_event_box!==false)
    {
      if(intval($hide_event_box))
        $ec3->set_show_event_box(2);
      else
        $ec3->set_show_event_box(0);
      update_option('ec3_hide_event_box',false);
    }
    
   // Sequence column is new in v3.2.dev-03
    $v32dev03 = new ec3_Version('3.2.dev-03');
    if( $v0->cmp($v32dev03) < 0 ) {
      $message .= '<br />'
        . sprintf(__('Added SEQUENCE column to table %s','ec3'),$ec3->schedule)
        . '.';
      $wpdb->query(
        "ALTER TABLE $ec3->schedule ADD COLUMN location VARCHAR(255)"
      );
    }
    // Record the new version number
    update_option('ec3_version',$ec3->version);

    // Display an informative message.
    echo '<div id="message" class="updated fade"><p><strong>';
    echo $message;
    echo "</strong></p></div>\n";
  } // end function upgrade_database();


  function action_admin_menu()
  {
    global $ec3;
    add_options_page(
      __('Event Calendar Options','ec3'),
      'Event-Calendar',
      6,
      'ec3_admin',
      'ec3_options_subpanel'
    );

    if(empty($ec3->event_category))
      return; // Until EC is properly configured, only show the options page.
    
    if(function_exists('add_meta_box'))
    {
      add_meta_box(
        'ec3_schedule_editor',   // HTML id for container div
        __('Event Editor','ec3'),
        'ec3_event_editor_box',  // callback function
        'post',                  // page type
        'advanced',              // context
        'high'                   // priority
      );
    }
    else
    {
      global $ec3_admin;
      // Old (pre WP2.5) functionality.
      add_filter('simple_edit_form',    array(&$ec3_admin,'filter_edit_form'));
      if($ec3->wp_have_dbx)
        add_filter('dbx_post_advanced', array(&$ec3_admin,'filter_edit_form'));
      else
        add_filter('edit_form_advanced',array(&$ec3_admin,'filter_edit_form'));
    }
  }


  function options_subpanel()
  {
    global $ec3;

    if(isset($_POST['info_update']))
    {
      echo '<div id="message" class="updated fade"><p><strong>';
      if(isset($_POST['ec3_event_category']))
          $ec3->set_event_category( intval($_POST['ec3_event_category']) );
      if(isset($_POST['ec3_show_event_box']))
          $ec3->set_show_event_box( intval($_POST['ec3_show_event_box']) );
      if(isset($_POST['ec3_advanced']))
          $ec3->set_advanced( intval($_POST['ec3_advanced']) );
      if(isset($_POST['ec3_tz']))
          $ec3->set_tz( $_POST['ec3_tz'] );
      _e('Options saved.');
      echo '</strong></p></div>';
    }
    ?>

   <div class="wrap">
    <form method="post">
     <h2><?php _e('Event Calendar Options','ec3'); ?></h2>

     <?php if(isset($_GET['ec3_easteregg'])): ?>

     <h3><?php _e('Easter Egg','ec3') ?>:
       <input type="submit" name="ec3_upgrade_posts"
        value="<?php _e('Upgrade Event Posts','ec3') ?>" /></h3>

     <?php endif ?>

     <table class="form-table"> 

      <tr valign="top"> 
       <th width="33%" scope="row"><?php _e('Event category','ec3'); ?>:</th> 
       <td>
        <select name="ec3_event_category">
        <?php
          if(0==$ec3->event_category)
              echo '<option value="0">'.__('- Select -').'</option>';
          wp_dropdown_cats( 0, $ec3->event_category );
         ?>
        </select>
        <br /><em>
         <?php _e("Event posts are put into this category for you. Don't make this your default post category.",'ec3'); ?>
        </em>
       </td> 
      </tr> 

       <tr valign="top"> 
        <th width="33%" scope="row"><?php _e('Show times within post content','ec3'); ?>:</th> 
        <td>
         <select name="ec3_show_event_box">          
          <option value='0'<?php if($ec3->show_event_box==0) echo " selected='selected'" ?> >
           <?php _e('Hide Times','wpevents'); ?>
          </option>
          <option value='1'<?php if($ec3->show_event_box==1) echo " selected='selected'" ?> >
           <?php _e('Times as table schedules','wpevents'); ?>
          </option>
          <option value='2'<?php if($ec3->show_event_box==2) echo " selected='selected'" ?> >
           <?php _e('Times as floating icons','wpevents'); ?>
          </option>
          <option value='3'<?php if($ec3->show_event_box==3) echo " selected='selected'" ?> >
           <?php _e('Times as icons in a <code>&lt;div class="wpevents_iconlets"&gt;</code>','wpevents'); ?>
          </option>
         </select>
        </td> 
       </tr>

      <tr valign="top">
       <th width="33%" scope="row"><?php _e('Show events as blog entries','ec3'); ?>:</th> 
       <td>
        <select name="ec3_advanced">
         <option value='0'<?php if(!$ec3->advanced_setting) echo " selected='selected'" ?> >
          <?php _e('Events are Normal Posts','ec3'); ?>
         </option>
         <option value='1'<?php if($ec3->advanced_setting) echo " selected='selected'" ?> >
          <?php _e('Keep Events Separate','ec3'); ?>
         </option>
        </select>
        <br /><em>
         <?php _e('Keep Events Separate: the Event Category page shows future events, in date order. Events do not appear on front page.','ec3'); ?>
        </em>
       </td> 

      <tr valign="top">
      <?php if($ec3->tz_disabled): ?>
       <th style="color:gray" width="33%" scope="row"><?php _e('Timezone','ec3'); ?>:</th> 
       <td>
         <input disabled="disabled" type="text" value="<?php
           if(empty($ec3->tz))
               _e('unknown','ec3');
           else
               echo $ec3->tz; ?>" />
         <br /><em>
          <?php _e("You cannot change your timezone. Turn off PHP's 'safe mode' or upgrade to PHP5.",'ec3'); ?>
         </em>
       </td> 
      <?php else: ?>
       <th width="33%" scope="row"><?php _e('Timezone','ec3'); ?>:</th> 
       <td>
         <select name="ec3_tz">
          <option value="wordpress">WordPress</option>
          <?php ec3_get_tz_options($ec3->tz); ?>
         </select>
       </td> 
      <?php endif; ?>
      </tr>

     </table>

     <p class="submit"><input type="submit" name="info_update"
        value="<?php _e('Save Changes') ?>" /></p>
    </form>

   </div> <?php
  } // end function options_subpanel()

}; // end class ec3_Admin


$ec3_admin=new ec3_Admin();

function ec3_options_subpanel()
{
  global $ec3_admin;

  // Upgrade
  if(isset($_POST['ec3_cancel_upgrade']))
    update_option('ec3_upgrade_posts',0);

  $ec3_admin->upgrade_database(); // May set option ec3_force_upgrade

  if( intval(get_option('ec3_upgrade_posts')) || isset($_POST['ec3_upgrade_posts']) ) {
    require_once(dirname(__FILE__).'/upgrade-posts.php');
    ec3_upgrade_posts();
    return;
  }
  
  // Normal options page...
  $ec3_admin->options_subpanel();
}

function ec3_event_editor_box()
{
  global $ec3_admin;
  $ec3_admin->event_editor_box();
}


//
// Hook in...
if($ec3->event_category)
{
  add_filter('admin_head', array(&$ec3_admin,'filter_admin_head'));
  add_action('save_post',  array(&$ec3_admin,'action_save_post'));
  add_action('delete_post',array(&$ec3_admin,'action_delete_post'));
}

// Always hook into the admin_menu - it's required to allow users to
// set things up.
add_action('admin_menu', array(&$ec3_admin,'action_admin_menu'));

?>
