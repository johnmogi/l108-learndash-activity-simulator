<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap learndash-activity-simulator">
    <h1><?php _e('LearnDash Activity Simulator', 'learndash-activity-simulator'); ?></h1>
    
    <div class="las-notice notice notice-info" style="display: none;">
        <p class="las-notice-message"></p>
    </div>
    
    <div class="las-card">
        <h2><?php _e('Generate Activity', 'learndash-activity-simulator'); ?></h2>
        <p><?php _e('Use this tool to generate realistic LearnDash activity data for testing purposes.', 'learndash-activity-simulator'); ?></p>
        
        <form id="las-generate-form">
            <div class="las-form-section">
                <h3><?php _e('Select Students', 'learndash-activity-simulator'); ?></h3>
                <?php if (!empty($this->students)) : ?>
                    <div class="las-checkbox-group">
                        <?php foreach ($this->students as $student) : ?>
                            <label>
                                <input type="checkbox" name="students[]" value="<?php echo esc_attr($student->ID); ?>">
                                <?php echo esc_html($student->display_name); ?> (<?php echo esc_html($student->user_email); ?>)
                            </label><br>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p class="las-no-data"><?php _e('No students found.', 'learndash-activity-simulator'); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="las-form-section">
                <h3><?php _e('Select Courses', 'learndash-activity-simulator'); ?></h3>
                <?php if (!empty($this->courses)) : ?>
                    <div class="las-checkbox-group">
                        <?php foreach ($this->courses as $course_id) : ?>
                            <label>
                                <input type="checkbox" name="courses[]" value="<?php echo esc_attr($course_id); ?>">
                                <?php echo esc_html(get_the_title($course_id)); ?>
                            </label><br>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p class="las-no-data"><?php _e('No courses found.', 'learndash-activity-simulator'); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="las-form-section">
                <h3><?php _e('Activity Settings', 'learndash-activity-simulator'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="las-activity-days"><?php _e('Activity Period (days)', 'learndash-activity-simulator'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="las-activity-days" name="activity_days" value="30" min="1" max="365">
                            <p class="description"><?php _e('The time period over which to distribute the activity.', 'learndash-activity-simulator'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="las-completion-rate"><?php _e('Completion Rate (%)', 'learndash-activity-simulator'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="las-completion-rate" name="completion_rate" value="80" min="0" max="100">
                            <p class="description"><?php _e('Percentage of courses/lessons that should be marked as completed.', 'learndash-activity-simulator'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="las-quiz-pass-rate"><?php _e('Quiz Pass Rate (%)', 'learndash-activity-simulator'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="las-quiz-pass-rate" name="quiz_pass_rate" value="75" min="0" max="100">
                            <p class="description"><?php _e('Minimum score required to pass quizzes.', 'learndash-activity-simulator'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="las-actions">
                <button type="submit" class="button button-primary" id="las-generate-button">
                    <?php _e('Generate Activity', 'learndash-activity-simulator'); ?>
                </button>
                <span class="spinner"></span>
            </div>
        </form>
    </div>
    
    <div class="las-card">
        <h2><?php _e('Export & Cleanup', 'learndash-activity-simulator'); ?></h2>
        <p><?php _e('Export the generated activity data or clean up all simulated data.', 'learndash-activity-simulator'); ?></p>
        
        <div class="las-actions">
            <button type="button" class="button" id="las-export-button">
                <?php _e('Export Activity Data', 'learndash-activity-simulator'); ?>
            </button>
            
            <button type="button" class="button button-link-delete" id="las-cleanup-button" style="color: #b32d2e; margin-left: 10px;">
                <?php _e('Clean Up All Activity', 'learndash-activity-simulator'); ?>
            </button>
            
            <span class="spinner"></span>
        </div>
    </div>
    
    <div class="las-card" id="las-export-results" style="display: none;">
        <h3><?php _e('Export Results', 'learndash-activity-simulator'); ?></h3>
        <div id="las-export-content"></div>
    </div>
</div>
