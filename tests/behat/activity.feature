@mod @mod_officehours
Feature: Teachers can create an Office Hours activity
  In order to organize academic appointments
  As a teacher
  I need to add the activity to my course

  Background:
    Given the following "courses" exist:
      | fullname    | shortname | category | format |
      | Test course | TC101     | 0        | topics |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher  | Test      | Teacher  | teacher@example.com  |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | TC101  | editingteacher |

  Scenario: Add an Office Hours activity
    Given I log in as "teacher"
    And I am on "Test course" course homepage with editing mode on
    When I add a "Office Hours" to section "1"
    And I set the following fields to these values:
      | Name | Academic consultations |
    And I press "Save and return to course"
    Then I should see "Academic consultations"
