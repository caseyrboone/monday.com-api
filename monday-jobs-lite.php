<?php
/**
 * Plugin Name: Monday Jobs Lite
 * Description: Ultra-lean monday.com → WordPress jobs list (read-only, cached). Use [monday_jobs] in the short code block on the careers page.
 * Version:     0.1.4
 * Author:      Casey Boone
 * Author URI:  https://caseyrboone.com
 */



 if (!defined('ABSPATH')) exit;

 class Monday_Jobs_Lite {
     const OPT_KEY = 'mjl_settings';
     const TRANSIENT_KEY = 'mjl_jobs_cache';
 
     public function __construct() {
         add_shortcode('monday_jobs', [$this, 'shortcode']);
         add_action('admin_menu', [$this, 'admin_menu']);
         add_action('admin_init', [$this, 'register_settings']);
         add_action('init', [$this, 'maybe_flush_cache_on_save']);
     }
 
     /* =========================
        SETTINGS / ADMIN
        ========================= */
 
     public function admin_menu() {
         add_options_page('Monday Jobs Lite', 'Monday Jobs Lite', 'manage_options', 'monday-jobs-lite', [$this, 'settings_page']);
     }
 
     public function register_settings() {
         register_setting(self::OPT_KEY, self::OPT_KEY, [$this, 'sanitize']);
 
         add_settings_section('mjl_main', 'API & Board Settings', '__return_false', self::OPT_KEY);
         add_settings_field('token', 'Personal API Token', [$this, 'field_token'], self::OPT_KEY, 'mjl_main');
         add_settings_field('board_id', 'Board ID', [$this, 'field_board'], self::OPT_KEY, 'mjl_main');
 
         add_settings_section('mjl_map', 'Column Mapping (IDs from API)', '__return_false', self::OPT_KEY);
         add_settings_field('col_location', 'Location column ID', [$this, 'field_col_location'], self::OPT_KEY, 'mjl_map');
         add_settings_field('col_date', 'Date column ID', [$this, 'field_col_date'], self::OPT_KEY, 'mjl_map');
         add_settings_field('col_description', 'Description column ID', [$this, 'field_col_description'], self::OPT_KEY, 'mjl_map');
         add_settings_field('col_apply', 'Apply URL column ID', [$this, 'field_col_apply'], self::OPT_KEY, 'mjl_map');
         add_settings_field('col_status', 'Status column ID', [$this, 'field_col_status'], self::OPT_KEY, 'mjl_map');
 
         add_settings_section('mjl_misc', 'Display, Cache & UX', '__return_false', self::OPT_KEY);
         add_settings_field('cache_minutes', 'Cache (minutes)', [$this, 'field_cache_minutes'], self::OPT_KEY, 'mjl_misc');
         add_settings_field('limit', 'Max items (limit)', [$this, 'field_limit'], self::OPT_KEY, 'mjl_misc');
         add_settings_field('date_format', 'Date format (PHP)', [$this, 'field_date_format'], self::OPT_KEY, 'mjl_misc');
         add_settings_field('desc_words', 'Description word limit', [$this, 'field_desc_words'], self::OPT_KEY, 'mjl_misc');
         add_settings_field('apply_label', 'Apply button label', [$this, 'field_apply_label'], self::OPT_KEY, 'mjl_misc');
         add_settings_field('empty_text', '“No openings” text', [$this, 'field_empty_text'], self::OPT_KEY, 'mjl_misc');
         add_settings_field('show_count', 'Show count header', [$this, 'field_show_count'], self::OPT_KEY, 'mjl_misc');
         add_settings_field('enable_schema', 'Enable JobPosting schema', [$this, 'field_enable_schema'], self::OPT_KEY, 'mjl_misc');
     }
 
     public function sanitize($in) {
         $out = [];
         $out['token']           = isset($in['token']) ? trim($in['token']) : '';
         $out['board_id']        = isset($in['board_id']) ? preg_replace('/\D/', '', $in['board_id']) : '';
         $out['col_location']    = isset($in['col_location']) ? sanitize_text_field($in['col_location']) : '';
         $out['col_date']        = isset($in['col_date']) ? sanitize_text_field($in['col_date']) : '';
         $out['col_description'] = isset($in['col_description']) ? sanitize_text_field($in['col_description']) : '';
         $out['col_apply']       = isset($in['col_apply']) ? sanitize_text_field($in['col_apply']) : '';
         $out['col_status']      = isset($in['col_status']) ? sanitize_text_field($in['col_status']) : '';
         $out['cache_minutes']   = isset($in['cache_minutes']) ? max(1, (int)$in['cache_minutes']) : 30;
         $out['limit']           = isset($in['limit']) ? max(1, (int)$in['limit']) : 25;
         $out['date_format']     = isset($in['date_format']) ? sanitize_text_field($in['date_format']) : 'M j, Y';
         $out['desc_words']      = isset($in['desc_words']) ? max(0, (int)$in['desc_words']) : 40;
         $out['apply_label']     = isset($in['apply_label']) ? sanitize_text_field($in['apply_label']) : 'Apply';
         $out['empty_text']      = isset($in['empty_text']) ? sanitize_text_field($in['empty_text']) : 'No openings at this time.';
         $out['show_count']      = !empty($in['show_count']) ? 1 : 0;
         $out['enable_schema']   = !empty($in['enable_schema']) ? 1 : 0;
 
         delete_transient(self::TRANSIENT_KEY);
         return $out;
     }
 
     private function settings() {
         $defaults = [
             'token'           => '',
             'board_id'        => '',
             'col_location'    => '',
             'col_date'        => '',
             'col_description' => '',
             'col_apply'       => '',
             'col_status'      => '',
             'cache_minutes'   => 30,
             'limit'           => 25,
             'date_format'     => 'M j, Y',
             'desc_words'      => 40,
             'apply_label'     => 'Apply',
             'empty_text'      => 'No openings at this time.',
             'show_count'      => 0,
             'enable_schema'   => 0,
         ];
         return wp_parse_args(get_option(self::OPT_KEY, []), $defaults);
     }
 
     public function settings_page() {
         ?>
         <div class="wrap">
             <h1>Monday Jobs Lite</h1>
             <p>Enter your monday.com Personal API token, Board ID, and the column IDs you want to map. Then place <code>[monday_jobs]</code> on your Careers page.</p>
             <form method="post" action="options.php">
                 <?php settings_fields(self::OPT_KEY); do_settings_sections(self::OPT_KEY); submit_button(); ?>
             </form>
             <hr />
             <details>
                 <summary><strong>How to find column IDs</strong></summary>
                 <p>Use the monday API playground with your token and this query (replace board id &amp; limit as needed):</p>
 <pre><code>{
   boards(ids: BOARD_ID) {
     id
     name
     items_page(limit: 25) {
       items {
         id
         name
         column_values { id title text value }
       }
       cursor
     }
   }
 }
 </code></pre>
                 <p>Copy the <code>column_values.id</code> for Location, Date, Description, Apply URL, and Status. For Link columns, the actual URL is read from <code>value</code> (JSON).</p>
             </details>
         </div>
         <?php
     }
 
     /* ----- Field renderers ----- */
 
     public function field_token() {
         $s = $this->settings();
         printf('<input type="password" name="%1$s[token]" value="%2$s" class="regular-text" placeholder="eyJhbGciOi...">',
             esc_attr(self::OPT_KEY), esc_attr($s['token'])
         );
         echo '<p class="description">Create in monday: Avatar → Admin/Developers → API → Generate token.</p>';
     }
 
     public function field_board() {
         $s = $this->settings();
         printf('<input type="text" name="%1$s[board_id]" value="%2$s" class="regular-text" placeholder="e.g., 9491628586">',
             esc_attr(self::OPT_KEY), esc_attr($s['board_id'])
         );
     }
 
     public function field_col_location() {
         $s = $this->settings();
         printf('<input type="text" name="%1$s[col_location]" value="%2$s" class="regular-text" placeholder="location column id">',
             esc_attr(self::OPT_KEY), esc_attr($s['col_location'])
         );
     }
 
     public function field_col_date() {
         $s = $this->settings();
         printf('<input type="text" name="%1$s[col_date]" value="%2$s" class="regular-text" placeholder="date column id">',
             esc_attr(self::OPT_KEY), esc_attr($s['col_date'])
         );
     }
 
     public function field_col_description() {
         $s = $this->settings();
         printf('<input type="text" name="%1$s[col_description]" value="%2$s" class="regular-text" placeholder="description column id">',
             esc_attr(self::OPT_KEY), esc_attr($s['col_description'])
         );
     }
 
     public function field_col_apply() {
         $s = $this->settings();
         printf('<input type="text" name="%1$s[col_apply]" value="%2$s" class="regular-text" placeholder="apply url column id (Link column)">',
             esc_attr(self::OPT_KEY), esc_attr($s['col_apply'])
         );
         echo '<p class="description">For a monday “Link” column, the URL is taken from its JSON value.</p>';
     }
 
     public function field_col_status() {
         $s = $this->settings();
         printf('<input type="text" name="%1$s[col_status]" value="%2$s" class="regular-text" placeholder="status column id">',
             esc_attr(self::OPT_KEY), esc_attr($s['col_status'])
         );
         echo '<p class="description">Only jobs with Status = "Open" (text) will be shown.</p>';
     }
 
     public function field_cache_minutes() {
         $s = $this->settings();
         printf('<input type="number" min="1" name="%1$s[cache_minutes]" value="%2$s" class="small-text"> minutes',
             esc_attr(self::OPT_KEY), esc_attr($s['cache_minutes'])
         );
     }
 
     public function field_limit() {
         $s = $this->settings();
         printf('<input type="number" min="1" name="%1$s[limit]" value="%2$s" class="small-text">',
             esc_attr(self::OPT_KEY), esc_attr($s['limit'])
         );
     }
 
     public function field_date_format() {
         $s = $this->settings();
         printf('<input type="text" name="%1$s[date_format]" value="%2$s" class="regular-text" placeholder="M j, Y">',
             esc_attr(self::OPT_KEY), esc_attr($s['date_format'])
         );
         echo '<p class="description">PHP date format (e.g., <code>M j, Y</code>, <code>Y-m-d</code>). Monday dates are assumed <code>YYYY-MM-DD</code>.</p>';
     }
 
     public function field_desc_words() {
         $s = $this->settings();
         printf('<input type="number" min="0" name="%1$s[desc_words]" value="%2$s" class="small-text"> words',
             esc_attr(self::OPT_KEY), esc_attr($s['desc_words'])
         );
         echo '<p class="description">0 = hide description.</p>';
     }
 
     public function field_apply_label() {
         $s = $this->settings();
         printf('<input type="text" name="%1$s[apply_label]" value="%2$s" class="regular-text" placeholder="Apply">',
             esc_attr(self::OPT_KEY), esc_attr($s['apply_label'])
         );
     }
 
     public function field_empty_text() {
         $s = $this->settings();
         printf('<input type="text" name="%1$s[empty_text]" value="%2$s" class="regular-text" placeholder="No openings at this time.">',
             esc_attr(self::OPT_KEY), esc_attr($s['empty_text'])
         );
     }
 
     public function field_show_count() {
         $s = $this->settings();
         printf('<label><input type="checkbox" name="%1$s[show_count]" value="1" %2$s> Show count header</label>',
             esc_attr(self::OPT_KEY), checked(1, $s['show_count'], false)
         );
     }
 
     public function field_enable_schema() {
         $s = $this->settings();
         printf('<label><input type="checkbox" name="%1$s[enable_schema]" value="1" %2$s> Output basic JobPosting schema (JSON-LD)</label>',
             esc_attr(self::OPT_KEY), checked(1, $s['enable_schema'], false)
         );
         echo '<p class="description">Uses site name as hiringOrganization.</p>';
     }
 
     /* =========================
        FRONTEND SHORTCODE
        ========================= */
 
     public function shortcode($atts = []) {
         $s = $this->settings();
         $is_debug = is_user_logged_in() && current_user_can('manage_options') && isset($_GET['mjl_debug']);
 
         $jobs = $this->get_jobs();
 
         $debug_html = '';
         if ($is_debug) {
             $debug_html .= '<pre style="background:#111;color:#0f0;padding:10px;overflow:auto">';
             if (is_wp_error($jobs)) {
                 $debug_html .= "WP_Error: " . esc_html($jobs->get_error_code()) . " - " . esc_html($jobs->get_error_message()) . "\n";
                 $data = $jobs->get_error_data();
                 if ($data) { $debug_html .= "Error data:\n" . esc_html(print_r($data, true)); }
             } else {
                 $debug_html .= "Jobs count: " . count($jobs) . "\n\n";
                 $debug_html .= esc_html(print_r($jobs, true));
             }
             $debug_html .= "</pre>";
         }
 
         if (is_wp_error($jobs)) {
             return $debug_html . '<div class="mjl-error">Unable to load jobs. Please try again later.</div>';
         }
         if (empty($jobs)) {
             return $debug_html . '<div class="mjl-empty">' . esc_html($s['empty_text']) . '</div>';
         }
 
         // Optional count header
         $count_html = '';
         if (!empty($s['show_count'])) {
             $label = (count($jobs) === 1) ? 'open position' : 'open positions';
             $count_html = '<div class="mjl-count" style="margin-bottom:.5rem;font-weight:600;">' . esc_html(count($jobs) . ' ' . $label) . '</div>';
         }
 
         // Optional schema
         $schema_html = '';
         if (!empty($s['enable_schema'])) {
             $org = get_bloginfo('name');
             $schemas = [];
             foreach ($jobs as $job) {
                 $schemas[] = [
                     "@context" => "https://schema.org",
                     "@type" => "JobPosting",
                     "title" => $job['name'] ?? '',
                     "datePosted" => $job['date'] ?? '',
                     "hiringOrganization" => [
                         "@type" => "Organization",
                         "name" => $org,
                     ],
                     "jobLocation" => [
                         "@type" => "Place",
                         "address" => [
                             "@type" => "PostalAddress",
                             "addressLocality" => $job['location'] ?? ''
                         ]
                     ],
                     "description" => wp_strip_all_tags($job['description'] ?? '')
                 ];
             }
             $schema_html = '<script type="application/ld+json">' . wp_json_encode($schemas) . '</script>';
         }
 
         ob_start();
         echo $debug_html;
         echo $count_html;
         echo '<div class="mjl-list">';
         foreach ($jobs as $job) {
             $title = $job['name'] ?? 'Untitled';
             $loc   = $job['location'] ?? '';
             $date  = $this->format_date($job['date'] ?? '', $s['date_format']);
             $desc  = $job['description'] ?? '';
             $apply = $job['apply'] ?? '';
             $apply_label = $s['apply_label'];
 
             echo '<div class="mjl-item" style="margin:0 0 1rem 0;">';
             echo '<div class="mjl-title" style="font-weight:600;">' . esc_html($title) . '</div>';
 
             $meta = array_filter([$loc, $date]);
             if (!empty($meta)) {
                 echo '<div class="mjl-meta" style="opacity:.8;">' . esc_html(implode(' • ', $meta)) . '</div>';
             }
 
             if (!empty($desc) && (int)$s['desc_words'] !== 0) {
                 $excerpt = wp_trim_words(wp_strip_all_tags($desc), (int)$s['desc_words'], '…');
                 echo '<div class="mjl-desc" style="margin-top:.25rem;">' . esc_html($excerpt) . '</div>';
             }
 
             if (!empty($apply)) {
                 echo '<div class="mjl-apply" style="margin-top:.25rem;"><a href="' . esc_url($apply) . '" target="_blank" rel="noopener">' . esc_html($apply_label) . '</a></div>';
             }
 
             echo '</div>';
         }
         echo '</div>';
         echo $schema_html; // JSON-LD
         return ob_get_clean();
     }
 
     private function format_date($raw, $format) {
         // monday "text" for a date column is usually "YYYY-MM-DD"
         $raw = trim((string)$raw);
         if ($raw === '') return '';
         $dt = \DateTime::createFromFormat('Y-m-d', $raw);
         if (!$dt) {
             // Try other common variants (rare)
             $ts = strtotime($raw);
             if ($ts) return date_i18n($format, $ts);
             return $raw; // fallback: show original
         }
         return date_i18n($format, $dt->getTimestamp());
     }
 
     /* =========================
        DATA FETCH
        ========================= */
 
     private function get_jobs() {
         $cached = get_transient(self::TRANSIENT_KEY);
         if ($cached !== false) return $cached;
 
         $s = $this->settings();
         if (empty($s['token']) || empty($s['board_id'])) {
             return new WP_Error('mjl_missing', 'Missing token or board id.');
         }
 
         // Include "value" so we can parse Link columns for real URLs.
         $query = sprintf('{
           boards(ids: %1$d) {
             items_page(limit: %2$d) {
               items {
                 id
                 name
                 column_values { id text value }
               }
             }
           }
         }', (int)$s['board_id'], (int)$s['limit']);
 
         $args = [
             'headers' => [
                 'Content-Type'  => 'application/json',
                 'Authorization' => $s['token'],
             ],
             'body'    => wp_json_encode(['query' => $query]),
             'timeout' => 20,
         ];
 
         $response = wp_remote_post('https://api.monday.com/v2', $args);
         if (is_wp_error($response)) {
             return new WP_Error('mjl_http', 'HTTP error contacting monday API.', ['transport_error' => $response->get_error_message()]);
         }
 
         $code = wp_remote_retrieve_response_code($response);
         $raw  = wp_remote_retrieve_body($response);
         if ($code !== 200) {
             return new WP_Error('mjl_status', 'Non-200 from monday API.', ['status' => $code, 'body' => $raw]);
         }
 
         $body = json_decode($raw, true);
         if (json_last_error() !== JSON_ERROR_NONE) {
             return new WP_Error('mjl_json', 'Invalid JSON from monday API.', ['raw' => $raw]);
         }
         if (!empty($body['errors'])) {
             return new WP_Error('mjl_graphql', 'monday GraphQL error.', ['errors' => $body['errors']]);
         }
 
         $boards = $body['data']['boards'] ?? [];
         if (empty($boards) || empty($boards[0]['items_page']['items'])) {
             set_transient(self::TRANSIENT_KEY, [], (int)$s['cache_minutes'] * MINUTE_IN_SECONDS);
             return [];
         }
 
         $items = $boards[0]['items_page']['items'];
         $jobs  = [];
 
         foreach ($items as $it) {
             $row = [
                 'id'   => $it['id'] ?? '',
                 'name' => $it['name'] ?? '',
             ];
 
             // Map columns by ID
             $map = [
                 'location'    => $s['col_location'],
                 'date'        => $s['col_date'],
                 'description' => $s['col_description'],
                 'apply'       => $s['col_apply'],
                 'status'      => $s['col_status'],
             ];
 
             $statusText = ''; // filter to "Open" only
 
             foreach (($it['column_values'] ?? []) as $cv) {
                 if (empty($cv['id'])) continue;
 
                 if ($map['status'] && $cv['id'] === $map['status']) {
                     $statusText = strtolower(trim($cv['text'] ?? ''));
                 }
 
                 if ($map['location'] && $cv['id'] === $map['location']) {
                     $row['location'] = $cv['text'] ?? '';
                 }
                 if ($map['date'] && $cv['id'] === $map['date']) {
                     $row['date'] = $cv['text'] ?? '';
                 }
                 if ($map['description'] && $cv['id'] === $map['description']) {
                     $row['description'] = $cv['text'] ?? '';
                 }
 
                 if ($map['apply'] && $cv['id'] === $map['apply']) {
                     $applyUrl = '';
                     if (!empty($cv['value'])) {
                         $val = json_decode($cv['value'], true);
                         if (json_last_error() === JSON_ERROR_NONE) {
                             if (!empty($val['url'])) $applyUrl = $val['url'];
                             if (!$applyUrl && isset($val[0]['url'])) $applyUrl = $val[0]['url'];
                         }
                     }
                     if (!$applyUrl && !empty($cv['text'])) {
                         $parts = preg_split('/\s*-\s*/', $cv['text']);
                         $last  = trim(end($parts));
                         if (preg_match('#https?://[^\s]+#i', $last, $m)) $applyUrl = $m[0];
                     }
                     if ($applyUrl) $row['apply'] = $applyUrl;
                 }
             }
 
             // Only include if status is "open"
             if (!empty($row['name']) && $statusText === 'open') {
                 $jobs[] = $row;
             }
         }
 
         set_transient(self::TRANSIENT_KEY, $jobs, (int)$s['cache_minutes'] * MINUTE_IN_SECONDS);
         return $jobs;
     }
 
     /* =========================
        UTIL
        ========================= */
 
     public function maybe_flush_cache_on_save() {
         if (is_admin() && current_user_can('manage_options') && isset($_GET['mjl_flush_cache'])) {
             delete_transient(self::TRANSIENT_KEY);
         }
     }
 }
 
 new Monday_Jobs_Lite();