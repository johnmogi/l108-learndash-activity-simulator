# LearnDash Activity Simulator

A WordPress plugin that helps you generate realistic LearnDash activity data for testing and development purposes.

## Features

- Generate realistic course progress, lesson completions, and quiz attempts
- Simulate activity for multiple students across multiple courses
- Control completion rates and quiz pass rates
- Export generated activity data for backup or sharing
- Clean up all simulated data with a single click
- User-friendly admin interface

## Installation

1. Upload the `learndash-activity-simulator` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'LD Activity Simulator' in the WordPress admin menu

## Usage

### Generating Activity

1. Select one or more students
2. Select one or more courses
3. Configure activity settings:
   - Activity Period: The time period over which to distribute the activity
   - Completion Rate: Percentage of courses/lessons that should be marked as completed
   - Quiz Pass Rate: Minimum score required to pass quizzes
4. Click 'Generate Activity'

### Exporting Activity

1. Click 'Export Activity Data' to download a JSON file containing all generated activity data
2. The export file can be used to restore activity data later or share with others

### Cleaning Up

1. Click 'Clean Up All Activity' to remove all simulated activity data
2. This will delete all generated activity records and reset user progress

## Requirements

- WordPress 5.0 or higher
- LearnDash LMS 3.0 or higher
- PHP 7.4 or higher

## Screenshots

1. Main plugin screen
2. Activity generation in progress
3. Success message after generating activity

## Changelog

### 1.0.0
* Initial release

## License

GPL v2 or later
# LearnDash-Activity-Simulator
# l108-learndash-activity-simulator
