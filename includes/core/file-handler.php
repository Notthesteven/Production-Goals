<?php
/**
 * File handler functionality for Production Goals
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Production_File_Handler {
    private $file_table;
    private $log_table;
    
    public function __construct() {
        global $wpdb;
        $this->file_table = $wpdb->prefix . 'file_downloads';
        $this->log_table = $wpdb->prefix . 'file_download_logs';
        
        // Add hooks
        add_action('production_project_saved', array($this, 'handle_project_file_upload'), 10, 2);
        add_action('init', array($this, 'process_download'));
        add_shortcode('download_counter', array($this, 'download_counter_shortcode'));
    }
    
    /**
     * Generate a random token for file downloads
     */
    public function generate_random_token() {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $random_token = '';
        for ($i = 0; $i < 10; $i++) {
            $random_token .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $random_token;
    }
    
    /**
     * Handle file upload when a project is created or updated
     */
    public function handle_project_file_upload($project_id, $is_update = false) {
        global $wpdb;
        
        // Check if a file was uploaded
        if (!isset($_FILES['project_file']) || empty($_FILES['project_file']['tmp_name'])) {
            return; // No file uploaded
        }
        
        $file = $_FILES['project_file'];
        $upload_dir = WP_CONTENT_DIR . '/protected-files/';
        
        // Ensure the upload directory exists
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
                wp_die('Failed to create directory: ' . $upload_dir);
            }
        }
        
        // Sanitize file name
        $file_name = sanitize_file_name($file['name']);
        $upload_path = trailingslashit($upload_dir) . $file_name;
        
        // Move the uploaded file to the secure directory
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $random_token = $this->generate_random_token();
            
            // Get allowed roles
            $allowed_roles = isset($_POST['allowed_roles']) ? implode(',', $_POST['allowed_roles']) : '';
            $project_name = sanitize_text_field($_POST['project_name']);
            
            if ($is_update) {
                // Check if this project already has a file
                $existing_file = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$this->file_table} WHERE project_id = %d",
                    $project_id
                ));
                
                if ($existing_file) {
                    // Update existing file record
                    $wpdb->update(
                        $this->file_table,
                        array(
                            'file_name' => $project_name . ' - Files',
                            'file_url'  => $file_name,
                            'random_token' => $random_token,
                            'allowed_roles' => $allowed_roles,
                        ),
                        array('id' => $existing_file->id)
                    );
                } else {
                    // Insert new file record
                    $wpdb->insert($this->file_table, array(
                        'file_name' => $project_name . ' - Files',
                        'file_url'  => $file_name,
                        'random_token' => $random_token,
                        'allowed_roles' => $allowed_roles,
                        'project_id' => $project_id
                    ));
                }
            } else {
                // Insert new file record
                $wpdb->insert($this->file_table, array(
                    'file_name' => $project_name . ' - Files',
                    'file_url'  => $file_name,
                    'random_token' => $random_token,
                    'allowed_roles' => $allowed_roles,
                    'project_id' => $project_id
                ));
            }
            
            if ($wpdb->last_error) {
                error_log('Database Insert Error: ' . $wpdb->last_error);
                return false;
            }
            
            return true;
        } else {
            error_log('Failed to upload file for project ID: ' . $project_id);
            return false;
        }
    }
    
    /**
     * Get file information for a project
     */
    public function get_project_file($project_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->file_table} WHERE project_id = %d",
            $project_id
        ));
    }
    
    /**
     * Delete file associated with a project
     */
    public function delete_project_file($project_id) {
        global $wpdb;
        
        $file = $this->get_project_file($project_id);
        
        if ($file) {
            // Delete physical file if it exists
            $file_path = WP_CONTENT_DIR . '/protected-files/' . $file->file_url;
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
            
            // Delete database record
            $wpdb->delete($this->file_table, array('id' => $file->id));
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Generate download URL for a file
     */
    public function get_download_url($file) {
        if ($file && isset($file->random_token)) {
            return home_url('/?download_token=' . esc_attr($file->random_token));
        }
        return false;
    }
    
    /**
     * Generate shortcode for download counter
     */
    public function get_download_shortcode($file) {
        if ($file && isset($file->id)) {
            return '[download_counter id="' . esc_attr($file->id) . '"]';
        }
        return false;
    }
    
    /**
     * Process file download
     */
    public function process_download() {
        if (isset($_GET['download_token'])) {
            global $wpdb;
            
            $token = sanitize_text_field($_GET['download_token']);
            $file = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->file_table} WHERE random_token = %s", 
                $token
            ));
            
            if ($file) {
                // Check if the user is logged in
                if (!is_user_logged_in()) {
                    wp_die('You must be logged in to download this file.');
                }
                
                // Get the user's roles
                $user = wp_get_current_user();
                $user_roles = $user->roles; // Array of current user's roles
                
                // Get allowed roles for the file
                if (!empty($file->allowed_roles)) {
                    $allowed_roles = explode(',', $file->allowed_roles);
                    
                    // Check if the user has an allowed role
                    $has_permission = false;
                    foreach ($user_roles as $role) {
                        if (in_array($role, $allowed_roles)) {
                            $has_permission = true;
                            break;
                        }
                    }
                    
                    if (!$has_permission) {
                        wp_die('You do not have permission to download this file.');
                    }
                }
                
                // Increment the download count
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$this->file_table} SET download_count = download_count + 1, last_download = %s WHERE id = %d",
                        current_time('mysql'),
                        $file->id
                    )
                );
                
                // Log the download
                $wpdb->insert(
                    $this->log_table,
                    array(
                        'file_id' => $file->id,
                        'user_id' => get_current_user_id(),
                        'download_date' => current_time('mysql')
                    )
                );
                
                // Serve the file securely
                $protected_path = WP_CONTENT_DIR . '/protected-files/' . basename($file->file_url);
                
                if (file_exists($protected_path)) {
                    // Clear output buffering
                    if (ob_get_level()) {
                        ob_end_clean();
                    }
                    
                    // Send headers for file download
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . basename($protected_path) . '"');
                    header('Content-Length: ' . filesize($protected_path));
                    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                    header('Pragma: public');
                    header('Expires: 0');
                    
                    // Read and output the file
                    readfile($protected_path);
                    exit;
                } else {
                    wp_die('File not found.');
                }
            } else {
                wp_die('Invalid download token.');
            }
        }
    }
    
    /**
     * Download counter shortcode
     */
    public function download_counter_shortcode($atts) {
        global $wpdb;
        
        // Extract attributes and set default
        $atts = shortcode_atts(array('id' => 0), $atts, 'download_counter');
        $file_id = intval($atts['id']);
        
        // Retrieve the file's download count
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT download_count FROM {$this->file_table} WHERE id = %d", 
            $file_id
        ));
        
        // Display the download count or an error message
        if ($file) {
            return '<p><strong>Downloads:</strong> ' . esc_html($file->download_count) . '</p>';
        } else {
            return '<p><strong>Download count not available for this file.</strong></p>';
        }
    }
    
    /**
     * Get download logs for a file
     */
    public function get_file_download_logs($file_id, $limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->log_table} WHERE file_id = %d ORDER BY download_date DESC LIMIT %d",
            $file_id, $limit
        ));
    }
}

// Initialize the file handler
global $production_file_handler;
$production_file_handler = new Production_File_Handler();
