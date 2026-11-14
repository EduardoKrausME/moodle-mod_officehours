@mod @mod_officehours
Feature: Teachers can create an Office Hours activity
  In order to provide private academic guidance
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
    When I add an officehours activity to course "Test course" section "1" and I fill the form with:
      | Name | Academic consultations |
    Then I should see "Academic consultations"
    And I follow "Academic consultations"
    And I should see "Manage academic support"
