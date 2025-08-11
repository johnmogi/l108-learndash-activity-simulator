jQuery(document).ready(function($) {
    'use strict';

    // Student selection controls
    $('#select-all-students').on('click', function() {
        $('.las-student-list input[type="checkbox"]').prop('checked', true);
        updateStudentCount();
    });
    
    $('#select-none-students').on('click', function() {
        $('.las-student-list input[type="checkbox"]').prop('checked', false);
        updateStudentCount();
    });
    
    $('#select-random-students').on('click', function() {
        const $checkboxes = $('.las-student-list input[type="checkbox"]');
        $checkboxes.prop('checked', false);
        
        // Select random 20 students
        const totalStudents = $checkboxes.length;
        const numToSelect = Math.min(20, totalStudents);
        const selectedIndices = [];
        
        while (selectedIndices.length < numToSelect) {
            const randomIndex = Math.floor(Math.random() * totalStudents);
            if (selectedIndices.indexOf(randomIndex) === -1) {
                selectedIndices.push(randomIndex);
            }
        }
        
        selectedIndices.forEach(function(index) {
            $checkboxes.eq(index).prop('checked', true);
        });
        
        updateStudentCount();
    });
    
    // Update student count when checkboxes change
    $('.las-student-list input[type="checkbox"]').on('change', updateStudentCount);
    
    function updateStudentCount() {
        const selectedCount = $('.las-student-list input[type="checkbox"]:checked').length;
        const totalCount = $('.las-student-list input[type="checkbox"]').length;
        $('.las-student-count').text(`Selected: ${selectedCount} / ${totalCount} students`);
    }
    
    // Initialize count
    updateStudentCount();

    // Quiz selection controls
    $('#select-all-quizzes').on('click', function() {
        $('.las-quiz-list input[type="checkbox"]').prop('checked', true);
        updateQuizCount();
    });
    
    $('#select-none-quizzes').on('click', function() {
        $('.las-quiz-list input[type="checkbox"]').prop('checked', false);
        updateQuizCount();
    });
    
    $('#select-enforce-hint-quizzes').on('click', function() {
        $('.las-quiz-list input[type="checkbox"]').prop('checked', false);
        $('.las-quiz-list label[data-enforce-hint="1"] input[type="checkbox"]').prop('checked', true);
        updateQuizCount();
    });
    
    $('#select-real-quizzes').on('click', function() {
        $('.las-quiz-list input[type="checkbox"]').prop('checked', false);
        $('.las-quiz-list label[data-enforce-hint="0"] input[type="checkbox"]').prop('checked', true);
        updateQuizCount();
    });
    
    // Update quiz count when checkboxes change
    $('.las-quiz-list input[type="checkbox"]').on('change', updateQuizCount);
    
    function updateQuizCount() {
        const selectedCount = $('.las-quiz-list input[type="checkbox"]:checked').length;
        const totalCount = $('.las-quiz-list input[type="checkbox"]').length;
        const enforceHintCount = $('.las-quiz-list label[data-enforce-hint="1"] input[type="checkbox"]:checked').length;
        const realQuizCount = $('.las-quiz-list label[data-enforce-hint="0"] input[type="checkbox"]:checked').length;
        
        if ($('.las-quiz-count').length === 0) {
            $('.las-quiz-controls').append('<span class="las-quiz-count"></span>');
        }
        $('.las-quiz-count').html(`Selected: ${selectedCount} / ${totalCount} quizzes<br><small>Enforce Hint: ${enforceHintCount}, Real Quizzes: ${realQuizCount}</small>`);
    }
    
    // Initialize quiz count
    updateQuizCount();

    // Handle Generate Activity form submission
    $('#las-generate-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $('#las-generate-button');
        const $spinner = $form.find('.spinner');
        
        // Validate form
        if ($('input[name="students[]"]:checked').length === 0) {
            showNotice('error', lasAdmin.i18n.select_students);
            return;
        }
        
        if ($('input[name="courses[]"]:checked').length === 0) {
            showNotice('error', lasAdmin.i18n.select_courses);
            return;
        }
        
        // Get form data
        const formData = {
            action: 'las_generate_activity',
            nonce: lasAdmin.nonce,
            data: {
                students: $('input[name="students[]"]:checked').map(function() {
                    return $(this).val();
                }).get(),
                courses: $('input[name="courses[]"]:checked').map(function() {
                    return $(this).val();
                }).get(),
                activity_days: $('#las-activity-days').val(),
                completion_rate: $('#las-completion-rate').val(),
                quiz_pass_rate: $('#las-quiz-pass-rate').val()
            }
        };
        
        // Disable button and show spinner
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        
        // Send AJAX request
        $.post(lasAdmin.ajax_url, formData, function(response) {
            if (response.success) {
                showNotice('success', response.data.message);
            } else {
                showNotice('error', response.data || lasAdmin.i18n.error);
            }
        }).fail(function() {
            showNotice('error', lasAdmin.i18n.error);
        }).always(function() {
            $button.prop('disabled', false);
            $spinner.removeClass('is-active');
        });
    });
    
    // Handle Export Activity button click
    $('#las-export-button').on('click', function() {
        const $button = $(this);
        const $spinner = $button.siblings('.spinner');
        const $exportResults = $('#las-export-results');
        const $exportContent = $('#las-export-content');
        
        // Show loading state
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        
        // Send AJAX request
        $.post(
            lasAdmin.ajax_url,
            {
                action: 'las_export_activity',
                nonce: lasAdmin.nonce
            },
            function(response) {
                if (response.success) {
                    // Show export results
                    $exportContent.html(`
                        <p>${response.data.message}</p>
                        <p><a href="${response.data.url}" class="button" download>${lasAdmin.i18n.download_export}</a></p>
                        <p><strong>${lasAdmin.i18n.file}:</strong> ${response.data.file}</p>
                        <p><strong>${lasAdmin.i18n.path}:</strong> ${response.data.path}</p>
                    `);
                    $exportResults.show();
                    showNotice('success', response.data.message);
                } else {
                    showNotice('error', response.data || lasAdmin.i18n.error);
                }
            }
        ).fail(function() {
            showNotice('error', lasAdmin.i18n.error);
        }).always(function() {
            $button.prop('disabled', false);
            $spinner.removeClass('is-active');
        });
    });
    
    // Handle Cleanup Activity button click
    $('#las-cleanup-button').on('click', function() {
        if (!confirm(lasAdmin.i18n.confirm_cleanup)) {
            return;
        }
        
        const $button = $(this);
        const $spinner = $button.siblings('.spinner');
        
        // Show loading state
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        
        // Send AJAX request
        $.post(
            lasAdmin.ajax_url,
            {
                action: 'las_cleanup_activity',
                nonce: lasAdmin.nonce
            },
            function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                } else {
                    showNotice('error', response.data || lasAdmin.i18n.error);
                }
            }
        ).fail(function() {
            showNotice('error', lasAdmin.i18n.error);
        }).always(function() {
            $button.prop('disabled', false);
            $spinner.removeClass('is-active');
        });
    });
    
    // Show/hide notice
    function showNotice(type, message) {
        const $notice = $('.las-notice');
        const $message = $('.las-notice-message');
        
        $notice
            .removeClass('notice-info notice-success notice-error notice-warning')
            .addClass(`notice-${type}`)
            .show();
            
        $message.html(message);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $notice.fadeOut();
        }, 5000);
    }
    
    // Initialize select all/none toggles
    $('.las-select-all').on('click', function(e) {
        e.preventDefault();
        const $checkboxes = $(this).closest('.las-form-section').find('input[type="checkbox"]');
        $checkboxes.prop('checked', true);
    });
    
    $('.las-select-none').on('click', function(e) {
        e.preventDefault();
        const $checkboxes = $(this).closest('.las-form-section').find('input[type="checkbox"]');
        $checkboxes.prop('checked', false);
    });
});
