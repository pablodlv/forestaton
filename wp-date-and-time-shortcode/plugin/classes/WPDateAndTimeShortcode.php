<?php

/**
 * WPDateAndTimeShortcode Class
 *
 * The main class for the WP Date and Time Shortcode plugin
 *
 * @author     Denra.com aka SoftShop Ltd <support@denra.com>
 * @copyright  2019 Denra.com aka SoftShop Ltd
 * @license    GPLv2 or later
 * @version    1.3.1
 * @link       https://www.denra.com/
 */

namespace Denra\Plugins;

class WPDateAndTimeShortcode extends Plugin {
    
    protected $allowed_items = [
        'date-time', 'datetime', // default WP date and time format (default value)
        'date', // default WP date format
        'time', // default WP date format
        'custom', // custom format used by the built-in PHP date() function
        'year', 'years', // 4-digit year
        'year-short', 'years-short', // 2-digit year
        'month', 'months', // month as number (1-12)
        'month-name', // month as name (January-December)
        'month-name-short', // month as 3-letter name (Jan-Dec),
        'day', 'days', // day of month
        'hour', 'hours', // hours
        'minute', 'minutes', // minutes
        'second', 'seconds', // seconds
        'day-of-year', // day of the year as number
        'days-in-month', // number of days in the month
        'days-in-february', // number of days in the month of February for the year
        'days-in-year', // number of days in year - 365/366
        'weekday', // day of the week as number (1-7)
        'weekday-name', // day of the week as full name (Monday-Sunday)
        'weekday-name-short', // day of the week as full name (Mon-Sun)
        'week-of-year', // week of year, since first Monday of the year
        'am-pm', // shows am/pm or AM/PM according to the am_pm attribute ('L' or 'U')
        'timezone', // show the timezone
        'timezone-abbreviation' // show the timezone abbreviation
    ];
    
    protected $weekdays = [
        'mon' => 1,
        'tue' => 2,
        'wed' => 3,
        'thu' => 4,
        'fri' => 5,
        'sat' => 6,
        'sun' => 7
    ];
    
    public function __construct($id, $data = []) {
        
        // Set text_domain for the framework
        $this->text_domain = 'denra-wp-dt';
        
        // Set admin menus texts
        $this->admin_title_menu = __('WP Date and Time Shortcode', 'denra-wp-dt');
        
        parent::__construct($id, $data);
        
        $this->addShortcodes();
        
    }
    
    public function addShortcodes() {
        
        // Add the main shortcodes.
        add_shortcode('wpdts', [&$this, 'getDateTime']);
        // Add the main shortcodes
        add_shortcode('wp-dt', [&$this, 'getDateTime']);
        add_shortcode('wp_dt', [&$this, 'getDateTime']);
        
        // Add additional shortcodes for each separate item
        foreach ($this->allowed_items as $item) {
            add_shortcode('wpdts-' . $item, [&$this, 'getDateTime']);
            // Backward compatibility with version 2.3.1 and earlier
            add_shortcode('wp-dt-'.$item, [&$this, 'getDateTime']);
        }
        
    }
    
    public function getDateTime($atts, $content, $tag) {
        
        // set attributes
        if (is_array($atts)) {
            foreach ($atts as $key => $value) {
                if (in_array($value, ['yes', '1', 'on', 1, true], true)) {
                    $atts[$key] = 1;
                }
                elseif (in_array($value, ['no', '0', 'off', 0, false], true)) {
                    $atts[$key] = 0;
                }
                else {
                    $atts[$key] = trim(htmlspecialchars_decode($value), '" ');
                }
            }
        }
              
        $wp_dt = ['years', 'months', 'days', 'hours', 'minutes', 'seconds'];
        
        // _change for backward compatibility with version 1.1
        foreach($wp_dt as $wp_dt_part) {
            if (isset($atts[$wp_dt_part.'_change'])) {
                $atts[$wp_dt_part] = $atts[$wp_dt_part.'_change'];
                unset($atts[$wp_dt_part.'_change']);
            }
            if (isset($atts[$wp_dt_part.'_zero'])) {
                $atts['zero'] = $atts[$wp_dt_part.'_zero'];
                unset($atts[$wp_dt_part.'_zero']);
            }
        }
        // Backward compatibility with version 2.3.1 - changing 'start' attr to 'start'.
        if (isset($atts['init'])) {
            $atts['start'] = $atts['init'];
            unset($atts['init']);
        }
        
        $atts = shortcode_atts([
            'item' => 'custom', // item to show
            'format' => '', // format when item is custom
            'start' => 'now', // start date (now, mysql format, or other special one)
            'next' => '', // move the start date and time to the next coming selected
            'i18n' => 1, // set months and weeks names to be displayed in the current language
            'days_suffix' => 0, // use suffix st, nd, rd, th for days
            'hours_24' => 1, // // use 24 or 12 hours format
            'am_pm' => 'L', // use when 12 hours format: 'L' for lowercase (am, pm) or 'U' for uppercase (AM, PM)
            // _change for backward compatibility with version 1.1
            'years' => 0, 'years_change' => 0, // change in years
            'months' => 0, 'months_change' => 0, // change in months
            'days' => 0, 'days_change' => 0, // change in days
            'hours' => 0, 'hours_change' => 0, // change in hours
            'minutes' => 0, 'minutes_change' => 0, // change in minutes
            'seconds' => 0, 'seconds_change' => 0, // change in seconds
            // use one 'zero' attribute and keeping the compatibility with version 2.2.1 for the x_zero attributes
            'zero' => 1 // use leading zero for months, days, hours, minutes, seconds
        ], $atts, $tag );
        
        if (!in_array($tag, ['wp-dt', 'wp_dt', 'wpdts'])) {
            $atts['item'] = preg_replace('/^wpdts\-/', '', $tag);
            // Backward compatibility with version 2.3.1 - changing 'start' attr to 'start'.
            $atts['item'] = preg_replace('/^wp[\-_]dt\-/', '', $atts['item']);
            
        }
        
        $atts['start'] = trim($atts['start']);
        $atts['item'] = trim($atts['item']);
        
        if (!in_array($atts['item'], $this->allowed_items, true)) {
            $atts['item'] = 'custom';
        }
        
        
        // get date from post if needed
        $post_id = $post = null;
        if  (in_array($atts['start'], ['post-created', 'post-created-gmt', 'post-modified', 'post-modified-gmt'])) {
            $post_id = get_the_ID();
            if ($post_id && $post = get_post($post_id, ARRAY_A)) {
                switch($atts['start']) {
                    case 'post-created':
                        $atts['start'] = $post['post_date'];
                        break;

                    case 'post-created-gmt':
                        $atts['start'] = $post['post_date_gmt'];
                        break;

                    case 'post-modified':
                        $atts['start'] = $post['post_modified'];
                        break;

                    case 'post-modified-gmt':
                        $atts['start'] = $post['post_modified_gmt'];
                        break;
                }
            }
        }
        
        // set initial date and time if not set in the 'start' attribute
        if (!$atts['start'] || $atts['start'] == "now") {
            $atts['start'] = current_time('mysql', FALSE);
        }
        
        $timestamp = $timestamp_start = strtotime($atts['start']);
        
        // calculate changes
        $dt_change = [];
        foreach ($wp_dt as $wp_dt_part) {
            $change_value = $atts[$wp_dt_part] ? intval($atts[$wp_dt_part]) : 0;
            if ($change_value) {
                $plus_minus = $change_value > 0 ? '+' : '-';
                $change_value = abs($change_value);
                $dt_change[$wp_dt_part] = $plus_minus . $change_value . ' ';
                if ($change_value == 1) {
                    $dt_change[$wp_dt_part] .= rtrim($wp_dt_part, 's');
                }
                else {
                    $dt_change[$wp_dt_part] .= $wp_dt_part;
                }
            }
        }
        if (count($dt_change)) {
            $timestamp = strtotime(implode(' ', $dt_change), $timestamp);
        }
        
        // Move the init date and time to the next coming selected. No descriptions for the magic below. :)
        if  ('' != $atts['next']) {
            $next_timestamps = [];
            $next_days = explode(',', strtolower(preg_replace('/\s+/', ' ', $atts['next'])));
            foreach ($next_days as $next_day) {
                $date_arr = explode(' ', trim($next_day));
                $next_day = trim($date_arr[0]);
                if (isset($date_arr[1]) && '' != $date_arr[1]) {
                    $next_time = $date_arr[1];
                }
                else {
                    $next_time = 'H:i:s';
                }
                $next_dt = '';
                $next_timestamp = 0;
                $last_day_of_month = intval(date('t', $timestamp));
                if (in_array($next_day, array_keys($this->weekdays), true)) {
                    $this_weekday = intval(date('N', $timestamp));
                    $next_weekday = $this->weekdays[$next_day];
                    $add_days = 0;
                    if ($this_weekday < $next_weekday) {
                        // if in the current week and after today
                        $add_days = $next_weekday - $this_weekday;
                    }
                    elseif ($this_weekday > $next_weekday) {
                        $add_days = 7 - abs($this_weekday - $next_weekday);
                    }
                    else {
                        $temp_this_dt = date('Y-m-d ' . $next_time, $timestamp);
                        $temp_this_timestamp = strtotime($temp_this_dt);
                        if ($timestamp >= $temp_this_timestamp ) {
                            $add_days = 7;
                        }
                    }
                    $next_dt = date('Y-m-d ' . $next_time, $timestamp + $add_days * 86400);
                    $next_timestamp = strtotime($next_dt);
                }
                elseif ($next_day == 'last-day-of-month') {
                    $next_dt = date('Y-m-' . sprintf('%02d', $last_day_of_month) . ' ' . $next_time, $timestamp);
                    $next_timestamp = strtotime($next_dt);
                }
                elseif (is_numeric($next_day)) {
                    $next_day = intval($next_day);
                    if ($next_day >=1 && $next_day <= 31) {
                        $this_day = intval(date('j', $timestamp));
                        if ($next_day > $this_day) {
                            $next_dt = date('Y-m-' . sprintf('%02d', $next_day) . ' ' . $next_time, $timestamp);
                        }
                        else {
                            // Get last day 23:59:59, add one second to have next month 1st 00:00:00.
                            $first_day_of_next_month_timestamp = strtotime(date('Y-m-' . sprintf('%02d', $last_day_of_month) . ' 23:59:59', $timestamp)) + 1;
                            // Get the number of days for the next month.
                            $next_month_days = intval(date('t', $first_day_of_next_month_timestamp));
                            if ($next_day == $this_day) {
                                $temp_next_dt = date('Y-m-' .sprintf('%02d', $next_day) . ' ' . $next_time, $timestamp);
                                $temp_next_timestamp = strtotime($temp_next_dt);
                                if ($timestamp < $temp_next_timestamp) {
                                    $next_dt = $temp_next_dt;
                                }
                                elseif ($next_day <= $next_month_days) {
                                    $next_dt = date('Y-m-'. sprintf('%02d', $next_day) . ' ' . $next_time, $first_day_of_next_month_timestamp);
                                }
                            }
                            elseif (($next_day < $this_day) && ($next_day <= $next_month_days)) {
                                $next_dt = date('Y-m-'. sprintf('%02d', $next_day) . ' ' . $next_time, $first_day_of_next_month_timestamp);
                            }
                        }
                        if ($next_dt) {
                            $next_timestamp = strtotime($next_dt);
                        }
                    }
                }
                if ($next_timestamp) { //$next_timestamp > $timestamps
                    $next_timestamps[$next_dt] = $next_timestamp;
                }
            }
            asort($next_timestamps);
            $timestamp = array_shift($next_timestamps);
            unset($next_timestamps);
        }
        
        switch ($atts['item']) {
            
            case 'date-time':
            case 'datetime':
                $atts['format'] = get_option('date_format') . ' ' . get_option('time_format');
                break;
            
            case 'date':
                $atts['format'] = get_option('date_format'); // default from WP
                break;
            
            case 'time':
                $atts['format'] = get_option('time_format'); // default from WP
                break;
            
            case 'month':
            case 'months':
                if ($atts['zero']) {
                    $atts['format'] = "m"; // 01-09, 10-12
                }
                else {
                    $atts['format'] = "n"; // 1-12
                }
                break;
            
            case 'month-name':
                $atts['format'] = "F"; // January-December
                break;
            
            case 'month-name-short':
                $atts['format'] = "M"; // Jan-Dec
                break;
            
            case 'days-in-month':
                $atts['format'] = "t"; // 1-31
                break;
            
            case 'day':
            case 'days':
                if ($atts['zero']) {
                    $atts['format'] = "d"; // 01-09, 10-31
                }
                else {
                    $atts['format'] = "j"; // 1-31
                }
                if (intval($atts['days_suffix'])) {
                    $atts['format'] .= "S"; // add st, nd, rd, th
                }
                break;
            
            case 'weekday':
                 $atts['format'] = "N"; // ISO ISO-8601 1 is Monday, 7 - Sunday
                break;
            
            case 'weekday-name':
                 $atts['format'] = "l"; // Monday-Sunday
                break;
            
            case 'weekday-name-short':
                $atts['format'] = "D"; // Mon-Sun
                break;
            
            case 'week-of-year':
                $atts['format'] = 'W'; // 1-52, since first Monday
                break;
            
            case 'hour':
            case 'hours':
                if ($atts['hours_24']) {
                    if ($atts['zero']) {
                        $atts['format'] = "H"; // 01-09, 10-24
                    }
                    else {
                        $atts['format'] = "G"; // 1-24
                    }
                }
                else {
                   if ($atts['zero']) {
                        $atts['format'] = "h"; // 01-09, 10-12
                    }
                    else {
                        $atts['format'] = "g"; // 1-12
                    }
                }
                break;
                
            case 'am-pm':
                if ($atts['am_pm'] == 'L') {
                    $atts['format'] = "a"; // AM/PM
                }
                else {
                    $atts['format'] = "A"; // am/pm
                }
                break;
                
            case 'timezone':
                $atts['format'] = "e"; // EST, CDT, MDT, ...
                break;
            
            case 'timezone-abbreviation':
                $atts['format'] = "Т"; // UTC, GMT, America/Los_Angeles, ...
                break;
            
            // do nothing with these here since they need custom processing
            /*
            case 'day-of-year':
            case 'days-in-month':
            case 'days-in-february':
            case 'days-in-year':
            case 'minutes':
            case 'seconds':
                break;
             * 
             * Format contstants are not added either.
            */
            
            default:
            case 'custom':
                if ($atts['format'] == '' || $atts['format'] == 'custom') {
                    $atts['format'] = get_option('date_format') . ' ' . get_option('time_format');
                }
                break;

        }
        
        // process all that need direct calculations or displays
        switch ($atts['item']) {
            
            case 'date-time':
            case 'datetime':
            default:
                if ($atts['i18n']) {
                    $result = date_i18n($atts['format'], $timestamp, false);
                }
                else {
                    $result = date($atts['format'], $timestamp);
                }
                break;
            
            case 'day-of-year':
                // calculate the current day of the year
                $result = intval(date('z', $timestamp)) + 1;
                break;
            
            case 'days-in-february':
                // calculate the number of days in February this year
                $result = 28;
                if (intval(date('L', $timestamp))) {
                    $result++; // 29 days
                }
                break;
                
            case 'days-in-year':
                $result = 365;
                if (intval(date('L', $timestamp))) {
                    $result++; // 366 days
                }
                break;
                
            case 'year':
            case 'years':
                $result = date('Y', $timestamp); // 0000-9999
                if (!$atts['zero']) {
                    $result =  intval($result); // remove the leading zeros
                }
                break;
            
            case 'year-short':
            case 'years-short':
                $result = date('y', $timestamp); // 01-99
                if (!$atts['zero']) {
                    $result =  intval($result); // remove the leading zeros
                }
                break;
            
            case 'minute':
            case 'minutes':
                $result = date('i', $timestamp);
                if (!$atts['zero']) {
                    $result =  intval($result); // remove the leading zero
                }
                break;
            
            case 'second':
            case 'seconds':
                $result = date('s', $timestamp);
                if (!$atts['zero']) {
                    $result = intval($result); // remove the leading zero
                }
                break;
                
        }
        
        // convert to string and return the display result
        return strval($result);
    }
    
}
