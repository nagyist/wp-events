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


/** Report an error if WP Events not yet installed. */
function ec3_check_installed($title) {
  global $ec3;
  if(!$ec3->event_category) {?>
    <div id="wp-events" class="error">
      <h2><?php echo $title; ?></h2>
      <p><?php _e('You must choose an event category.','wp-events'); ?><br />
        <a href="<?php echo get_option('home');?>/wp-admin/options-general.php?page=ec3_admin"><?php _e('Go to WP Events Options','wp-events'); ?></a>
      </p>
    </div>
   <?php
  }
  return $ec3->event_category;
}

/** Substitutes placeholders like '%key%' in $format with 'value' from $data
 *  array. */
function ec3_format_str($format,$data) {
  foreach($data as $k=>$v)
      $format=str_replace("%$k%",$v,$format);
  return $format;
}

define('EC3_DEFAULT_TEMPLATE_EVENT','<a href="%LINK%">%TITLE% (%TIME%)</a>');
define('EC3_DEFAULT_TEMPLATE_DAY',  '%DATE%:');
define('EC3_DEFAULT_DATE_FORMAT',   'j F');
define('EC3_DEFAULT_TEMPLATE_MONTH','');
define('EC3_DEFAULT_MONTH_FORMAT',  'F Y');

/** Returns TRUE if the current post is an event. */
function ec3_is_event() {
  global $post;
  return( !empty($post->ec3_schedule) );
}

/** Returns TRUE if $query is an event category query. */
function ec3_is_event_category_q(&$query) {
  global $ec3;
  // This bit nabbed from is_category()
  if($query->is_category) {
    $cat_obj = $query->get_queried_object();
    if($cat_obj->term_id == $ec3->event_category) {
      return true;
    }
  }
  return false;
}

/** Returns TRUE if $ec3->query is an event category query. */
function ec3_is_event_category() {
  global $ec3;
  return ec3_is_event_category_q($ec3->query);
}

/** Determines the type of listing for $query - P(OST),E(VENT),A(LL),D(ISABLE).
 *  When $query->ec3_listing the result is A or E, depending upon the query. */
function ec3_get_listing_q(&$query)
{
  global $ec3;
  if(empty($query->ec3_listing))
  {
    if($ec3->advanced && ec3_is_event_category_q($query))
      return 'E';
    else
      return 'A';
  }
  return substr($query->ec3_listing,0,1);
}

/** Determines the type of listing for $ec3->query - P(OST),E(VENT),A(LL).
 *  When $query->ec3_listing the result is A or E, depending upon the query. */
function ec3_get_listing()
{
  global $ec3;
  return ec3_get_listing_q($ec3->query);
}

/** Comparison function for events' start times.
 *  Example: Sort the events in a post by start time.
 *
 *    usort( $post, 'ec3_cmp_events' );
 *
 * (Note. This isn't a practical example, because posts' events are already
 *  sorted by start time.)
 */
function ec3_cmp_events(&$e0,&$e1) {
  if( $e0->start < $e1->start ) return -1;
  if( $e0->start > $e1->start ) return 1;
  return 0;
}

/** Fetch the first sensible 'current' event. Use this function if you want
 *  to look at the start time. */
function ec3_sensible_start_event() {
  global $ec3, $post;
  if(!empty($ec3->event))
    return $ec3->event;
  elseif(isset($post->ec3_schedule) && count($post->ec3_schedule)>0)
    return $post->ec3_schedule[0];
  else
    return false;
}

/** Fetch the last sensible 'current' event. Use this function if you want
 *  to look at the end time. */
function ec3_sensible_end_event()
{
  global $ec3, $post;
  if(!empty($ec3->event))
    return $ec3->event;
  elseif(isset($post->ec3_schedule) && count($post->ec3_schedule)>0)
    return $post->ec3_schedule[ count($post->ec3_schedule) - 1 ];
  else
    return false;
}

/** Get the sched_id of the current event. */
function ec3_get_sched_id()
{
  $event = ec3_sensible_start_event();
  if(empty($event))
    return '';
  else
    return $event->sched_id;
}

/** Return TRUE if the current event is in the past. */
function ec3_is_past()
{
  $event = ec3_sensible_end_event();
  if(empty($event))
    return false;
  else
    return( $event->end < $ec3->today );
}

/** Get a human-readable 'time since' the current event. */
function ec3_get_since()
{
  // To use %SINCE%, you need Dunstan's 'Time Since' plugin.
  if(function_exists('time_since'))
  {
    $event = ec3_sensible_start_event();
    if(!empty($event))
      return time_since( time(), ec3_to_time($event->start) );
  }
  return '';
}

/* Get the start date and time of the current event.
   - get month and year (i.e. dec 2011): F Y
   - get time (i.e. 0:30): G:i
   - get Iso date and time format (i.e. ): 
*/
function ec3_get_start_datetime($d='') {
  $event = ec3_sensible_start_event();
  if(empty($event)) {
    return '';
  } elseif($event->allday && substr($event->start,0,10) == substr($event->end,0,10)) {
    $start_date = mysql2date(__('Y-m-d'),$event->start).' 00:00:00';
    return mysql2date($d,$start_date);
  }
  $d = empty($d)? get_option('date_format'): $d;
  return mysql2date($d,$event->start);
}

/* Get the end date and time of the current event. */
function ec3_get_end_datetime($d='') {
  $event = ec3_sensible_end_event();
  if(empty($event)) {
    return '';
  } elseif($event->allday && substr($event->start,0,10) == substr($event->end,0,10)) {
    $end_date = mysql2date(__('Y-m-d'),$event->start).' 23:59:59';
    return mysql2date($d,$end_date);
  }
  $d = empty($d)? get_option('date_format'): $d;
  return mysql2date($d,$event->end);
}
/** Get the location of the current event. */
function ec3_get_location() {
  $event = ec3_sensible_start_event();
  if(empty($event))
    return '';
  else
    return __($event->location);
}
/** Get the current version of the EC3 plug-in. */
function ec3_get_version() {
  global $ec3;
  return $ec3->version;
}

/** Initialise an event-loop, just for the events in the current $post.
 *  Example:
 *
 *    // First a normal loop over the current query's posts.
 *    while(have_posts())
 *    {
 *      the_post();
 *      // Now a nested loop, over the events in each post.
 *      for($evt=ec3_iter_post_events(); $evt->valid(); $evt->next())
 *      {
 *        ...
 *      }
 *    }
 */
function ec3_iter_post_events($id=0) {
  global $ec3;
  $post = get_post($id);
  unset($ec3->events);
  if(!isset($post->ec3_schedule) || empty($post->ec3_schedule)) {
    $ec3->events       = false;
  } else {
    $ec3->events       = $post->ec3_schedule;
  }
  return new ec3_EventIterator();
}


/** Initialise an event-loop, for ALL events in all posts in a query.
 *  You must explicitly state which query is to be used. If you just want to use
 *  the current query, then use the variant form: ec3_iter_all_events(). */
function ec3_iter_all_events_q(&$query) {
  global $ec3, $post;
  unset($ec3->events);
  $ec3->events = array();
  $listing = ec3_get_listing_q($query);

  if($query->is_page || $query->is_single || $query->is_admin || $listing=='D'):

      // Emit all events.
      while($query->have_posts())
      {
        $query->the_post();
        if(!isset($post->ec3_schedule))
          continue;
        foreach($post->ec3_schedule as $s)
          $ec3->events[] = $s;
      }

  elseif($listing=='P'): // posts-only

      ; // Leave the $ec3->events array empty - list no events.

  elseif($query->is_date && !$query->is_time):

      // Only emit events that occur on the given day (or month or year).
      // There two alternate ways to specify a date, the 'm' parameter...
      if($query->query_vars['m'])
      {
        if(strlen($query->query_vars['m'])>=8)
        {
          $m=substr($query->query_vars['m'],0,8);
          $fmt='Ymd';
        }
        elseif(strlen($query->query_vars['m'])>=6)
        {
          $m=substr($query->query_vars['m'],0,6);
          $fmt='Ym';
        }
        else
        {
          $m=substr($query->query_vars['m'],0,4);
          $fmt='Y';
        }
      }
      else // ...or the 'year', 'monthnum' and 'day' parameters...
      {
        $m=date('Ymd'); // Start with today.
        $fmt='Ymd';
        if($query->query_vars['year']) {
          $m=''.zeroise($query->query_vars['year'],4).substr($m,4,2);
          $fmt='Y';
        }
        if($query->query_vars['monthnum']) {
          $m=substr($m,0,4).zeroise($query->query_vars['monthnum'],2);
          $fmt='Ym';
        }
        if($query->query_vars['day']) {
          $m=substr($m,0,6).zeroise($query->query_vars['day'],2);
          $fmt='Ymd';
        }
      }

      while($query->have_posts()) {
        $query->the_post();
        if(!isset($post->ec3_schedule)) {
          continue;
        }
        foreach($post->ec3_schedule as $s) {
          if(mysql2date($fmt,$s->end) >= $m && mysql2date($fmt,$s->start) <= $m) {
            $ec3->events[] = $s;
          }
        }
      }

  elseif($ec3->is_date_range):

      // The query is date-limited, so only emit events that occur
      // within the date range.
      while($query->have_posts()) {
        $query->the_post();
        if(!isset($post->ec3_schedule))
          continue;
        foreach($post->ec3_schedule as $s)
          if( ( empty($ec3->range_from) ||
                  mysql2date('Y-m-d',$s->end) >= $ec3->range_from ) &&
              ( empty($ec3->range_before) ||
                  mysql2date('Y-m-d',$s->start) <= $ec3->range_before ) )
          {
            $ec3->events[] = $s;
          }
      }

  elseif($ec3->advanced &&( $listing=='E' || $query->is_search )):

      // Hide inactive events
      while($query->have_posts()) {
        $query->the_post();
        if(!isset($post->ec3_schedule)) {
          continue;
        }
        foreach($post->ec3_schedule as $s) {
          if( $s->end >= $ec3->today ) {
            $ec3->events[] = $s;
          }
        }
      }

  else:

      // Emit all events (same as the first branch).
      while($query->have_posts()) {
        $query->the_post();
        if(!isset($post->ec3_schedule)) {
          continue;
        }
        foreach($post->ec3_schedule as $s) {
          $ec3->events[] = $s;
        }
      }

  endif;
  usort($ec3->events,'ec3_cmp_events');
  // This is a bit of a hack - only detect 'order=ASC' query var.
  // Really need our own switch.
  if(strtoupper($query->query_vars['order'])=='ASC')
    $ec3->events=array_reverse($ec3->events);
  return new ec3_EventIterator();
}


/** Initialise an event-loop, for ALL events in all posts in the current query.
 *  Example:
 *
 *    if(have_posts())
 *    {
 *      for($evt=ec3_iter_all_events(); $evt->valid(); $evt->next())
 *      {
 *        ...
 *      }
 *    }
 */
function ec3_iter_all_events() {
  global $wp_query;
  return ec3_iter_all_events_q($wp_query);
}


/** Resets the global $post status from $wp_query. Allows us to continue
 *  with the main loop, after a nested loop. */
function ec3_reset_wp_query()
{
  global $wp_query,$post;
  if($wp_query->in_the_loop)
  {
    $wp_query->post = $wp_query->posts[$wp_query->current_post];
    $post = $wp_query->post;
    setup_postdata($post);
  }
}


/** Iterator class implements loops over events. Generated by
 *  ec3_iter_post_events() or ec3_iter_all_events().
 *  These iterators are not independent - don't try to get smart with nested
 *  loops!
 *  This class is ready to implement PHP5's Iterator interface.
 */
class ec3_EventIterator
{
  var $_idx   =0;
  var $_begin =0;
  var $_limit =0;

  /** Parameters are andices into the $ec3->events array.
   *  'begin' points to the first event.
   *  'limit' is one higher than the last event. */
  function ec3_EventIterator($begin=0, $limit=-1)
  {
    global $ec3;
    $this->_begin = $begin;
    if(empty($ec3->events))
      $this->_limit = 0;
    elseif($limit<0)
      $this->_limit = count($ec3->events);
    else
      $this->_limit = $limit;
    $this->rewind();
  }

  /** Resets this iterator to the beginning. */
  function rewind()
  {
    $this->_idx = $this->_begin - 1;
    $this->next();
  }

  /** Move along to the next (possibly empty) event. */
  function next()
  {
    $this->_idx++;
    $this->current();
  }
  
  /** Returns TRUE if this iterator points to an event. */
  function valid()
  {
    if( $this->_idx < $this->_limit )
      return TRUE;
    ec3_reset_wp_query();
    return FALSE;
  }

  /** Set the global $ec3->event to match this iterator's index. */
  function current()
  {
    global $ec3,$id,$post;
    if( $this->_idx < $this->_limit )
    {
      $ec3->event = $ec3->events[$this->_idx];
      if($post->ID != $ec3->event->post_id || $id != $ec3->event->post_id)
      {
        $post = get_post($ec3->event->post_id);
        setup_postdata($post);
      }
    }
    else
    {
      unset($ec3->event); // Need to break the reference.
      $ec3->event = false;
    }
  }
  
  function key()
  {
    return $this->_idx;
  }
}; // limit class ec3_EventIterator


/** Template function, for backwards compatibility.
 *  Call this from your template to insert a list of forthcoming events.
 *  Available template variables are:
 *   - template_day: %DATE% %SINCE% (only with Time Since plugin)
 *   - template_event: %DATE% %TIME% %LINK% %TITLE% %AUTHOR%
 */
function ec3_get_events(
  $limit,
  $template_event=EC3_DEFAULT_TEMPLATE_EVENT,
  $template_day  =EC3_DEFAULT_TEMPLATE_DAY,
  $date_format   =EC3_DEFAULT_DATE_FORMAT,
  $template_month=EC3_DEFAULT_TEMPLATE_MONTH,
  $month_format  =EC3_DEFAULT_MONTH_FORMAT)
{
  if(!ec3_check_installed(__('Upcoming Events','ec3')))
    return;

  // Parse $limit:
  //  NUMBER      - limits number of posts
  //  NUMBER days - next NUMBER of days
  $query = new WP_Query();
  if(preg_match('/^ *([0-9]+) *d(ays?)?/',$limit,$matches)) {
      $query->query( 'ec3_listing=event&ec3_days='.intval($matches[1]) );
  } elseif(intval($limit)>0) {
      $query->query( 'ec3_after=today&posts_per_page='.intval($limit) );
  } elseif(intval($limit)<0) {
      $query->query( 'ec3_before=today&order=asc&posts_per_page='.abs(intval($limit)) );
  } else {
      $query->query( 'ec3_after=today&posts_per_page=5' );
  }
?>
  <ul class="ec3_events">
<!-- Generated by WP Events v. <?php echo ec3_get_version(); ?> -->
<?php
  if($query->have_posts()) {
    $current_month=false;
    $current_date=false;
    $data=array();
    for($evt=ec3_iter_all_events_q($query); $evt->valid(); $evt->next()) {
      $data['SINCE']=ec3_get_since();

      // Month changed?
      $data['MONTH']=ec3_get_start_datetime($month_format);
      if((!$current_month || $current_month!=$data['MONTH']) && $template_month) {
        if($current_date) {
?>
  </ul>
</li>
<?php
        }
        if($current_month) {
?>
  </ul>
</li>
<?php
        }
?>
<li class="ec3_list ec3_list_month"><?php echo ec3_format_str($template_month,$data); ?>
  <ul>
<?php
        $current_month=$data['MONTH'];
        $current_date=false;
      }

      // Date changed?
      $data['DATE'] =ec3_get_start_datetime($date_format);
      if((!$current_date || $current_date!=$data['DATE']) && $template_day)
      {
        if($current_date) {
?>
  </ul>
</li>
<?php
        }
?>
<li class="ec3_list ec3_list_day"><?php echo ec3_format_str($template_day,$data); ?>
  <ul>
<?php
        $current_date=$data['DATE'];
      }

      $data['TIME']  =ec3_get_start_datetime();
      $data['TITLE'] =get_the_title();
      $data['LINK']  =get_permalink();
      $data['AUTHOR']=get_the_author();
?>
  <li><?php echo ec3_format_str($template_event,$data); ?></li>
<?php
    }
    if($current_date) {
?>
  </ul>
</li>
<?php
    }
    if($current_month) {
?>
  </ul>
</li>
<?php
    }
  } else {
?>
  <li><?php echo __('No events.','ec3'); ?></li>
<?php
  }
?>
</ul>
<?php
}

/** Formats the schedule for the current post.
 *  Returns the HTML fragment as a string. */
define('EC3_DEFAULT_FORMAT_SINGLE',
       '<tr class="%3$s"><td colspan="3">%1$s</td><td class="location">%2$s</td></tr>');
define('EC3_DEFAULT_FORMAT_RANGE',
       '<tr class="%4$s"><td class="ec3_start">%1$s</td>'.'<td class="ec3_to">&rarr;</td><td class="ec3_end">%2$s</td><td class="location">%3$s</td></tr>');
define('EC3_DEFAULT_FORMAT_WRAPPER','
<table class="travel_schedule">
  <thead>
    <tr>
      <th>'.__('Start','wpevents').'</th>
      <td />
      <th>'.__('End','wpevents').'</th>
      <th>'.__('Location','wpevents').'</th>
    </tr>
    </thead>
    <tbody>
      %s
    </tbody>
    <tfoot>
      <tr class="past">
        <td colspan="4">'.__('Past event','wpevents').'</td>
      </tr>
    </tfoot>
</table>
');
function ec3_get_schedule($format_single=EC3_DEFAULT_FORMAT_SINGLE, $format_range=EC3_DEFAULT_FORMAT_RANGE, $format_wrapper=EC3_DEFAULT_FORMAT_WRAPPER) {
  if(!ec3_is_event()) {
    return '';
  }

  global $ec3;
  $result='';
  $date_format=get_option('date_format');
  $time_format=get_option('time_format');
  $current=false;
  for($evt=ec3_iter_post_events(); $evt->valid(); $evt->next()) {
    $date_start=ec3_get_start_datetime('j F Y');
    $date_end  =ec3_get_end_datetime('j F Y');
    $datetime_start=ec3_get_start_datetime('j F Y @G:i');
    $datetime_end  =ec3_get_end_datetime('j F Y @G:i)');
    $location  =ec3_get_location();
    if($ec3->event->active) {
      $active ='';
    } else {
      $active ='past';
    }

    if($ec3->event->allday) {
      if($date_start!=$date_end) {
        $result.=
          sprintf($format_range,$date_start,$date_end,$location,$active);
      }
      elseif($date_start!=$current) {
        $current=$date_start;
        $result.=sprintf($format_single,$date_start,$location,$active);
      }
    } else if($date_start!=$date_end) {
      $current=$date_start;
      $result.=sprintf(
          $format_range,
          "{$date_start} {$datetime_start}",
          "{$date_end} {$datetime_end}",
          $location,
          $active
        );
    } else {
      if($date_start!=$current) {
        $current=$date_start;
        $result.=sprintf($format_single,$date_start,$location,$active);
      }
      if($datetime_start==$datetime_end) {
        $result.=sprintf($format_single,$datetime_start,$location,$active);
      } else {
        $result.= sprintf($format_range,$datetime_start,ec3_get_end_datetime('G:i'),$location,$active);
      }
    }
  }
  return sprintf($format_wrapper,$result);
}
function wpevents_the_schedule() {
  echo ec3_get_schedule();
}
add_shortcode('the_table_schedule', 'wpevents_the_schedule');


/** Formats the schedule for the current post as one or more 'iconlets'.
 *  Returns the HTML fragment as a string. */
function ec3_get_iconlets() {
  if(!ec3_is_event()) {
    return '';
  }

  global $ec3;
  $result='';
  $current=false;
  $this_year=date('Y');
  for($evt=ec3_iter_post_events(); $evt->valid(); $evt->next()) {
    $year_start =ec3_get_start_datetime('Y');
    $month_start=ec3_get_start_datetime('M');
    $day_start  =ec3_get_start_datetime('j');
    // Don't bother about intra-day details.
    if($current==$day_start.$month_start.$year_start)
      continue;
    $current=$day_start.$month_start.$year_start;
    // Grey-out past events.
    if($ec3->event->active) {
      $active ='';
    } else {
      $active =' past';
    }
    /* //Only put the year in if it isn't *this* year. If uncomment, delete:
      //<span class="year">'. $year_start.'</span>
    if($year_start!=$this_year) {
      $month_start.='&nbsp;&rsquo;'.substr($year_start,2);
    }
    */
    // OK, make the iconlet.
    $result.='
<div class="wpevents_iconlet'.$active.'">
    ';
    if(!$ec3->event->allday && substr($ec3->event->start,0,10) == substr($ec3->event->end,0,10)) {
      // Event with start time.
      $time_start=ec3_get_start_datetime('G:i');
      $result.='
  <time class="startdate" itemprop="startDate" datetime="'.ec3_get_start_datetime('c').'">
    <strong class="yearmonth">
      <span class="month">'.$month_start.'</span> <span class="year">'. $year_start.'</span><span class="punctuation">, </span>
    </strong>
    <strong class="day">'.$day_start.'</strong><span class="punctuation">, </span>
    <small class="time">'.$time_start.'</small>
  </time><!-- /.startdate -->
      ';
    } elseif($ec3->event->allday && substr($ec3->event->start,0,10) == substr($ec3->event->end,0,10)) {
      // Single, all-day event.
      $result.='
  <div class="allday">
    <time class="startdate" itemprop="startDate" datetime="'.ec3_get_start_datetime('c').'">
      <strong class="yearmonth">
        <span class="month">'.$month_start.'</span> <span class="year">'. $year_start.'</span><span class="punctuation">, </span>
      </strong>
      <strong class="day">'.$day_start.'</strong>
    </time>
    <time class="enddate" itemprop="endDate" datetime="'.ec3_get_end_datetime('c').'"></time>
  </div><!-- /.allday -->
      ';
    } else {
      // Multi-day event.
      $year_end = ec3_get_end_datetime('Y');
      $month_end = ec3_get_end_datetime('M');
      $day_end = ec3_get_end_datetime('j');
      $time_end = ec3_get_end_datetime('G:i');
      $result.='
  <div class="multiday'; if($ec3->event->allday){ $result.=' allday">'; } else { $result.='">'; }
      $result.='
    <time class="startdate" itemprop="startDate" datetime="'.ec3_get_start_datetime('c').'">
      <strong class="yearmonth">
        <span class="month">'.$month_start.'</span> <span class="year">'. $year_start.'</span><span class="punctuation">, </span>
      </strong>
      <strong class="day">'.$day_start.'</strong>
      ';
      if(!$ec3->event->allday){
        $result.='
      <small class="time">'.$time_start.'</small>
        ';
      }
      $result.='
    </time>
    <span class="arrow"> &rarr; </span>
    <time class="enddate" itemprop="endDate" datetime="'.ec3_get_end_datetime('c').'">
      <strong class="yearmonth">
        <span class="month">'.$month_end.'</span> <span class="year">'.$year_end.'</span>
      </strong>
      <strong class="day">'.$day_end.'</strong>
      ';
      if(!$ec3->event->allday){
        $result.='
      <small class="time">'.$time_end.'</small>
        ';
      }
      $result.='
    </time>
  </div><!-- /.multiday -->';
    }
    //Display location infos.
    if(ec3_get_location()) {
      $result.='<small class="location"><span class="punctuation">, </span><span itemprop="location" itemtype="http://schema.org/Place">'.ec3_get_location().'</span></small>';
    }
    $result.='
</div><!-- /.wpevents_iconlet'.$active.' -->';
  }
  return apply_filters( 'ec3_filter_iconlets', $result );
}

function wpevents_the_iconlets() { ?>
<div class="wpevents_iconlets">
<?php	echo ec3_get_iconlets(); ?>
</div><!--/.wpevents_iconlets-->
<?php
}
add_shortcode('wpevents_the_iconlets', 'wpevents_the_iconlets');

/** Template function, for backwards compatibility.
 *  Call this from your template to insert the Sidebar Event Calendar. */
function ec3_get_calendar($options = false) {
  if(!ec3_check_installed('Event-Calendar')) {
    return;
  }
  require_once(dirname(__FILE__).'/calendar-sidebar.php');
  $calobj = new ec3_SidebarCalendar($options);
  echo $calobj->generate();
}
?>