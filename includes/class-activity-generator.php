<?php
/**
 * Activity Generator Class
 *
 * Handles the generation, export, and cleanup of LearnDash activity data.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class LAS_Activity_Generator {
    /**
     * Generate activity data for the specified parameters
     *
     * @param array $data Activity generation parameters
     * @return array|WP_Error Results or WP_Error on failure
     */
    public function generate($data) {
        global $wpdb;
        
        // Default parameters
        $params = wp_parse_args($data, array(
            'students' => array(),
            'courses' => array(),
            'activity_days' => 30,
            'completion_rate' => 80,
            'quiz_pass_rate' => 75
        ));

        // Validate inputs
        if (empty($params['students'])) {
            return new WP_Error('no_students', __('No students selected', 'learndash-activity-simulator'));
        }

        if (empty($params['courses'])) {
            return new WP_Error('no_courses', __('No courses available', 'learndash-activity-simulator'));
        }

        // Store simulation data for export/cleanup
        $simulation_data = array(
            'students' => $params['students'],
            'courses' => $params['courses'],
            'timestamp' => current_time('mysql'),
            'activity' => array()
        );

        // Begin transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Generate activity for each student
            foreach ($params['students'] as $student_id) {
                $student_activity = $this->generate_student_activity($student_id, $params);
                $simulation_data['activity'][$student_id] = $student_activity;
            }

            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Save simulation data for later reference
            update_option('las_simulation_data', $simulation_data, false);
            
            return array(
                'message' => sprintf(
                    _n(
                        'Generated activity for %d student.',
                        'Generated activity for %d students.',
                        count($params['students']),
                        'learndash-activity-simulator'
                    ),
                    count($params['students'])
                ),
                'data' => $simulation_data
            );
            
        } catch (Exception $e) {
            // Rollback on error
            $wpdb->query('ROLLBACK');
            return new WP_Error('generation_failed', __('Failed to generate activity: ', 'learndash-activity-simulator') . $e->getMessage());
        }
    }

    /**
     * Generate activity for a single student
     */
    private function generate_student_activity($student_id, $params) {
        $activity = array(
            'courses' => array(),
            'activity_ids' => array()
        );

        $now = current_time('timestamp');
        $start_date = strtotime("-" . intval($params['activity_days']) . " days", $now);

        // Process each course
        foreach ($params['courses'] as $course_id) {
            $course_activity = $this->generate_course_activity($student_id, $course_id, $params, $start_date, $now);
            $activity['courses'][$course_id] = $course_activity;
            $activity['activity_ids'] = array_merge($activity['activity_ids'], $course_activity['activity_ids']);
            
            // Process lessons and quizzes for this course
            $this->generate_course_content_activity($student_id, $course_id, $params, $start_date, $now, $activity);
        }

        return $activity;
    }

    /**
     * Generate course activity
     */
    private function generate_course_activity($student_id, $course_id, $params, $start_date, $end_date) {
        global $wpdb;
        
        $activity = array(
            'activity_id' => 0,
            'started' => 0,
            'completed' => 0,
            'status' => 0,
            'activity_ids' => array()
        );

        // Randomize start date within the activity period
        $started = $this->random_date($start_date, $end_date - 86400); // At least 1 day before end
        
        // Determine if course is completed based on completion rate
        $is_completed = (mt_rand(1, 100) <= $params['completion_rate']);
        
        // If completed, set completion date (between start and end date)
        $completed = $is_completed ? $this->random_date($started, $end_date) : 0;

        // Insert course activity
        $result = $wpdb->insert(
            $wpdb->prefix . 'learndash_user_activity',
            array(
                'user_id' => $student_id,
                'post_id' => $course_id,
                'activity_type' => 'course',
                'activity_status' => $is_completed ? 1 : 0,
                'activity_started' => $started,
                'activity_completed' => $completed,
                'activity_updated' => $completed ?: $started
            ),
            array('%d', '%d', '%s', '%d', '%d', '%d', '%d')
        );

        if ($result) {
            $activity_id = $wpdb->insert_id;
            $activity['activity_id'] = $activity_id;
            $activity['started'] = $started;
            $activity['completed'] = $completed;
            $activity['status'] = $is_completed ? 1 : 0;
            $activity['activity_ids'][] = $activity_id;
            
            // Add course progress meta
            $this->add_activity_meta($activity_id, 'steps_total', 10);
            $this->add_activity_meta($activity_id, 'steps_completed', $is_completed ? 10 : mt_rand(1, 9));
            
            // Update user's course progress
            $this->update_course_progress($student_id, $course_id, $is_completed);
        }

        return $activity;
    }

    /**
     * Generate activity for course content (lessons, topics, quizzes)
     */
    private function generate_course_content_activity($student_id, $course_id, $params, $start_date, $end_date, &$activity) {
        // Get all lessons in the course
        $lessons = learndash_get_course_lessons_list($course_id, $student_id);
        
        if (empty($lessons)) {
            return;
        }

        foreach ($lessons as $lesson) {
            $lesson_id = $lesson['post']->ID;
            
            // Generate lesson activity
            $lesson_activity = $this->generate_lesson_activity($student_id, $course_id, $lesson_id, $params, $start_date, $end_date);
            $activity['lessons'][$lesson_id] = $lesson_activity;
            $activity['activity_ids'] = array_merge($activity['activity_ids'], $lesson_activity['activity_ids']);
            
            // Get quizzes for this lesson
            $quizzes = $this->get_lesson_quizzes($lesson_id);
            
            foreach ($quizzes as $quiz_id) {
                $quiz_activity = $this->generate_quiz_activity($student_id, $course_id, $lesson_id, $quiz_id, $params, $start_date, $end_date);
                $activity['quizzes'][$quiz_id] = $quiz_activity;
                $activity['activity_ids'] = array_merge($activity['activity_ids'], $quiz_activity['activity_ids']);
            }
            
            // Get topics for this lesson
            $topics = learndash_topic_dots($lesson_id, false, 'array', null, $course_id);
            
            if (!empty($topics)) {
                foreach ($topics as $topic) {
                    $topic_id = $topic->ID;
                    
                    // Generate topic activity
                    $topic_activity = $this->generate_topic_activity($student_id, $course_id, $lesson_id, $topic_id, $params, $start_date, $end_date);
                    $activity['topics'][$topic_id] = $topic_activity;
                    $activity['activity_ids'] = array_merge($activity['activity_ids'], $topic_activity['activity_ids']);
                    
                    // Get quizzes for this topic
                    $topic_quizzes = $this->get_lesson_quizzes($topic_id);
                    
                    foreach ($topic_quizzes as $quiz_id) {
                        $quiz_activity = $this->generate_quiz_activity($student_id, $course_id, $topic_id, $quiz_id, $params, $start_date, $end_date);
                        $activity['quizzes'][$quiz_id] = $quiz_activity;
                        $activity['activity_ids'] = array_merge($activity['activity_ids'], $quiz_activity['activity_ids']);
                    }
                }
            }
        }
        
        // Get global quizzes for the course
        $course_quizzes = $this->get_course_quizzes($course_id);
        
        foreach ($course_quizzes as $quiz_id) {
            if (!isset($activity['quizzes'][$quiz_id])) {
                $quiz_activity = $this->generate_quiz_activity($student_id, $course_id, 0, $quiz_id, $params, $start_date, $end_date);
                $activity['quizzes'][$quiz_id] = $quiz_activity;
                $activity['activity_ids'] = array_merge($activity['activity_ids'], $quiz_activity['activity_ids']);
            }
        }
    }

    /**
     * Generate lesson activity
     */
    private function generate_lesson_activity($student_id, $course_id, $lesson_id, $params, $start_date, $end_date) {
        global $wpdb;
        
        $activity = array(
            'activity_id' => 0,
            'started' => 0,
            'completed' => 0,
            'status' => 0,
            'activity_ids' => array()
        );

        // Randomize start date within the activity period
        $started = $this->random_date($start_date, $end_date - 86400);
        
        // Determine if lesson is completed based on completion rate
        $is_completed = (mt_rand(1, 100) <= $params['completion_rate']);
        
        // If completed, set completion date (between start and end date)
        $completed = $is_completed ? $this->random_date($started, $end_date) : 0;

        // Insert lesson activity
        $result = $wpdb->insert(
            $wpdb->prefix . 'learndash_user_activity',
            array(
                'user_id' => $student_id,
                'course_id' => $course_id,
                'post_id' => $lesson_id,
                'activity_type' => 'lesson',
                'activity_status' => $is_completed ? 1 : 0,
                'activity_started' => $started,
                'activity_completed' => $completed,
                'activity_updated' => $completed ?: $started
            ),
            array('%d', '%d', '%d', '%s', '%d', '%d', '%d', '%d')
        );

        if ($result) {
            $activity_id = $wpdb->insert_id;
            $activity['activity_id'] = $activity_id;
            $activity['started'] = $started;
            $activity['completed'] = $completed;
            $activity['status'] = $is_completed ? 1 : 0;
            $activity['activity_ids'][] = $activity_id;
            
            // Add lesson progress meta
            $this->add_activity_meta($activity_id, 'steps_total', 1);
            $this->add_activity_meta($activity_id, 'steps_completed', $is_completed ? 1 : 0);
            
            // Update user's lesson completion
            if ($is_completed) {
                $this->mark_lesson_complete($student_id, $course_id, $lesson_id);
            }
        }

        return $activity;
    }

    /**
     * Generate topic activity
     */
    private function generate_topic_activity($student_id, $course_id, $lesson_id, $topic_id, $params, $start_date, $end_date) {
        // Similar to lesson activity but for topics
        return $this->generate_lesson_activity($student_id, $course_id, $topic_id, $params, $start_date, $end_date);
    }

    /**
     * Generate quiz activity
     */
    private function generate_quiz_activity($student_id, $course_id, $lesson_id, $quiz_id, $params, $start_date, $end_date) {
        global $wpdb;
        
        $activity = array(
            'activity_id' => 0,
            'started' => 0,
            'completed' => 0,
            'score' => 0,
            'percentage' => 0,
            'pass' => 0,
            'activity_ids' => array()
        );

        // Randomize start date within the activity period
        $started = $this->random_date($start_date, $end_date - 3600); // At least 1 hour before end
        
        // Determine quiz score and pass/fail status
        $percentage = mt_rand(0, 100);
        $is_passed = ($percentage >= $params['quiz_pass_rate']);
        
        // Calculate points (assuming 10 questions max)
        $total_questions = mt_rand(5, 10);
        $correct_answers = round(($percentage / 100) * $total_questions);
        $points_per_question = 10; // Assuming 10 points per question
        
        // Set completion time (between 5 and 30 minutes after start)
        $completed = $started + mt_rand(300, 1800);

        // Insert quiz activity
        $result = $wpdb->insert(
            $wpdb->prefix . 'learndash_user_activity',
            array(
                'user_id' => $student_id,
                'course_id' => $course_id,
                'post_id' => $quiz_id,
                'activity_type' => 'quiz',
                'activity_status' => 1, // Always completed if we're generating it
                'activity_started' => $started,
                'activity_completed' => $completed,
                'activity_updated' => $completed
            ),
            array('%d', '%d', '%d', '%s', '%d', '%d', '%d', '%d')
        );

        if ($result) {
            $activity_id = $wpdb->insert_id;
            $activity['activity_id'] = $activity_id;
            $activity['started'] = $started;
            $activity['completed'] = $completed;
            $activity['score'] = $correct_answers;
            $activity['percentage'] = $percentage;
            $activity['pass'] = $is_passed ? 1 : 0;
            $activity['activity_ids'][] = $activity_id;
            
            // Add quiz meta
            $this->add_activity_meta($activity_id, 'percentage', $percentage);
            $this->add_activity_meta($activity_id, 'count', $total_questions);
            $this->add_activity_meta($activity_id, 'score', $correct_answers);
            $this->add_activity_meta($activity_id, 'pass', $is_passed ? 1 : 0);
            $this->add_activity_meta($activity_id, 'points', $correct_answers * $points_per_question);
            $this->add_activity_meta($activity_id, 'total_points', $total_questions * $points_per_question);
            
            // Update quiz statistics in user meta
            $this->update_quiz_statistics($student_id, $course_id, $quiz_id, $percentage, $is_passed, $started, $completed);
        }

        return $activity;
    }

    /**
     * Add activity meta
     */
    private function add_activity_meta($activity_id, $meta_key, $meta_value) {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'learndash_user_activity_meta',
            array(
                'activity_id' => $activity_id,
                'meta_key' => $meta_key,
                'meta_value' => maybe_serialize($meta_value)
            ),
            array('%d', '%s', '%s')
        );
    }

    /**
     * Update course progress in user meta
     */
    private function update_course_progress($user_id, $course_id, $is_completed) {
        $course_progress = get_user_meta($user_id, '_sfwd-course_progress', true);
        
        if (empty($course_progress)) {
            $course_progress = array();
        }
        
        if (!isset($course_progress[$course_id])) {
            $course_progress[$course_id] = array(
                'completed' => 0,
                'total' => 1
            );
        }
        
        if ($is_completed) {
            $course_progress[$course_id]['completed'] = 1;
        }
        
        update_user_meta($user_id, '_sfwd-course_progress', $course_progress);
        
        // Also update the legacy course completion meta
        if ($is_completed) {
            update_user_meta($user_id, 'course_completed_' . $course_id, time());
            
            // Update course certificates if the function exists
            if (function_exists('learndash_get_course_certificate_link')) {
                $certificate_id = get_post_meta($course_id, 'certificate', true);
                if ($certificate_id) {
                    update_user_meta($user_id, 'completed_certificate_' . $course_id, $certificate_id);
                }
            }
        }
    }

    /**
     * Mark a lesson as complete in user meta
     */
    private function mark_lesson_complete($user_id, $course_id, $lesson_id) {
        $lesson_completed = get_user_meta($user_id, '_sfwd-lesson_completed', true);
        
        if (empty($lesson_completed)) {
            $lesson_completed = array();
        }
        
        if (!isset($lesson_completed[$course_id])) {
            $lesson_completed[$course_id] = array();
        }
        
        $lesson_completed[$course_id][$lesson_id] = time();
        
        update_user_meta($user_id, '_sfwd-lesson_completed', $lesson_completed);
        
        // Also update the legacy lesson completion meta
        update_user_meta($user_id, 'completed_' . $course_id . '_' . $lesson_id, time());
    }

    /**
     * Update quiz statistics in user meta
     */
    private function update_quiz_statistics($user_id, $course_id, $quiz_id, $percentage, $is_passed, $started, $completed) {
        global $wpdb;
        
        $quiz_progress = get_user_meta($user_id, '_sfwd-quizzes', true);
        
        if (empty($quiz_progress)) {
            $quiz_progress = array();
        }
        
        $quiz_data = array(
            'quiz' => $quiz_id,
            'score' => $percentage,
            'count' => 10, // Assuming 10 questions
            'pass' => $is_passed ? 1 : 0,
            'rank' => $is_passed ? 'PASS' : 'FAIL',
            'time' => time(),
            'pro_quizid' => get_post_meta($quiz_id, 'quiz_pro_id', true),
            'course' => $course_id,
            'points' => $percentage,
            'total_points' => 100,
            'percentage' => $percentage,
            'timespent' => $completed - $started,
            'has_graded' => false,
            'statistic_ref_id' => 0,
            'started' => $started,
            'completed' => $completed
        );
        
        $quiz_progress[] = $quiz_data;
        update_user_meta($user_id, '_sfwd-quizzes', $quiz_progress);
    }

    /**
     * Get quizzes associated with a lesson
     */
    private function get_lesson_quizzes($lesson_id) {
        $quizzes = get_posts(array(
            'post_type' => 'sfwd-quiz',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'lesson_id',
                    'value' => $lesson_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'ld_quiz_lesson',
                    'value' => $lesson_id,
                    'compare' => '='
                )
            )
        ));
        
        return $quizzes;
    }

    /**
     * Get quizzes directly associated with a course (not through lessons)
     */
    private function get_course_quizzes($course_id) {
        $quizzes = get_posts(array(
            'post_type' => 'sfwd-quiz',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'course_id',
                    'value' => $course_id,
                    'compare' => '='
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => 'lesson_id',
                        'value' => '0',
                        'compare' => '='
                    ),
                    array(
                        'key' => 'lesson_id',
                        'compare' => 'NOT EXISTS'
                    )
                )
            )
        ));
        
        return $quizzes;
    }

    /**
     * Generate a random timestamp between two dates
     */
    private function random_date($start, $end) {
        return mt_rand($start, $end);
    }

    /**
     * Export activity data to a file
     */
    public function export() {
        $simulation_data = get_option('las_simulation_data', array());
        
        if (empty($simulation_data)) {
            return new WP_Error('no_data', __('No simulation data found', 'learndash-activity-simulator'));
        }
        
        // Get upload directory
        $upload_dir = wp_upload_dir();
        $exports_dir = $upload_dir['basedir'] . '/learndash-activity-exports';
        
        // Create exports directory if it doesn't exist
        if (!file_exists($exports_dir)) {
            wp_mkdir_p($exports_dir);
            file_put_contents($exports_dir . '/index.php', '<?php // Silence is golden');
        }
        
        // Generate a filename with timestamp
        $filename = 'learndash-activity-export-' . date('Y-m-d-H-i-s') . '.json';
        $filepath = $exports_dir . '/' . $filename;
        
        // Save the data as JSON
        $result = file_put_contents($filepath, json_encode($simulation_data, JSON_PRETTY_PRINT));
        
        if ($result === false) {
            return new WP_Error('export_failed', __('Failed to export activity data', 'learndash-activity-simulator'));
        }
        
        return array(
            'message' => __('Activity data exported successfully', 'learndash-activity-simulator'),
            'file' => $filename,
            'path' => $filepath,
            'url' => $upload_dir['baseurl'] . '/learndash-activity-exports/' . $filename
        );
    }

    /**
     * Clean up all simulated activity data
     */
    public function cleanup() {
        global $wpdb;
        
        $simulation_data = get_option('las_simulation_data', array());
        
        if (empty($simulation_data)) {
            return new WP_Error('no_data', __('No simulation data found', 'learndash-activity-simulator'));
        }
        
        $deleted_activity = 0;
        $deleted_meta = 0;
        
        // Begin transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Delete activity records and meta for each student
            foreach ($simulation_data['activity'] as $student_id => $student_activity) {
                if (!empty($student_activity['activity_ids'])) {
                    $activity_ids = array_map('intval', $student_activity['activity_ids']);
                    $activity_ids_placeholders = implode(',', array_fill(0, count($activity_ids), '%d'));
                    
                    // Delete activity meta
                    $sql = $wpdb->prepare(
                        "DELETE FROM {$wpdb->prefix}learndash_user_activity_meta WHERE activity_id IN ($activity_ids_placeholders)",
                        $activity_ids
                    );
                    $deleted_meta += $wpdb->query($sql);
                    
                    // Delete activity
                    $sql = $wpdb->prepare(
                        "DELETE FROM {$wpdb->prefix}learndash_user_activity WHERE activity_id IN ($activity_ids_placeholders)",
                        $activity_ids
                    );
                    $deleted_activity += $wpdb->query($sql);
                }
                
                // Clean up user meta
                delete_user_meta($student_id, '_sfwd-course_progress');
                delete_user_meta($student_id, '_sfwd-lesson_completed');
                
                // Clean up quiz progress
                $quiz_progress = get_user_meta($student_id, '_sfwd-quizzes', true);
                if (!empty($quiz_progress)) {
                    foreach ($quiz_progress as $key => $quiz) {
                        if (in_array($quiz['quiz'], $simulation_data['quizzes'] ?? [])) {
                            unset($quiz_progress[$key]);
                        }
                    }
                    update_user_meta($student_id, '_sfwd-quizzes', $quiz_progress);
                }
                
                // Clean up legacy meta
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND (meta_key LIKE 'completed_%%' OR meta_key LIKE 'course_completed_%%' OR meta_key LIKE 'completed_certificate_%%')",
                    $student_id
                ));
            }
            
            // Delete the simulation data
            delete_option('las_simulation_data');
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            return array(
                'message' => __('Activity data cleaned up successfully', 'learndash-activity-simulator'),
                'deleted_activity' => $deleted_activity,
                'deleted_meta' => $deleted_meta
            );
            
        } catch (Exception $e) {
            // Rollback on error
            $wpdb->query('ROLLBACK');
            return new WP_Error('cleanup_failed', __('Failed to clean up activity data: ', 'learndash-activity-simulator') . $e->getMessage());
        }
    }
}
