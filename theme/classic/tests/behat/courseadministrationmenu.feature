@javascript @theme_classic
Feature: Course administration menu
  To navigate in classic theme teachers need to use the course administration menu

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |

  Scenario: Teacher can use the course administration menu
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I should see the page administration menu

  Scenario: Student cannot see the course administration menu
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I should not see the page administration menu
    And I log out

  @javascript
  Scenario: Teacher should see correct question in the question bank.
    Given the following "question categories" exist:
      | contextlevel | reference | name         |
      | Course       | C1        | Categories 1 |
      | Course       | C1        | Categories 2 |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext    |
      | Categories 1     | truefalse | TF1  | First question  |
      | Categories 2     | truefalse | TF2  | Second question |
    And I log in as "admin"
    And the following "activities" exist:
      | activity | name           | intro                 | course | idnumber |
      | quiz     | Test quiz name | Test quiz description | C1     | quiz1    |
    And quiz "Test quiz name" contains the following questions:
      | question | page |
      | TF2      | 1    |
    When I am on the "quiz1" "Activity" page logged in as "teacher1"
    And I navigate to "Questions" in current page administration
    Then I should see "TF2"
    And I should not see "TF1"
    And I click on "Second question" "link"
    And I should see "Categories 2 (1)"
    And I press "id_submitbutton"
    And I click on "Quiz administration" "text"
    And I click on "Course administration" "text"
    And I click on "Question bank" "link" in the "#settingsnav li[aria-expanded=true]" "css_element"
    And I should see "TF1"
    And I should not see "TF2"
