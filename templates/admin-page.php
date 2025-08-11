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
                    <div class="las-student-controls">
                        <button type="button" id="select-all-students"><?php _e('Select All', 'learndash-activity-simulator'); ?></button>
                        <button type="button" id="select-none-students"><?php _e('Select None', 'learndash-activity-simulator'); ?></button>
                        <button type="button" id="select-random-students"><?php _e('Select Random 20', 'learndash-activity-simulator'); ?></button>
                        <span class="las-student-count"><?php printf(__('Total: %d students', 'learndash-activity-simulator'), count($this->students)); ?></span>
                    </div>
                    <div class="las-checkbox-group las-student-list" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                        <?php foreach ($this->students as $student) : ?>
                            <label style="display: block; margin-bottom: 5px;">
                                <input type="checkbox" name="students[]" value="<?php echo esc_attr($student->ID); ?>">
                                <?php echo esc_html($student->display_name); ?> (<?php echo esc_html($student->user_email); ?>)
                            </label>
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
                <h3><?php _e('Select Quizzes for Testing', 'learndash-activity-simulator'); ?></h3>
                <?php if (!empty($this->quizzes)) : ?>
                    <div class="las-quiz-controls">
                        <button type="button" id="select-all-quizzes"><?php _e('Select All', 'learndash-activity-simulator'); ?></button>
                        <button type="button" id="select-none-quizzes"><?php _e('Select None', 'learndash-activity-simulator'); ?></button>
                        <button type="button" id="select-enforce-hint-quizzes"><?php _e('Select Enforce Hint Only', 'learndash-activity-simulator'); ?></button>
                        <button type="button" id="select-real-quizzes"><?php _e('Select Real Quizzes Only', 'learndash-activity-simulator'); ?></button>
                    </div>
                    <div class="las-checkbox-group las-quiz-list" style="max-height: 250px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                        <?php foreach ($this->quizzes as $quiz_id) : 
                            $enforce_hint = get_post_meta($quiz_id, '_ld_quiz_enforce_hint', true);
                            $is_enforce_hint = ($enforce_hint === '1');
                        ?>
                            <label style="display: block; margin-bottom: 5px;" data-enforce-hint="<?php echo $is_enforce_hint ? '1' : '0'; ?>">
                                <input type="checkbox" name="quizzes[]" value="<?php echo esc_attr($quiz_id); ?>">
                                <?php echo esc_html(get_the_title($quiz_id)); ?>
                                <?php if ($is_enforce_hint) : ?>
                                    <span style="color: #d63638; font-weight: bold;">[ENFORCE HINT]</span>
                                <?php else : ?>
                                    <span style="color: #00a32a; font-weight: bold;">[REAL QUIZ]</span>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="description"><?php _e('Select specific quizzes to generate activity for. Enforce Hint quizzes are marked in red and should be excluded from averages.', 'learndash-activity-simulator'); ?></p>
                <?php else : ?>
                    <p class="las-no-data"><?php _e('No quizzes found.', 'learndash-activity-simulator'); ?></p>
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
