<?php
/*
Copyright (c) 2011 Lorenzo De Tomasi, 2008, Alex Tingle.

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 3
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

require_once(dirname(__FILE__).'/calendar-basic.php');

/** Renders a sidebar calendar. */
class ec3_SidebarCalendar extends ec3_BasicCalendar {
  // OPTIONS

  /** Should day names be abbreviated to 1 or 3 letters? DEFAULT=1 */
  var $day_length;
  /** Hide the 'EC' logo on calendar displays? DEFAULT=0 */
  var $hide_logo;
  /** Position navigation links or hide them. DEFAULT=0 */
  var $show_locations;
  /** Position navigation links or hide them. DEFAULT=0 */  
  var $navigation;
  /** Disable popups? DEFAULT=0 */
  var $disable_popups;


  function ec3_SidebarCalendar($options=false,$datetime=false) {
    // Set appearance options from the $options array, if it's been provided.
    // Otherwise set the defaults from the old, global WP options.
    if(empty($options))
      $options=array();

    // The default id for sidebar calendars is 'wp-calendar'
    if(!array_key_exists('id',$options))
      $options['id'] = 'wp-calendar';

    if(array_key_exists('day_length',$options))
      $this->day_length = $options['day_length'];
    else
      $this->day_length =max(1,abs(intval(get_option('ec3_day_length'))));

    if(array_key_exists('hide_logo',$options))
      $this->hide_logo = $options['hide_logo'];
    else
      $this->hide_logo=intval(get_option('ec3_hide_logo'));
      
    if(array_key_exists('show_locations',$options))
      $this->show_locations = $options['show_locations'];
    else
      $this->show_locations=intval(get_option('wpevents_show_locations'));

    if(array_key_exists('navigation',$options))
      $this->navigation = $options['navigation'];
    else
      $this->navigation=intval(get_option('ec3_navigation'));

    if(array_key_exists('disable_popups',$options))
      $this->disable_popups = $options['disable_popups'];
    else
      $this->disable_popups=intval(get_option('ec3_disable_popups'));
    // END OPTIONS

    // Initialise the parent class.
    $this->ec3_BasicCalendar($options,$datetime);
  }

  /** Generate the table header (same for every month). */
  function _get_thead() {
    global $weekday,$weekday_abbrev,$weekday_initial;
    $thead='
    <tr class="weekday_abbr">
    ';
    $start_of_week =intval( get_option('start_of_week') );
    for($i=0; $i<7; $i++) {
      $full_day_name=$weekday[ ($i+$start_of_week) % 7 ];
      if(3==$this->day_length) {
          $display_day_name=$weekday_abbrev[$full_day_name];
      } elseif($this->day_length<3) {
          $display_day_name=$weekday_initial[$full_day_name];
      } else {
          $display_day_name=$full_day_name;
      }
      $thead.='<th scope="col"><abbr title="'.$full_day_name.'">'.$display_day_name.'</abbr></th>';
    }
    $thead.='
    </tr>
    ';
    return $thead;
  }

  /** Generate the event calendar navigation controls. */
  function _get_nav() {
    global $ec3;
    $idprev = '';
    $idnext = '';
    if(empty($this->id)) {
      $ec3previd    = "ec3_prev";
      $ec3nextid    = "ec3_next";
      $ec3spinnerid = "ec3_spinner";
      $ec3publishid = "ec3_publish";
    } else {
      $ec3previd    = "$this->id-ec3_prev";
      $ec3nextid    = "$this->id-ec3_next";
      $ec3spinnerid = "$this->id-ec3_spinner";
      $ec3publishid = "$this->id-ec3_publish";
      if($this->id=='wp-calendar') {
        // For compatibility with standard wp-calendar.
        $idprev = ' id="prev"';
        $idnext = ' id="next"';
      }
    }
    $nav = '
  <thead>
    <tr class="nav">
    ';

    // Previous month
    $prev=$this->begin_dateobj->prev_month();
    $nav .= '
      <td'.$idprev.'><a id="'.$ec3previd.'" href="'.$prev->month_link($this->show_only_events).'">&laquo;&nbsp;'.$prev->month_abbrev().'</a></td>
    ';
    // Selected month
    $title = sprintf(
        __('View posts for %1$s %2$s'),
        $this->begin_dateobj->month_name(),
        $this->begin_dateobj->year_num
      );
    $nav .= '
      <td colspan="5">
        <a href="'.$this->begin_dateobj->month_link($this->show_only_events).'" title="'. $title .'">'.$this->begin_dateobj->month_name().' '.$this->begin_dateobj->year_num.'</a>
      </td>
    ';
    // Next month
    $next=$this->limit_dateobj;
    $nav .= '
      <td'.$idnext.'><a id="'.$ec3nextid.'" href="'.$next->month_link($this->show_only_events).'"'.'>'.$next->month_abbrev().'&nbsp;&raquo;</a></td>
    </tr>
      '.$this->_thead.'
  </thead>
  <tfoot>
    <tr>
      <td colspan="7">
        <img id="'.$ec3spinnerid.'" style="display:none" src="'.$ec3->myfiles.'/images/loading.gif" alt="spinner" />
    ';
    // iCalendar link.
    $webcal=get_feed_link('ical');
    // Macintosh always understands webcal:// protocol.
    // It's hard to guess on other platforms, so stick to http://
    if(strstr($_SERVER['HTTP_USER_AGENT'],'Mac OS X'))
        $webcal=preg_replace('/^http:/','webcal:',$webcal);
    $nav .= '
        <a id="'.$ec3publishid.'" href="'.$webcal.'" title="'.__('Subscribe to iCalendar.','ec3').'"><img src="'.$ec3->myfiles.'/images/ical-icon_12x12px.gif" alt="iCalendar" /></a>
    ';
    $nav .= '
      </td>
    </tr>
  </tfoot>
';
    return $nav;
  }

  function wrap_month($monthstr) {
    // Make a table for this month.
    $result = '
  <tbody id="'.$this->id.'-'.$this->dateobj->month_id().'">'
    .$monthstr.'
  </tbody>
    ';
    return $result;
  }

  function wrap_week($weekstr) {
    return '
    <tr>
      '.$weekstr.'
    </tr>
    ';
  }
  
  function make_pad($num_days,$is_start_of_month) {
    global $ec3;
    if(!$is_start_of_month && $num_days>1) {
      return '
      <td colspan="'.$num_days.'" class="pad" style="vertical-align:bottom">
        <a href="http://wpcal.firetree.net/?ec3_version='.$ec3->version.'" title="Event-Calendar '.$ec3->version.'"'.($this->hide_logo? ' style="display:none">' : '>').'<span class="ec3_ec"><span>EC</span></span></a></td>
      ';
    } else if($num_days>0) {
      return '
      <td colspan="'.$num_days.'" class="pad">&nbsp;</td>
      ';
    } else {
      return '';
    }
  }
  
  /** dayobj - ec3_CalendarDay object, may be empty. */
  function wrap_day($dayarr)
  {
    $day_id = $this->dateobj->day_id();
    $td_attr = ' id="'.$this->id.'-'.$day_id.'"';
    $td_classes = array();
    if($day_id=='today') {
      $td_classes[] = 'today';
    }
    if(!empty($this->dayobj)) {
      $td_classes[] = 'postday';
      $a_attr = ' href="'.$this->dateobj->day_link($this->show_only_events)
       . '" title="'.implode(', ',$dayarr).'"';
      if($this->dayobj->has_events())
      {
        $td_classes[] = 'eventday';
        $a_attr  .= ' class="eventday"';
      }
      $daystr = "<a$a_attr>" . $this->dateobj->day_num . '</a>';
    } else {
      $daystr = $this->dateobj->day_num;
    }
    if(!empty($td_classes)) {
      $td_attr .= ' class="' . implode(' ',$td_classes) . '"';
    }
    return '<td'.$td_attr.'>'.$daystr.'</td>';
  }

  function make_event(&$event) {
    global $post;
    if($this->dayobj->date == substr($event->start,0,10))
      return $this->make_post($post) . ' @' . ec3_get_start_datetime(); // same day
    else
      return '...' . $this->make_post($post); // continued from previous day.
  }

  function make_post(&$post) {
    global $ec3_htmlspecialchars;
    $safe_title=strip_tags(get_the_title());
    $safe_title=
      str_replace(
        array(',','@'),
        ' ',
        $ec3_htmlspecialchars(
          stripslashes($safe_title),
          ENT_QUOTES,
          get_option('blog_charset'),
          FALSE // double_encode
        )
      );
    return $safe_title;
  }

  function generate() {
    global $ec3;
    
    // Set-up $this->_thead, so that it's ready for $this->wrap_month().
    $this->_thead = $this->_get_thead();

    $result = '
<div id="'.$this->id.'">
<table>
    ';

    // Display navigation panel.
    if(0==$this->navigation) {
      $result .= $this->_get_nav();
    }

    $q = 'ec3_after='  .$this->begin_dateobj->to_mysqldate()
       . '&ec3_before='.$this->limit_dateobj->to_mysqldate()
       . '&nopaging=1';
    if(!$this->show_only_events)
        $q .= '&ec3_listing=all';
    $query = new WP_Query();
    $query->query($q);

    switch(ec3_get_listing_q($query)) {
      case 'E':
        $this->add_events($query);
        break;
      case 'P':
        $this->add_posts($query,!$ec3->advanced);
        break;
      default:
        $this->add_events($query);
        $this->add_posts($query,!$ec3->advanced);
    }
    $result .= parent::generate();

    // Display navigation panel.
    if(1==$this->navigation) {
      $result .= $this->_get_nav();
    }
    $result .= '
</table>
</div><!-- /#'.$this->id.' -->';

    if(!$this->disable_popups && empty($ec3->done_popups_javascript)) {
      $ec3->done_popups_javascript=true;
      $result .= '<script type="text/javascript" src="'.$ec3->myfiles.'/js/popup.js"></script>';
    }

    if($this->hide_logo) {
      $options=',{hide_logo:true}';
    } else {
      $options='';
    }

    $result .= '<script type="text/javascript"><!--'.'ec3.new_calendar("'.$this->id.'"'.$options.');--></script>';
    return $result;
  }
};

