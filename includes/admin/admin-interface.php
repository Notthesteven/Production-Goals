<?php
/**
 * Admin Interface for Production Goals
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Production_Goals_Admin {
    private $production_file_handler;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $production_file_handler;
        $this->production_file_handler = $production_file_handler;
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add AJAX handlers
        add_action('wp_ajax_pg_add_project', array($this, 'ajax_add_project'));
        add_action('wp_ajax_pg_edit_project', array($this, 'ajax_edit_project'));
        add_action('wp_ajax_pg_delete_project', array($this, 'ajax_delete_project'));
        add_action('wp_ajax_pg_add_part', array($this, 'ajax_add_part'));
        add_action('wp_ajax_pg_edit_part', array($this, 'ajax_edit_part'));
        add_action('wp_ajax_pg_delete_part', array($this, 'ajax_delete_part'));
        add_action('wp_ajax_pg_start_project', array($this, 'ajax_start_project'));
        add_action('wp_ajax_pg_complete_project', array($this, 'ajax_complete_project'));
        add_action('wp_ajax_pg_delete_completed', array($this, 'ajax_delete_completed'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Production Goals',
            'Production Goals',
            'manage_options',
            'production-goals',
            array($this, 'render_admin_page'),
            'dashicons-admin-tools',
            20
        );
        
        add_submenu_page(
            'production-goals',
            'Production Goals',
            'Manage Projects',
            'manage_options',
            'production-goals',
            array($this, 'render_admin_page')
        );
        
        add_submenu_page(
            'production-goals',
            'Settings',
            'Settings',
            'manage_options',
            'production-goals-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        global $wpdb;
        
        $projects_table = $wpdb->prefix . "production_projects";
        $parts_table = $wpdb->prefix . "production_parts";
        $submissions_table = $wpdb->prefix . "production_submissions";
        $completed_table = $wpdb->prefix . "production_completed";
        
        // Get all projects
        $projects = Production_Goals_DB::get_projects();
        
        // Get selected project ID
        $selected_project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : (isset($projects[0]) ? $projects[0]->id : 0);
        
        // Get parts for selected project
        $parts = Production_Goals_DB::get_project_parts($selected_project_id);
        
        // Check if the project is active
        $active_project = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $parts_table WHERE project_id = %d AND start_date IS NOT NULL",
            $selected_project_id
        ));
        
        // Get materials for selected project
        $materials = Production_Goals_DB::get_project_materials($selected_project_id);
        
        // Get file information for the selected project
        $project_file = $this->production_file_handler->get_project_file($selected_project_id);
        
        // Get completed goals for selected project
        $completed_goals = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $completed_table WHERE project_id = %d ORDER BY completed_date DESC",
            $selected_project_id
        ));
        ?>
        <div class="wrap pg-admin-wrap">
            <h1>Production Goals</h1>
            
            <div class="pg-admin-tabs">
                <div class="pg-admin-tab-nav">
                    <button class="pg-admin-tab-button active" data-tab="manage-projects">Manage Projects</button>
                    <button class="pg-admin-tab-button" data-tab="project-files">Project Files</button>
                    <button class="pg-admin-tab-button" data-tab="parts-goals">Parts & Goals</button>
                    <button class="pg-admin-tab-button" data-tab="completed-goals">Completed Goals</button>
                    <button class="pg-admin-tab-button" data-tab="shortcodes">Shortcodes</button>
                </div>
                
                <div class="pg-admin-tab-content active" id="manage-projects">
                    <div class="pg-admin-card">
                        <h2>Add New Project</h2>
                        <form id="add-project-form" enctype="multipart/form-data">
                            <div class="pg-form-row">
                                <label for="project_name">Project Name:</label>
                                <input type="text" id="project_name" name="project_name" placeholder="New Project Name" required>
                            </div>
                            
                            <div class="pg-form-row">
                                <label for="project_material">Material:</label>
                                <input type="text" id="project_material" name="project_material" placeholder="Material (e.g., PLA, ABS)" required>
                                <p class="description">Separate multiple materials with commas (e.g., PLA, ABS)</p>
                            </div>
                            
                            <div class="pg-form-row">
                                <label for="project_url">Project URL:</label>
                                <input type="url" id="project_url" name="project_url" placeholder="https://example.com/project-page" required>
                            </div>
                            
                            <div class="pg-form-row">
                                <label for="project_file">Project File (ZIP):</label>
                                <input type="file" id="project_file" name="project_file" accept=".zip">
                            </div>
                            
                            <div class="pg-form-row">
                                <label><strong>Allowed Download Roles:</strong></label>
                                <div class="pg-role-checkboxes">
                                    <?php 
                                    $roles = wp_roles()->roles;
                                    foreach ($roles as $role_key => $role): ?>
                                        <label class="pg-role-checkbox">
                                            <input type="checkbox" name="allowed_roles[]" value="<?php echo esc_attr($role_key); ?>">
                                            <?php echo esc_html($role['name']); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="pg-form-row">
                                <button type="submit" class="button button-primary">Add Project</button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="pg-admin-card">
                        <h2>Select Project</h2>
                        <form method="GET">
                            <input type="hidden" name="page" value="production-goals">
                            <div class="pg-form-row">
                                <select name="project_id" id="select_project" onchange="this.form.submit()">
                                    <?php foreach ($projects as $project): ?>
                                        <option value="<?php echo esc_attr($project->id); ?>" <?php selected($selected_project_id, $project->id); ?>>
                                            <?php echo esc_html($project->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                    
                    <?php if ($selected_project_id > 0): ?>
                    <div class="pg-admin-card">
                        <h2>Edit Selected Project</h2>
                        <form id="edit-project-form" enctype="multipart/form-data">
                            <input type="hidden" name="project_id" value="<?php echo esc_attr($selected_project_id); ?>">
                            
                            <div class="pg-form-row">
                                <label for="edit_project_name">Project Name:</label>
                                <input type="text" id="edit_project_name" name="project_name" 
                                       value="<?php echo esc_html($wpdb->get_var($wpdb->prepare("SELECT name FROM $projects_table WHERE id = %d", $selected_project_id))); ?>" required>
                            </div>
                            
                            <div class="pg-form-row">
                                <label for="edit_project_material">Material:</label>
                                <input type="text" id="edit_project_material" name="project_material" 
                                       value="<?php echo esc_html(implode(', ', $materials)); ?>" 
                                       placeholder="e.g., PLA, ABS">
                                <p class="description">Separate multiple materials with commas (e.g., PLA, ABS)</p>
                            </div>
                            
                            <div class="pg-form-row">
                                <label for="edit_project_url">Project URL:</label>
                                <input type="url" id="edit_project_url" name="project_url" 
                                       value="<?php echo esc_html($wpdb->get_var($wpdb->prepare("SELECT url FROM $projects_table WHERE id = %d", $selected_project_id))); ?>" 
                                       placeholder="https://example.com/project-page" required>
                            </div>
                            
                            <div class="pg-form-row">
                                <label for="edit_project_file">Update Project File (ZIP):</label>
                                <input type="file" id="edit_project_file" name="project_file" accept=".zip">
                                <?php if ($project_file): ?>
                                    <p><em>Current file: <?php echo esc_html($project_file->file_url); ?></em></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="pg-form-row">
                                <label><strong>Allowed Download Roles:</strong></label>
                                <div class="pg-role-checkboxes">
                                    <?php 
                                    $roles = wp_roles()->roles;
                                    $allowed_roles = $project_file ? explode(',', $project_file->allowed_roles) : [];
                                    foreach ($roles as $role_key => $role): ?>
                                        <label class="pg-role-checkbox">
                                            <input type="checkbox" name="allowed_roles[]" value="<?php echo esc_attr($role_key); ?>" 
                                                <?php if (in_array($role_key, $allowed_roles)) echo 'checked'; ?>>
                                            <?php echo esc_html($role['name']); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="pg-form-row">
                                <button type="submit" class="button button-primary">Save Changes</button>
                                <button type="button" id="delete-project-btn" class="button button-secondary button-delete">Delete Project</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="pg-admin-tab-content" id="project-files">
                    <?php if ($selected_project_id > 0): ?>
                    <div class="pg-admin-card">
                        <h2>Project Files</h2>
                        <?php if ($project_file): ?>
                            <div class="pg-file-info">
                                <h3><?php echo esc_html($project_file->file_name); ?></h3>
                                <p><strong>Downloads:</strong> <?php echo esc_html($project_file->download_count); ?></p>
                                <p><strong>Last Downloaded:</strong> <?php echo $project_file->last_download ? esc_html(date('Y-m-d H:i:s', strtotime($project_file->last_download))) : 'Never'; ?></p>
                                
                                <?php 
                                $download_url = $this->production_file_handler->get_download_url($project_file);
                                $shortcode = $this->production_file_handler->get_download_shortcode($project_file);
                                ?>
                                
                                <div class="pg-file-actions">
                                    <button type="button" class="button button-secondary copy-to-clipboard" data-clipboard="<?php echo esc_url($download_url); ?>">Copy Download URL</button>
                                    <button type="button" class="button button-secondary copy-to-clipboard" data-clipboard="<?php echo esc_attr($shortcode); ?>">Copy Shortcode</button>
                                </div>
                                
                                <!-- Download Logs -->
                                <?php
                                $logs = $this->production_file_handler->get_file_download_logs($project_file->id);
                                
                                if ($logs): ?>
                                    <h4>Recent Downloads</h4>
                                    <table class="widefat">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Date</th>
                                                <th>Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($logs as $log):
                                                $user = $log->user_id ? get_userdata($log->user_id)->user_login : 'Guest';
                                                $download_date = new DateTime($log->download_date);
                                            ?>
                                                <tr>
                                                    <td><?php echo esc_html($user); ?></td>
                                                    <td><?php echo esc_html($download_date->format('Y-m-d')); ?></td>
                                                    <td><?php echo esc_html($download_date->format('H:i:s')); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p><em>No downloads recorded yet.</em></p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p><em>No file has been uploaded for this project yet. Use the form in the "Manage Projects" tab to upload a file.</em></p>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                        <div class="pg-admin-card">
                            <p>Please select a project first.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="pg-admin-tab-content" id="parts-goals">
                    <?php if ($selected_project_id > 0): ?>
                    <div class="pg-admin-card">
                        <h2>Manage Parts</h2>
                        
                        <div class="pg-parts-list">
                            <?php if (empty($parts)): ?>
                                <p>No parts defined for this project. Add some parts below.</p>
                            <?php else: ?>
                                <table class="widefat">
                                    <thead>
                                        <tr>
                                            <th>Part Name</th>
                                            <th>Goal</th>
                                            <th>Progress</th>
                                            <th>Length (m)</th>
                                            <th>Weight (g)</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($parts as $part): ?>
                                            <tr>
                                                <td><?php echo esc_html($part->name); ?></td>
                                                <td><?php echo esc_html($part->goal); ?></td>
                                                <td>
                                                    <?php echo esc_html($part->progress); ?> 
                                                    <?php if ($part->goal > 0): ?>
                                                        (<?php echo round(($part->progress / $part->goal) * 100, 2); ?>%)
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo esc_html($part->estimated_length); ?></td>
                                                <td><?php echo esc_html($part->estimated_weight); ?></td>
                                                <td>
                                                    <button type="button" class="button button-small edit-part-btn" 
                                                            data-part-id="<?php echo esc_attr($part->id); ?>"
                                                            data-part-name="<?php echo esc_attr($part->name); ?>"
                                                            data-part-goal="<?php echo esc_attr($part->goal); ?>"
                                                            data-part-length="<?php echo esc_attr($part->estimated_length); ?>"
                                                            data-part-weight="<?php echo esc_attr($part->estimated_weight); ?>">
                                                        Edit
                                                    </button>
                                                    <button type="button" class="button button-small delete-part-btn" 
                                                            data-part-id="<?php echo esc_attr($part->id); ?>">
                                                        Delete
                                                    </button>
                                                    
                                                    <?php if ($active_project): ?>
                                                        <button type="button" class="button button-small view-contributions-btn" 
                                                                data-part-id="<?php echo esc_attr($part->id); ?>">
                                                            View Contributions
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            
                                            <?php if ($active_project): ?>
                                                <tr id="contributions-<?php echo esc_attr($part->id); ?>" class="contributions-row" style="display: none;">
                                                    <td colspan="6">
                                                        <div class="pg-contributions-container">
                                                            <h4>User Contributions for Current Goal</h4>
                                                            <?php
                                                            $user_contributions = $wpdb->get_results($wpdb->prepare(
                                                                "SELECT user_id, SUM(quantity) as total 
                                                                 FROM $submissions_table 
                                                                 WHERE part_id = %d AND created_at >= %s 
                                                                 GROUP BY user_id",
                                                                $part->id, $part->start_date ?? '1970-01-01 00:00:00'
                                                            ));
                                                            
                                                            if (!empty($user_contributions)): ?>
                                                                <ul>
                                                                    <?php foreach ($user_contributions as $contribution):
                                                                        $user_info = get_userdata($contribution->user_id);
                                                                        ?>
                                                                        <li><?php echo esc_html($user_info->user_login); ?>: <?php echo esc_html($contribution->total); ?></li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php else: ?>
                                                                <p>No contributions yet.</p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                        
                        <div class="pg-add-part">
                            <h3>Add New Part</h3>
                            <form id="add-part-form">
                                <input type="hidden" name="project_id" value="<?php echo esc_attr($selected_project_id); ?>">
                                
                                <div class="pg-form-row">
                                    <label for="part_name">Part Name:</label>
                                    <input type="text" id="part_name" name="part_name" required>
                                </div>
                                
                                <div class="pg-form-row">
                                    <label for="part_goal">Goal:</label>
                                    <input type="number" id="part_goal" name="part_goal" min="0" value="0" required>
                                </div>
                                
                                <div class="pg-form-row">
                                    <label for="estimated_length">Estimated Length (m):</label>
                                    <input type="number" id="estimated_length" name="estimated_length" step="0.01" min="0" value="0" required>
                                </div>
                                
                                <div class="pg-form-row">
                                    <label for="estimated_weight">Estimated Weight (g):</label>
                                    <input type="number" id="estimated_weight" name="estimated_weight" step="0.01" min="0" value="0" required>
                                </div>
                                
                                <div class="pg-form-row">
                                    <button type="submit" class="button button-primary">Add Part</button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Edit Part Modal -->
                        <div id="edit-part-modal" class="pg-modal">
                            <div class="pg-modal-content">
                                <span class="pg-modal-close">&times;</span>
                                <h3>Edit Part</h3>
                                <form id="edit-part-form">
                                    <input type="hidden" name="part_id" id="edit_part_id">
                                    
                                    <div class="pg-form-row">
                                        <label for="edit_part_name">Part Name:</label>
                                        <input type="text" id="edit_part_name" name="part_name" required>
                                    </div>
                                    
                                    <div class="pg-form-row">
                                        <label for="edit_part_goal">Goal:</label>
                                        <input type="number" id="edit_part_goal" name="part_goal" min="0" required>
                                    </div>
                                    
                                    <div class="pg-form-row">
                                        <label for="edit_estimated_length">Estimated Length (m):</label>
                                        <input type="number" id="edit_estimated_length" name="estimated_length" step="0.01" min="0" required>
                                    </div>
                                    
                                    <div class="pg-form-row">
                                        <label for="edit_estimated_weight">Estimated Weight (g):</label>
                                        <input type="number" id="edit_estimated_weight" name="estimated_weight" step="0.01" min="0" required>
                                    </div>
                                    
                                    <div class="pg-form-row">
                                        <button type="submit" class="button button-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <div class="pg-project-actions">
                            <form id="start-project-form">
                                <input type="hidden" name="project_id" value="<?php echo esc_attr($selected_project_id); ?>">
                                <button type="submit" class="button <?php echo $active_project ? 'button-disabled' : 'button-primary'; ?>" <?php echo $active_project ? 'disabled' : ''; ?>>
                                    Start Project
                                </button>
                            </form>
                            
                            <form id="complete-project-form">
                                <input type="hidden" name="project_id" value="<?php echo esc_attr($selected_project_id); ?>">
                                <button type="submit" class="button <?php echo !$active_project ? 'button-disabled' : 'button-primary'; ?>" <?php echo !$active_project ? 'disabled' : ''; ?>>
                                    Mark Project as Complete
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php else: ?>
                        <div class="pg-admin-card">
                            <p>Please select a project first.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="pg-admin-tab-content" id="completed-goals">
                    <?php if ($selected_project_id > 0): ?>
                    <div class="pg-admin-card">
                        <h2>Completed Goals for <?php echo esc_html($wpdb->get_var($wpdb->prepare("SELECT name FROM $projects_table WHERE id = %d", $selected_project_id))); ?></h2>
                        
                        <?php if (empty($completed_goals)): ?>
                            <p>No completed goals for this project.</p>
                        <?php else: ?>
                            <div class="pg-completed-goals-list">
                                <?php foreach ($completed_goals as $completed): ?>
                                    <div class="pg-completed-goal">
                                        <div class="pg-completed-header">
                                            <h3>Completed: <?php echo esc_html(date('F j, Y', strtotime($completed->completed_date))); ?></h3>
                                            <button type="button" class="button button-small button-link toggle-completed-details" data-id="<?php echo esc_attr($completed->id); ?>">Show Details</button>
                                            <button type="button" class="button button-small button-link-delete delete-completed-btn" data-id="<?php echo esc_attr($completed->id); ?>">Delete</button>
                                        </div>
                                        
                                        <div id="completed-details-<?php echo esc_attr($completed->id); ?>" class="pg-completed-details" style="display: none;">
                                            <?php
                                            $project_contributions = json_decode($completed->user_contributions, true);
                                            if (!empty($project_contributions)): ?>
                                                <table class="widefat">
                                                    <thead>
                                                        <tr>
                                                            <th>Part</th>
                                                            <th>Goal</th>
                                                            <th>Actual</th>
                                                            <th>User Contributions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($project_contributions as $part): ?>
                                                            <tr>
                                                                <td><?php echo esc_html($part['part_name']); ?></td>
                                                                <td><?php echo esc_html($part['goal']); ?></td>
                                                                <td><?php echo esc_html($part['progress']); ?></td>
                                                                <td>
                                                                    <?php if (!empty($part['contributions'])): ?>
                                                                        <ul>
                                                                            <?php foreach ($part['contributions'] as $contribution): ?>
                                                                                <li><?php echo esc_html($contribution['user']); ?>: <?php echo esc_html($contribution['total']); ?></li>
                                                                            <?php endforeach; ?>
                                                                        </ul>
                                                                    <?php else: ?>
                                                                        <p>No contributions recorded.</p>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            <?php else: ?>
                                                <p>No contribution data available.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                        <div class="pg-admin-card">
                            <p>Please select a project first.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="pg-admin-tab-content" id="shortcodes">
                    <div class="pg-admin-card">
                        <h2>Available Shortcodes</h2>
                        
                        <div class="pg-shortcodes-list">
                            <div class="pg-shortcode-item">
                                <h3>[production_goal id="X"]</h3>
                                <p>Displays a project's progress and allows user submissions.</p>
                                <?php if ($selected_project_id > 0): ?>
                                    <div class="pg-shortcode-example">
                                        <p>Example for selected project:</p>
                                        <code>[production_goal id="<?php echo esc_attr($selected_project_id); ?>"]</code>
                                        <button type="button" class="button button-small copy-to-clipboard" data-clipboard="[production_goal id=&quot;<?php echo esc_attr($selected_project_id); ?>&quot;]">Copy</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="pg-shortcode-item">
                                <h3>[my_projects]</h3>
                                <p>Displays a user interface for managing their contributions, viewing active and completed goals, and overall statistics.</p>
                                <div class="pg-shortcode-example">
                                    <code>[my_projects]</code>
                                    <button type="button" class="button button-small copy-to-clipboard" data-clipboard="[my_projects]">Copy</button>
                                </div>
                            </div>
                            
                            <div class="pg-shortcode-item">
                                <h3>[all_projects]</h3>
                                <p>Displays all projects with search and pagination, with links to project pages.</p>
                                <div class="pg-shortcode-example">
                                    <code>[all_projects]</code>
                                    <button type="button" class="button button-small copy-to-clipboard" data-clipboard="[all_projects]">Copy</button>
                                </div>
                            </div>
                            
                            <div class="pg-shortcode-item">
                                <h3>[all_projects_nolink]</h3>
                                <p>Displays all projects without links to project pages.</p>
                                <div class="pg-shortcode-example">
                                    <code>[all_projects_nolink]</code>
                                    <button type="button" class="button button-small copy-to-clipboard" data-clipboard="[all_projects_nolink]">Copy</button>
                                </div>
                            </div>
                            
                            <div class="pg-shortcode-item">
                                <h3>[most_unfulfilled_goals]</h3>
                                <p>Displays a carousel of projects with unfulfilled goals.</p>
                                <div class="pg-shortcode-example">
                                    <code>[most_unfulfilled_goals]</code>
                                    <button type="button" class="button button-small copy-to-clipboard" data-clipboard="[most_unfulfilled_goals]">Copy</button>
                                </div>
                            </div>
                            
                            <div class="pg-shortcode-item">
                                <h3>[static_unfulfilled_projects]</h3>
                                <p>Displays a static grid of projects with unfulfilled goals.</p>
                                <div class="pg-shortcode-example">
                                    <code>[static_unfulfilled_projects]</code>
                                    <button type="button" class="button button-small copy-to-clipboard" data-clipboard="[static_unfulfilled_projects]">Copy</button>
                                </div>
                            </div>
                            
                            <div class="pg-shortcode-item">
                                <h3>[production_goal_ticker]</h3>
                                <p>Displays a scrolling ticker of active projects.</p>
                                <div class="pg-shortcode-example">
                                    <code>[production_goal_ticker]</code>
                                    <button type="button" class="button button-small copy-to-clipboard" data-clipboard="[production_goal_ticker]">Copy</button>
                                </div>
                            </div>
                            
                            <div class="pg-shortcode-item">
                                <h3>[group_totals]</h3>
                                <p>Displays group-wide statistics.</p>
                                <div class="pg-shortcode-example">
                                    <code>[group_totals]</code>
                                    <button type="button" class="button button-small copy-to-clipboard" data-clipboard="[group_totals]">Copy</button>
                                </div>
                            </div>
                            
                            <div class="pg-shortcode-item">
                                <h3>[user_lifetime_contributions]</h3>
                                <p>Displays user's lifetime contribution statistics.</p>
                                <div class="pg-shortcode-example">
                                    <code>[user_lifetime_contributions]</code>
                                    <button type="button" class="button button-small copy-to-clipboard" data-clipboard="[user_lifetime_contributions]">Copy</button>
                                </div>
                            </div>
                            
                            <div class="pg-shortcode-item">
                                <h3>[historical_submissions]</h3>
                                <p>Displays historical submissions for a user.</p>
                                <div class="pg-shortcode-example">
                                    <code>[historical_submissions]</code>
                                    <button type="button" class="button button-small copy-to-clipboard" data-clipboard="[historical_submissions]">Copy</button>
                                </div>
                            </div>
                            
                            <div class="pg-shortcode-item">
                                <h3>[corrections]</h3>
                                <p>Allows users to edit or delete their submissions.</p>
                                <div class="pg-shortcode-example">
                                    <code>[corrections]</code>
                                    <button type="button" class="button button-small copy-to-clipboard" data-clipboard="[corrections]">Copy</button>
                                </div>
                            </div>
                            
                            <?php if ($project_file): ?>
                                <div class="pg-shortcode-item">
                                    <h3>[download_counter id="X"]</h3>
                                    <p>Displays download count for a project file.</p>
                                    <div class="pg-shortcode-example">
                                        <p>Example for selected project:</p>
                                        <code><?php echo esc_html($this->production_file_handler->get_download_shortcode($project_file)); ?></code>
                                        <button type="button" class="button button-small copy-to-clipboard" data-clipboard="<?php echo esc_attr($this->production_file_handler->get_download_shortcode($project_file)); ?>">Copy</button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab navigation
            $('.pg-admin-tab-button').on('click', function() {
                const tabId = $(this).data('tab');
                
                // Update active tab button
                $('.pg-admin-tab-button').removeClass('active');
                $(this).addClass('active');
                
                // Show selected tab content
                $('.pg-admin-tab-content').removeClass('active');
                $('#' + tabId).addClass('active');
            });
            
            // Copy to clipboard functionality
            $('.copy-to-clipboard').on('click', function() {
                const textToCopy = $(this).data('clipboard');
                const tempTextarea = document.createElement('textarea');
                tempTextarea.value = textToCopy;
                document.body.appendChild(tempTextarea);
                tempTextarea.select();
                document.execCommand('copy');
                document.body.removeChild(tempTextarea);
                
                const originalText = $(this).text();
                $(this).text('Copied!');
                
                setTimeout(() => {
                    $(this).text(originalText);
                }, 2000);
            });
            
            // Toggle completed goal details
            $('.toggle-completed-details').on('click', function() {
                const id = $(this).data('id');
                const detailsContainer = $('#completed-details-' + id);
                
                if (detailsContainer.is(':visible')) {
                    detailsContainer.slideUp();
                    $(this).text('Show Details');
                } else {
                    detailsContainer.slideDown();
                    $(this).text('Hide Details');
                }
            });
            
            // Toggle user contributions
            $('.view-contributions-btn').on('click', function() {
                const partId = $(this).data('part-id');
                $('#contributions-' + partId).toggle();
            });
            
            // Edit part modal
            $('.edit-part-btn').on('click', function() {
                const partId = $(this).data('part-id');
                const partName = $(this).data('part-name');
                const partGoal = $(this).data('part-goal');
                const partLength = $(this).data('part-length');
                const partWeight = $(this).data('part-weight');
                
                $('#edit_part_id').val(partId);
                $('#edit_part_name').val(partName);
                $('#edit_part_goal').val(partGoal);
                $('#edit_estimated_length').val(partLength);
                $('#edit_estimated_weight').val(partWeight);
                
                $('#edit-part-modal').show();
            });
            
            // Close modal
            $('.pg-modal-close').on('click', function() {
                $(this).closest('.pg-modal').hide();
            });
            
            // Close modal when clicking outside
            $(window).on('click', function(event) {
                if ($(event.target).hasClass('pg-modal')) {
                    $('.pg-modal').hide();
                }
            });
            
            // AJAX form submissions
            $('#add-project-form').on('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('action', 'pg_add_project');
                formData.append('nonce', pg_admin_data.nonce);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function() {
                        $('#add-project-form button[type="submit"]').prop('disabled', true).text('Adding Project...');
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Project added successfully!');
                            location.href = 'admin.php?page=production-goals&project_id=' + response.data.project_id;
                        } else {
                            alert(response.data.message || 'Error adding project.');
                        }
                    },
                    error: function() {
                        alert('An error occurred while adding the project.');
                    },
                    complete: function() {
                        $('#add-project-form button[type="submit"]').prop('disabled', false).text('Add Project');
                    }
                });
            });
            
            $('#edit-project-form').on('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('action', 'pg_edit_project');
                formData.append('nonce', pg_admin_data.nonce);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function() {
                        $('#edit-project-form button[type="submit"]').prop('disabled', true).text('Saving Changes...');
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Project updated successfully!');
                            location.reload();
                        } else {
                            alert(response.data.message || 'Error updating project.');
                        }
                    },
                    error: function() {
                        alert('An error occurred while updating the project.');
                    },
                    complete: function() {
                        $('#edit-project-form button[type="submit"]').prop('disabled', false).text('Save Changes');
                    }
                });
            });
            
            $('#delete-project-btn').on('click', function() {
                if (!confirm('Are you sure you want to delete this project? This action cannot be undone.')) {
                    return;
                }
                
                const projectId = $('#edit-project-form input[name="project_id"]').val();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'pg_delete_project',
                        nonce: pg_admin_data.nonce,
                        project_id: projectId
                    },
                    beforeSend: function() {
                        $('#delete-project-btn').prop('disabled', true).text('Deleting...');
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Project deleted successfully!');
                            location.href = 'admin.php?page=production-goals';
                        } else {
                            alert(response.data.message || 'Error deleting project.');
                        }
                    },
                    error: function() {
                        alert('An error occurred while deleting the project.');
                    },
                    complete: function() {
                        $('#delete-project-btn').prop('disabled', false).text('Delete Project');
                    }
                });
            });
            
            $('#add-part-form').on('submit', function(e) {
                e.preventDefault();
                
                const formData = $(this).serialize();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData + '&action=pg_add_part&nonce=' + pg_admin_data.nonce,
                    beforeSend: function() {
                        $('#add-part-form button[type="submit"]').prop('disabled', true).text('Adding Part...');
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Part added successfully!');
                            location.reload();
                        } else {
                            alert(response.data.message || 'Error adding part.');
                        }
                    },
                    error: function() {
                        alert('An error occurred while adding the part.');
                    },
                    complete: function() {
                        $('#add-part-form button[type="submit"]').prop('disabled', false).text('Add Part');
                    }
                });
            });
            
            $('#edit-part-form').on('submit', function(e) {
                e.preventDefault();
                
                const formData = $(this).serialize();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData + '&action=pg_edit_part&nonce=' + pg_admin_data.nonce,
                    beforeSend: function() {
                        $('#edit-part-form button[type="submit"]').prop('disabled', true).text('Saving Changes...');
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Part updated successfully!');
                            location.reload();
                        } else {
                            alert(response.data.message || 'Error updating part.');
                        }
                    },
                    error: function() {
                        alert('An error occurred while updating the part.');
                    },
                    complete: function() {
                        $('#edit-part-form button[type="submit"]').prop('disabled', false).text('Save Changes');
                        $('#edit-part-modal').hide();
                    }
                });
            });
            
            $('.delete-part-btn').on('click', function() {
                if (!confirm('Are you sure you want to delete this part? This action cannot be undone.')) {
                    return;
                }
                
                const partId = $(this).data('part-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'pg_delete_part',
                        nonce: pg_admin_data.nonce,
                        part_id: partId
                    },
                    beforeSend: function() {
                        $(this).prop('disabled', true).text('Deleting...');
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Part deleted successfully!');
                            location.reload();
                        } else {
                            alert(response.data.message || 'Error deleting part.');
                        }
                    },
                    error: function() {
                        alert('An error occurred while deleting the part.');
                    },
                    complete: function() {
                        $(this).prop('disabled', false).text('Delete');
                    }
                });
            });
            
            $('#start-project-form').on('submit', function(e) {
                e.preventDefault();
                
                if (!confirm('Are you sure you want to start this project? This will set the start date for all parts with goals.')) {
                    return;
                }
                
                const formData = $(this).serialize();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData + '&action=pg_start_project&nonce=' + pg_admin_data.nonce,
                    beforeSend: function() {
                        $('#start-project-form button[type="submit"]').prop('disabled', true).text('Starting Project...');
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Project started successfully!');
                            location.reload();
                        } else {
                            alert(response.data.message || 'Error starting project.');
                        }
                    },
                    error: function() {
                        alert('An error occurred while starting the project.');
                    },
                    complete: function() {
                        $('#start-project-form button[type="submit"]').prop('disabled', false).text('Start Project');
                    }
                });
            });
            
            $('#complete-project-form').on('submit', function(e) {
                e.preventDefault();
                
                if (!confirm('Are you sure you want to mark this project as complete? This will reset all progress for current goals.')) {
                    return;
                }
                
                const formData = $(this).serialize();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData + '&action=pg_complete_project&nonce=' + pg_admin_data.nonce,
                    beforeSend: function() {
                        $('#complete-project-form button[type="submit"]').prop('disabled', true).text('Completing Project...');
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Project marked as complete successfully!');
                            location.reload();
                        } else {
                            alert(response.data.message || 'Error completing project.');
                        }
                    },
                    error: function() {
                        alert('An error occurred while completing the project.');
                    },
                    complete: function() {
                        $('#complete-project-form button[type="submit"]').prop('disabled', false).text('Mark Project as Complete');
                    }
                });
            });
            
            $('.delete-completed-btn').on('click', function() {
                if (!confirm('Are you sure you want to delete this completed goal record? This action cannot be undone.')) {
                    return;
                }
                
                const completedId = $(this).data('id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'pg_delete_completed',
                        nonce: pg_admin_data.nonce,
                        completed_id: completedId
                    },
                    beforeSend: function() {
                        $(this).prop('disabled', true).text('Deleting...');
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Completed goal record deleted successfully!');
                            location.reload();
                        } else {
                            alert(response.data.message || 'Error deleting completed goal record.');
                        }
                    },
                    error: function() {
                        alert('An error occurred while deleting the completed goal record.');
                    },
                    complete: function() {
                        $(this).prop('disabled', false).text('Delete');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Production Goals Settings</h1>
            <p>Settings will be added in a future update.</p>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for adding a project
     */
    public function ajax_add_project() {
        check_ajax_referer('production-goals-admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        }
        
        global $wpdb;
        $projects_table = $wpdb->prefix . "production_projects";
        
        $project_name = sanitize_text_field($_POST['project_name']);
        $materials = array_map('trim', explode(',', sanitize_text_field($_POST['project_material'])));
        $project_url = esc_url_raw($_POST['project_url']);
        
        // Insert project
        $wpdb->insert($projects_table, array(
            'name' => $project_name,
            'url' => $project_url,
            'created_at' => current_time('mysql')
        ));
        
        if ($wpdb->last_error) {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
        }
        
        $project_id = $wpdb->insert_id;
        
        // Insert materials
        foreach ($materials as $material) {
            if (!empty($material)) {
                $wpdb->insert($wpdb->prefix . 'project_materials', array(
                    'project_id' => $project_id,
                    'material' => $material
                ));
            }
        }
        
        // Handle file upload
        do_action('production_project_saved', $project_id, false);
        
        wp_send_json_success(array(
            'message' => 'Project added successfully!',
            'project_id' => $project_id
        ));
    }
    
    /**
     * AJAX handler for editing a project
     */
    public function ajax_edit_project() {
        check_ajax_referer('production-goals-admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        }
        
        global $wpdb;
        $projects_table = $wpdb->prefix . "production_projects";
        
        $project_id = intval($_POST['project_id']);
        $project_name = sanitize_text_field($_POST['project_name']);
        $materials = array_map('trim', explode(',', sanitize_text_field($_POST['project_material'])));
        $project_url = esc_url_raw($_POST['project_url']);
        
        // Update project
        $wpdb->update($projects_table, array(
            'name' => $project_name,
            'url' => $project_url
        ), array('id' => $project_id));
        
        if ($wpdb->last_error) {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
        }
        
        // Clear old materials
        $wpdb->delete($wpdb->prefix . 'project_materials', array('project_id' => $project_id));
        
        // Insert updated materials
        foreach ($materials as $material) {
            if (!empty($material)) {
                $wpdb->insert($wpdb->prefix . 'project_materials', array(
                    'project_id' => $project_id,
                    'material' => $material
                ));
            }
        }
        
        // Handle file upload
        do_action('production_project_saved', $project_id, true);
        
        wp_send_json_success(array(
            'message' => 'Project updated successfully!'
        ));
    }
    
    /**
     * AJAX handler for deleting a project
     */
    public function ajax_delete_project() {
        check_ajax_referer('production-goals-admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        }
        
        global $wpdb;
        $projects_table = $wpdb->prefix . "production_projects";
        $parts_table = $wpdb->prefix . "production_parts";
        $completed_table = $wpdb->prefix . "production_completed";
        
        $project_id = intval($_POST['project_id']);
        
        // Delete file associated with the project
        $this->production_file_handler->delete_project_file($project_id);
        
        // Delete parts linked to the project
        $wpdb->delete($parts_table, array('project_id' => $project_id));
        
        // Delete materials linked to the project
        $wpdb->delete($wpdb->prefix . 'project_materials', array('project_id' => $project_id));
        
        // Delete the project
        $wpdb->delete($projects_table, array('id' => $project_id));
        
        // Delete completed goals for the project
        $wpdb->delete($completed_table, array('project_id' => $project_id));
        
        wp_send_json_success(array(
            'message' => 'Project deleted successfully!'
        ));
    }
    
    /**
     * AJAX handler for adding a part
     */
    public function ajax_add_part() {
        check_ajax_referer('production-goals-admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        }
        
        global $wpdb;
        $parts_table = $wpdb->prefix . "production_parts";
        
        $project_id = intval($_POST['project_id']);
        $part_name = sanitize_text_field($_POST['part_name']);
        $part_goal = intval($_POST['part_goal']);
        $estimated_length = floatval($_POST['estimated_length']);
        $estimated_weight = floatval($_POST['estimated_weight']);
        
        // Insert part
        $wpdb->insert($parts_table, array(
            'project_id' => $project_id,
            'name' => $part_name,
            'goal' => $part_goal,
            'progress' => 0,
            'lifetime_total' => 0,
            'estimated_length' => $estimated_length,
            'estimated_weight' => $estimated_weight
        ));
        
        if ($wpdb->last_error) {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
        }
        
        wp_send_json_success(array(
            'message' => 'Part added successfully!'
        ));
    }
    
    /**
     * AJAX handler for editing a part
     */
    public function ajax_edit_part() {
        check_ajax_referer('production-goals-admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        }
        
        global $wpdb;
        $parts_table = $wpdb->prefix . "production_parts";
        
        $part_id = intval($_POST['part_id']);
        $part_name = sanitize_text_field($_POST['part_name']);
        $part_goal = intval($_POST['part_goal']);
        $estimated_length = floatval($_POST['estimated_length']);
        $estimated_weight = floatval($_POST['estimated_weight']);
        
        // Update part
        $wpdb->update($parts_table, array(
            'name' => $part_name,
            'goal' => $part_goal,
            'estimated_length' => $estimated_length,
            'estimated_weight' => $estimated_weight
        ), array('id' => $part_id));
        
        if ($wpdb->last_error) {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
        }
        
        wp_send_json_success(array(
            'message' => 'Part updated successfully!'
        ));
    }
    
    /**
     * AJAX handler for deleting a part
     */
    public function ajax_delete_part() {
        check_ajax_referer('production-goals-admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        }
        
        global $wpdb;
        $parts_table = $wpdb->prefix . "production_parts";
        
        $part_id = intval($_POST['part_id']);
        
        // Delete the part
        $wpdb->delete($parts_table, array('id' => $part_id));
        
        if ($wpdb->last_error) {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
        }
        
        wp_send_json_success(array(
            'message' => 'Part deleted successfully!'
        ));
    }
    
    /**
     * AJAX handler for starting a project
     */
    public function ajax_start_project() {
        check_ajax_referer('production-goals-admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        }
        
        global $wpdb;
        $parts_table = $wpdb->prefix . "production_parts";
        
        $project_id = intval($_POST['project_id']);
        $start_date = current_time('mysql');
        
        // Update parts with goals to set start date
        $wpdb->query($wpdb->prepare(
            "UPDATE $parts_table SET start_date = %s WHERE project_id = %d AND goal > 0",
            $start_date, $project_id
        ));
        
        if ($wpdb->last_error) {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
        }
        
        wp_send_json_success(array(
            'message' => 'Project started successfully!'
        ));
    }
    
    /**
     * AJAX handler for completing a project
     */
    public function ajax_complete_project() {
        check_ajax_referer('production-goals-admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        }
        
        global $wpdb;
        $parts_table = $wpdb->prefix . "production_parts";
        $submissions_table = $wpdb->prefix . "production_submissions";
        $projects_table = $wpdb->prefix . "production_projects";
        $completed_table = $wpdb->prefix . "production_completed";
        
        $project_id = intval($_POST['project_id']);
        
        // Fetch the project name
        $project_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM $projects_table WHERE id = %d",
            $project_id
        ));
        
        // Fetch parts for the project
        $parts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $parts_table WHERE project_id = %d",
            $project_id
        ));
        
        // Prepare the user contributions data
        $user_contributions = array();
        foreach ($parts as $part) {
            $contributions = $wpdb->get_results($wpdb->prepare(
                "SELECT user_id, SUM(quantity) as total 
                 FROM $submissions_table 
                 WHERE part_id = %d AND created_at >= %s 
                 GROUP BY user_id",
                $part->id, $part->start_date ?? '1970-01-01 00:00:00'
            ));
            
            $part_contributions = array();
            foreach ($contributions as $contribution) {
                $user_info = get_userdata($contribution->user_id);
                if ($user_info) {
                    $part_contributions[] = array(
                        'user' => $user_info->user_login,
                        'total' => $contribution->total
                    );
                }
            }
            
            $user_contributions[] = array(
                'part_name' => $part->name,
                'goal' => $part->goal,
                'progress' => $part->progress,
                'contributions' => $part_contributions
            );
        }
        
        // Insert the completed project into the completed table
        $wpdb->insert($completed_table, array(
            'project_id' => $project_id,
            'project_name' => $project_name,
            'completed_date' => current_time('mysql'),
            'user_contributions' => json_encode($user_contributions)
        ));
        
        if ($wpdb->last_error) {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
        }
        
        // Reset the progress and start_date of all parts for the project
        $wpdb->query($wpdb->prepare(
            "UPDATE $parts_table SET progress = 0, start_date = NULL WHERE project_id = %d",
            $project_id
        ));
        
        wp_send_json_success(array(
            'message' => 'Project marked as complete successfully!'
        ));
    }
    
    /**
     * AJAX handler for deleting a completed goal
     */
    public function ajax_delete_completed() {
        check_ajax_referer('production-goals-admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        }
        
        global $wpdb;
        $completed_table = $wpdb->prefix . "production_completed";
        
        $completed_id = intval($_POST['completed_id']);
        
        // Delete the completed goal
        $wpdb->delete($completed_table, array('id' => $completed_id));
        
        if ($wpdb->last_error) {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
        }
        
        wp_send_json_success(array(
            'message' => 'Completed goal deleted successfully!'
        ));
    }
}

// Initialize the admin class
new Production_Goals_Admin();