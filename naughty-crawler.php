<?php
/*
Plugin Name: Naughty Crawler
Description: API data crawling plugin for NaughtyWP theme
Version: 1.0
Author: Avdbapi.com
*/

if (!defined('ABSPATH')) {
    exit;
}

class NaughtyCrawler {
    private $api_url = 'https://avdbapi.com/api.php/provide/vod/';
    public $ui_log_messages = array(); // Property to store log messages for UI
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_crawl_data', array($this, 'ajax_crawl_data'));
        add_action('wp_ajax_crawl_single', array($this, 'ajax_crawl_single'));
        add_action('wp_ajax_crawl_by_category', array($this, 'ajax_crawl_by_category'));
        add_action('wp_ajax_crawl_by_page_range', array($this, 'ajax_crawl_by_page_range'));
        
        // Register taxonomy for actor and director
        add_action('init', array($this, 'register_taxonomies'));
    }

    public function register_taxonomies() {
        // Register taxonomy for actor
        register_taxonomy('actor', 'post', array(
            'hierarchical' => false,
            'labels' => array(
                'name' => 'Actors',
                'singular_name' => 'Actor',
                'menu_name' => 'Actors',
                'all_items' => 'All Actors',
                'edit_item' => 'Edit Actor',
                'view_item' => 'View Actor',
                'update_item' => 'Update Actor',
                'add_new_item' => 'Add New Actor',
                'new_item_name' => 'New Actor Name',
                'search_items' => 'Search Actors',
                'popular_items' => 'Popular Actors',
                'separate_items_with_commas' => 'Separate actors with commas',
                'add_or_remove_items' => 'Add or remove actors',
                'choose_from_most_used' => 'Choose from the most used actors',
                'not_found' => 'No actors found',
            ),
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'actor'),
        ));

        // Register taxonomy for director
        register_taxonomy('director', 'post', array(
            'hierarchical' => false,
            'labels' => array(
                'name' => 'Directors',
                'singular_name' => 'Director',
                'menu_name' => 'Directors',
                'all_items' => 'All Directors',
                'edit_item' => 'Edit Director',
                'view_item' => 'View Director',
                'update_item' => 'Update Director',
                'add_new_item' => 'Add New Director',
                'new_item_name' => 'New Director Name',
                'search_items' => 'Search Directors',
                'popular_items' => 'Popular Directors',
                'separate_items_with_commas' => 'Separate directors with commas',
                'add_or_remove_items' => 'Add or remove directors',
                'choose_from_most_used' => 'Choose from the most used directors',
                'not_found' => 'No directors found',
            ),
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'director'),
        ));
    }

    public function add_admin_menu() {
        add_menu_page(
            'Naughty Crawler',
            'Naughty Crawler',
            'manage_options',
            'naughty-crawler',
            array($this, 'admin_page'),
            'dashicons-download'
        );
    }

    public function register_settings() {
        register_setting('naughty_crawler_settings', 'naughty_crawler_api_url');
        register_setting('naughty_crawler_settings', 'naughty_crawler_batch_limit', array('default' => 50));
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Naughty Crawler</h1>

            <div class="naughty-crawler-layout">
                <div class="naughty-crawler-main-content">
                    <form method="post" action="options.php">
                        <?php settings_fields('naughty_crawler_settings'); ?>
                        <div class="card">
                            <h3>API Settings</h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="api_url">API URL</label></th>
                                    <td>
                                        <input type="text" id="api_url" name="naughty_crawler_api_url" value="<?php echo esc_attr(get_option('naughty_crawler_api_url', $this->api_url)); ?>" class="regular-text" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="batch_limit">Items per Batch Processing</label></th>
                                    <td>
                                        <input type="number" id="batch_limit" name="naughty_crawler_batch_limit" value="<?php echo esc_attr(get_option('naughty_crawler_batch_limit', 50)); ?>" min="1" max="1000" class="small-text" />
                                        <p class="description">Limit the number of movies processed per crawl request to avoid VPS overload. (Each API page can return up to 1000 movies)</p>
                                    </td>
                                </tr>
                            </table>
                            <?php submit_button(); ?>
                        </div>
                    </form>

                    <div class="card">
                        <h3>Crawl Data</h3>
                        <p>Only collect latest movies page 1</p>
                        <button id="crawl-all-button" class="button button-primary">Crawl New Movies</button>
                    </div>

                    <div class="card">
                        <h3>Crawl by Page Range</h3>
                        <p>Crawl data within a specific page range from the API.</p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="start-page">Start Page</label></th>
                                <td><input type="number" id="start-page" value="1" min="1" class="small-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="end-page">End Page</label></th>
                                <td><input type="number" id="end-page" value="1" min="1" class="small-text" /></td>
                            </tr>
                        </table>
                        <button id="crawl-page-range-button" class="button button-secondary">Crawl by Page Range</button>
                    </div>

                    <div class="card">
                        <h3>Crawl by Single Movie Code</h3>
                        <p>Crawl a single post by specific movie code from the API.</p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="movie-code">Movie Code</label></th>
                                <td><input type="text" id="movie-code" class="regular-text" placeholder="Example: FC2-PPV-1234567" /></td>
                            </tr>
                        </table>
                        <button id="crawl-single-button" class="button button-secondary">Crawl Single Movie</button>
                    </div>

                    <div class="card">
                        <h3>Crawl by Category</h3>
                        <p>Select a category to crawl related posts.</p>
                        <select id="category-select" class="regular-text">
                            <option value="">Select category...</option>
                            <option value="1">Censored</option>
                            <option value="2">Uncensored</option>
                            <option value="3">Uncensored Leaked</option>
                            <option value="4">Amateur</option>
                            <option value="5">Chinese AV</option>
                            <option value="6">Western</option>
                        </select>
                        <button id="crawl-category-button" class="button button-secondary">Crawl by Category</button>
                    </div>
                </div>

                <div class="naughty-crawler-log-sidebar">
                    <div id="realtime-log" class="card" style="display:none;">
                        <h3>Detailed Log</h3>
                        <div id="log-content" style="max-height: 500px; overflow-y: scroll; background: #f9f9f9; padding: 10px; border: 1px solid #eee; font-family: monospace; font-size: 15px;"></div>
                    </div>
                </div>
            </div>
        </div>
        <style>
            .naughty-crawler-layout {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                margin-top: 20px;
                align-items: flex-start;
            }
            .naughty-crawler-main-content {
                flex: 1.5;
                min-width: 300px;
                max-width: calc(60% - 10px);
            }
            .naughty-crawler-log-sidebar {
                flex: 1;
                min-width: 250px;
                max-width: calc(40% - 10px);
            }
            .naughty-crawler-main-content .card {
                margin-bottom: 20px;
            }
            .naughty-crawler-log-sidebar .card {
                margin-bottom: 0;
                width: 100%;
            }
        </style>
        <script>
        jQuery(document).ready(function($) {
            var $logDiv = $('#realtime-log');
            var $logContent = $('#log-content');

            function appendToLog(messages) {
                $logDiv.show();
                $.each(messages, function(index, message) {
                    $logContent.append('<div>' + message + '</div>');
                });
                $logContent.scrollTop($logContent[0].scrollHeight);
            }

            function clearLog() {
                $logContent.empty();
                $logDiv.hide();
            }

            function disableButtons(disabled) {
                $('#crawl-all-button, #crawl-page-range-button, #crawl-single-button, #crawl-category-button').prop('disabled', disabled);
            }

            $('#crawl-all-button').click(function() {
                var currentOffset = 0;

                function crawlNextBatchAll() {
                    disableButtons(true);
                    if (currentOffset === 0) {
                        clearLog();
                    }
                    appendToLog(['Crawling new movies (offset: ' + currentOffset + ')...']);

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'crawl_data',
                            offset: currentOffset
                        },
                        success: function(response) {
                            if (response.success) {
                                if (response.data.logs) {
                                    appendToLog(response.data.logs);
                                }
                                
                                if (response.data.has_more) {
                                    currentOffset = response.data.next_offset;
                                    setTimeout(crawlNextBatchAll, 1000); // Wait 1 second before crawling next batch
                                } else {
                                    appendToLog(['Finished crawling new movies on page 1.']);
                                    disableButtons(false);
                                }
                            } else {
                                if (response.data.logs) {
                                    appendToLog(response.data.logs);
                                }
                                appendToLog(['Error crawling new movies: ' + response.data.message]);
                                disableButtons(false);
                            }
                        },
                        error: function() {
                            appendToLog(['An error occurred while crawling all data!']);
                            disableButtons(false);
                        }
                    });
                }

                crawlNextBatchAll(); // Start the first crawl batch
            });

            $('#crawl-page-range-button').click(function() {
                var startPage = parseInt($('#start-page').val());
                var endPage = parseInt($('#end-page').val());
                var currentOffset = 0;

                if (isNaN(startPage) || startPage < 1) {
                    appendToLog(['Invalid start page!']);
                    return;
                }
                if (isNaN(endPage) || endPage < startPage) {
                    appendToLog(['Invalid end page or less than start page!']);
                    return;
                }

                function crawlNextBatch() {
                    disableButtons(true);
                    if (currentOffset === 0 && startPage === parseInt($('#start-page').val())) {
                        clearLog();
                    }
                    appendToLog(['Crawling from page ' + startPage + ' to page ' + endPage + ' (offset: ' + currentOffset + ')...']);
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'crawl_by_page_range',
                            start_page: startPage,
                            end_page: endPage,
                            offset: currentOffset
                        },
                        success: function(response) {
                            if (response.success) {
                                if (response.data.logs) {
                                    appendToLog(response.data.logs);
                                }
                                
                                if (response.data.has_more) {
                                    currentOffset = response.data.next_offset;
                                    setTimeout(crawlNextBatch, 1000);
                                } else if (startPage < endPage) {
                                    startPage++;
                                    currentOffset = 0;
                                    setTimeout(crawlNextBatch, 1000);
                                } else {
                                    disableButtons(false);
                                }
                            } else {
                                if (response.data.logs) {
                                    appendToLog(response.data.logs);
                                }
                                appendToLog(['An error occurred while crawling by page range!']);
                                disableButtons(false);
                            }
                        },
                        error: function() {
                            appendToLog(['An error occurred while crawling by page range!']);
                            disableButtons(false);
                        }
                    });
                }

                crawlNextBatch();
            });

            $('#crawl-single-button').click(function() {
                var movieCode = $('#movie-code').val();
                
                if (!movieCode) {
                    appendToLog(['Please enter movie code!']);
                    return;
                }
                
                disableButtons(true);
                clearLog();
                appendToLog(['Crawling movie ' + movieCode + '...']);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'crawl_single',
                        movie_code: movieCode
                    },
                    success: function(response) {
                        if (response.success) {
                            if (response.data.logs) {
                                appendToLog(response.data.logs);
                            }
                        } else {
                            if (response.data.logs) {
                                appendToLog(response.data.logs);
                            }
                        }
                        disableButtons(false);
                    },
                    error: function() {
                        appendToLog(['An error occurred while crawling single movie!']);
                        disableButtons(false);
                    }
                });
            });

            $('#crawl-category-button').click(function() {
                var category = $('#category-select').val();
                
                if (!category) {
                    appendToLog(['Please select a category!']);
                    return;
                }
                
                disableButtons(true);
                clearLog();
                appendToLog(['Crawling category...']);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'crawl_by_category',
                        category: category
                    },
                    success: function(response) {
                        if (response.success) {
                            if (response.data.logs) {
                                appendToLog(response.data.logs);
                            }
                        } else {
                            if (response.data.logs) {
                                appendToLog(response.data.logs);
                            }
                        }
                        disableButtons(false);
                    },
                    error: function() {
                        appendToLog(['An error occurred while crawling by category!']);
                        disableButtons(false);
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function ajax_crawl_data() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        $this->ui_log_messages = array();

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

        $api_url = get_option('naughty_crawler_api_url', $this->api_url);
        $response = wp_remote_get($api_url . '?ac=detail&pg=1'); // Always crawl page 1

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Error connecting to API: ' . $response->get_error_message()));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['list'])) {
            wp_send_json_error(array('message' => 'Could not parse JSON data or no post list available.'));
        }

        $batch_limit = (int) get_option('naughty_crawler_batch_limit', 50);
        $items_to_process = array_slice($data['list'], $offset, $batch_limit);
        
        $remaining_items_on_page = count($data['list']) - ($offset + count($items_to_process));
        $has_more_items = ($remaining_items_on_page > 0);

        $count = 0;
        $existing_count = 0;
        $processed_movie_codes = [];
        foreach ($items_to_process as $item) {
            if ($this->process_item($item)) {
                $count++;
            } else {
                $existing_count++;
            }
            $processed_movie_codes[] = $item['movie_code'];
        }

        $message = "Successfully crawled {$count} posts. ({$existing_count} posts already exist).";
        if (!empty($processed_movie_codes)) {
            $message .= "<br>Movies processed in batch: " . implode(', ', $processed_movie_codes);
        }
        if ($has_more_items) {
            $message .= "<br>Still {$remaining_items_on_page} posts on this page. Please run again to continue.";
        }
        wp_send_json_success(array(
            'message' => $message,
            'has_more' => $has_more_items,
            'next_offset' => $offset + $batch_limit,
            'logs' => $this->ui_log_messages
        ));
    }

    public function ajax_crawl_single() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        $this->ui_log_messages = array();

        $movie_code = isset($_POST['movie_code']) ? sanitize_text_field($_POST['movie_code']) : '';
        if (!$movie_code) {
            wp_send_json_error(array('message' => 'Please enter movie code!'));
        }

        $api_url = get_option('naughty_crawler_api_url', $this->api_url);
        $response = wp_remote_get($api_url . '?ac=detail&wd=' . urlencode($movie_code));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Error connecting to API: ' . $response->get_error_message()));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['list']) || empty($data['list'])) {
            wp_send_json_error(array('message' => 'Movie not found with code ' . esc_html($movie_code)));
        }

        $item = $data['list'][0];
        if ($this->process_item($item)) {
            wp_send_json_success(array('message' => "Successfully crawled movie " . esc_html($movie_code) . "!", 'logs' => $this->ui_log_messages));
        } else {
            wp_send_json_error(array('message' => "Movie " . esc_html($movie_code) . " already exists!", 'logs' => $this->ui_log_messages));
        }
    }

    public function ajax_crawl_by_category() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        $this->ui_log_messages = array();

        $category_id = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        if (!$category_id) {
            wp_send_json_error(array('message' => 'Please select a category!'));
        }

        $api_url = get_option('naughty_crawler_api_url', $this->api_url);
        $response = wp_remote_get($api_url . '?ac=detail&t=' . urlencode($category_id));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Error connecting to API: ' . $response->get_error_message()));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['list'])) {
            wp_send_json_error(array('message' => 'Could not parse JSON data or no post list for this category.'));
        }

        $batch_limit = (int) get_option('naughty_crawler_batch_limit', 50);
        $items_to_process = array_slice($data['list'], 0, $batch_limit);
        $remaining_items = count($data['list']) - count($items_to_process);

        $count = 0;
        $existing_count = 0;
        $processed_movie_codes = [];
        foreach ($items_to_process as $item) {
            if ($this->process_item($item)) {
                $count++;
            } else {
                $existing_count++;
            }
            $processed_movie_codes[] = $item['movie_code'];
        }

        $message = "Successfully crawled {$count} posts for the category. ({$existing_count} posts already exist).";
        if (!empty($processed_movie_codes)) {
            $message .= "<br>Movies processed in batch: " . implode(', ', $processed_movie_codes);
        }
        if ($remaining_items > 0) {
            $message .= "<br>Still {$remaining_items} posts on this page. Please run again to continue.";
        }
        wp_send_json_success(array('message' => $message, 'logs' => $this->ui_log_messages));
    }

    public function ajax_crawl_by_page_range() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        $this->ui_log_messages = array();

        $start_page = isset($_POST['start_page']) ? intval($_POST['start_page']) : 1;
        $end_page = isset($_POST['end_page']) ? intval($_POST['end_page']) : 1;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

        if ($start_page < 1 || $end_page < $start_page) {
            wp_send_json_error(array('message' => 'Invalid page range!'));
        }

        $api_url = get_option('naughty_crawler_api_url', $this->api_url);
        $batch_limit = (int) get_option('naughty_crawler_batch_limit', 50);
        $total_crawled = 0;
        $total_existing = 0;
        $error_messages = [];
        $processed_movie_codes_total = [];
        $has_more_items = false;
        $current_page = $start_page;
        $current_offset = $offset;

        $response = wp_remote_get($api_url . '?ac=detail&pg=' . $current_page);

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Error connecting to API for page ' . $current_page . ': ' . $response->get_error_message()));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['list'])) {
            wp_send_json_error(array('message' => 'Could not parse JSON data or no post list for page ' . $current_page));
        }

        $items_to_process = array_slice($data['list'], $current_offset, $batch_limit);
        
        $remaining_items = count($data['list']) - ($current_offset + count($items_to_process));
        if ($remaining_items > 0) {
            $has_more_items = true;
        }

        foreach ($items_to_process as $item) {
            if ($this->process_item($item)) {
                $total_crawled++;
            } else {
                $total_existing++;
            }
            $processed_movie_codes_total[] = $item['movie_code'];
        }

        $message = "Processed movie batch from {$current_offset} to " . ($current_offset + count($items_to_process)) . " on page {$current_page}. ";
        $message .= "Success: {$total_crawled} posts, Existed: {$total_existing} posts.";
        
        if (!empty($processed_movie_codes_total)) {
            $message .= "<br>Movies processed in batch: " . implode(', ', $processed_movie_codes_total);
        }

        if ($has_more_items) {
            $next_offset = $current_offset + $batch_limit;
            $message .= "<br>Still {$remaining_items} posts on page {$current_page}. Please run again with offset {$next_offset} to continue.";
        } elseif ($current_page < $end_page) {
            $message .= "<br>Finished page {$current_page}. Please run again with start page " . ($current_page + 1) . " to continue.";
        }

        wp_send_json_success(array(
            'message' => $message,
            'has_more' => $has_more_items,
            'next_offset' => $current_offset + $batch_limit,
            'current_page' => $current_page,
            'remaining_items' => $remaining_items,
            'logs' => $this->ui_log_messages
        ));
    }

    private function process_item($item) {
        $movie_code = esc_html($item['movie_code']);

        // Check if post already exists
        $existing_post = get_posts(array(
            'meta_key' => 'movie_code',
            'meta_value' => $item['movie_code'],
            'post_type' => 'post',
            'posts_per_page' => 1
        ));

        if (!empty($existing_post)) {
            $this->add_ui_log_message($movie_code . ': skipped as already exists.');
            return false;
        }

        // Create category from type_name if it doesn't exist
        $type_term = term_exists($item['type_name'], 'category');
        if (!$type_term) {
            $type_term = wp_insert_term($item['type_name'], 'category');
        }
        $type_id = is_array($type_term) ? $type_term['term_id'] : $type_term;

        // Create new post
        $post_data = array(
            'post_title' => $item['name'],
            'post_content' => $item['description'],
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_category' => array($type_id)
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            $this->add_ui_log_message($movie_code . ': error creating post.');
            return false;
        }

        // Add meta data
        update_post_meta($post_id, 'movie_code', $item['movie_code']);
        update_post_meta($post_id, 'origin_name', $item['origin_name']);
        update_post_meta($post_id, 'poster_url', $item['poster_url']);
        update_post_meta($post_id, 'thumb_url', $item['thumb_url']);
        
        $embed_link = $item['episodes']['server_data']['Full']['link_embed'];
        $iframe_html = '<iframe src="' . esc_attr($embed_link) . '" title="' . esc_attr($movie_code) . '" allowfullscreen></iframe>';
        update_post_meta($post_id, 'embed', $iframe_html);

        update_post_meta($post_id, 'quality', $item['quality']);
        update_post_meta($post_id, 'year', $item['year']);
        update_post_meta($post_id, 'country', implode(', ', $item['country']));
        update_post_meta($post_id, 'preview_url', $item['episodes']['server_data']['Full']['link_embed']);
        update_post_meta($post_id, 'meta_description', $item['description']);

        // Handle duration if available
        if (!empty($item['time'])) {
            $time_parts = explode(':', $item['time']);
            $hours = 0;
            $minutes = 0;
            $seconds = 0;

            if (count($time_parts) == 3) {
                $hours = intval($time_parts[0]);
                $minutes = intval($time_parts[1]);
                $seconds = intval($time_parts[2]);
            } elseif (count($time_parts) == 2) {
                $minutes = intval($time_parts[0]);
                $seconds = intval($time_parts[1]);
            }
            update_post_meta($post_id, 'duration_h', $hours);
            update_post_meta($post_id, 'duration_m', $minutes);
            update_post_meta($post_id, 'duration_s', $seconds);
        }

        // Add tags from category
        if (!empty($item['category'])) {
            wp_set_post_tags($post_id, $item['category'], true);
        }

        // Add movie_code to tags
        wp_set_post_tags($post_id, $item['movie_code'], true);

        // Add actor
        if (!empty($item['actor'])) {
            foreach ($item['actor'] as $actor_name) {
                $actor_name = trim($actor_name);
                if (!empty($actor_name)) {
                    $actor_term = term_exists($actor_name, 'actor');
                    if (!$actor_term) {
                        $actor_term = wp_insert_term($actor_name, 'actor');
                    }
                    if (!is_wp_error($actor_term)) {
                        wp_set_object_terms($post_id, $actor_name, 'actor', true);
                    }
                }
            }
        }

        // Add director
        if (!empty($item['director'])) {
            foreach ($item['director'] as $director_name) {
                $director_name = trim($director_name);
                if (!empty($director_name)) {
                    $director_term = term_exists($director_name, 'director');
                    if (!$director_term) {
                        $director_term = wp_insert_term($director_name, 'director');
                    }
                    if (!is_wp_error($director_term)) {
                        wp_set_object_terms($post_id, $director_name, 'director', true);
                    }
                }
            }
        }

        // Download and set thumbnail
        if (!empty($item['poster_url'])) {
            $this->set_featured_image($post_id, $item['poster_url']);
        }

        $this->add_ui_log_message($movie_code . ' - done.');
        return true;
    }

    private function set_featured_image($post_id, $image_url) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $tmp = download_url($image_url);
        if (is_wp_error($tmp)) {
            return false;
        }

        $file_array = array(
            'name' => basename($image_url),
            'tmp_name' => $tmp
        );

        $attachment_id = media_handle_sideload($file_array, $post_id);
        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            return false;
        }

        set_post_thumbnail($post_id, $attachment_id);
        return true;
    }

    public function add_ui_log_message($message) {
        $this->ui_log_messages[] = $message;
    }
}

new NaughtyCrawler(); 
