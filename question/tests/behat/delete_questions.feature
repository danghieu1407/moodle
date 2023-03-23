@core @core_question
Feature: A teacher can delete questions in the question bank
  In order to remove unused questions from the question bank
  As a teacher
  I need to delete questions

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1 | weeks |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype | name                        | questiontext                  |
      | Test questions   | essay | Test question to be deleted | Write about whatever you want |
    And I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"

  @javascript
  Scenario: A question not used anywhere can really be deleted
    When I choose "Delete" action for "Test question to be deleted" in the question bank
    And I press "Delete"
    And I set the field "Also show old questions" to "1"
    Then I should not see "Test question to be deleted"

  Scenario: Deleting a question can be cancelled
    When I choose "Delete" action for "Test question to be deleted" in the question bank
    And I press "Cancel"
    Then I should see "Test question to be deleted"

  @javascript
  Scenario: Delete a question used in a quiz
    Given the following "activity" exists:
      | course   | C1        |
      | activity | quiz      |
      | idnumber | Test quiz |
      | name     | Test quiz |
    And the following "question" exists:
      | questioncategory | Test questions                   |
      | qtype            | truefalse                        |
      | name             | Test used question to be deleted |
      | questiontext     | Write about whatever you want    |
    And quiz "Test quiz" contains the following questions:
      | question                         | page | requireprevious |
      | Test used question to be deleted | 1    | 0               |
    When I am on the "Course 1" "core_question > course question bank" page
    And I choose "Delete" action for "Test used question to be deleted" in the question bank
    And I should see "The following question(s) will be deleted. You can't undo this."
    And I should see "(* Denotes questions which can't be deleted because they are in use. Instead, they will be hidden in the question bank unless you select 'Show old questions'.)"
    And I press "Delete"
    Then I should not see "Test used question to be deleted"
    And I set the field "Also show old questions" to "1"
    And I should see "Test used question to be deleted"
    And I am on the "Test quiz" "quiz activity" page
    And I click on "Preview quiz" "button"
    And I should see "Write about whatever you want"

  @javascript
  Scenario: A question can be deleted even if that question type is no longer installed
    Given the following "questions" exist:
      | questioncategory | qtype       | name            | questiontext    |
      | Test questions   | missingtype | Broken question | Write something |
    And I reload the page
    When I choose "Delete" action for "Broken question" in the question bank
    And I press "Delete"
    And I set the field "Also show old questions" to "1"
    Then I should not see "Broken question"

  @javascript
  Scenario: Delete question has multiple versions
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I choose "Edit question" action for "Test question to be deleted" in the question bank
    And I set the field "id_name" to "Renamed question version2"
    And I set the field "id_questiontext" to "edited question"
    And I press "id_submitbutton"
    And I should not see "Test question to be deleted"
    And I should see "Renamed question version2"
    And I choose "Delete" action for "Renamed question version2" in the question bank
    And I should see "The following question(s) will be deleted. You can't undo this."
    And I should not see "(* Denotes questions which can't be deleted because they are in use. Instead, they will be hidden in the question bank unless you select 'Show old questions'.)"
    And I press "Delete"
    Then I should not see "Test question to be deleted"
    And I should not see "Renamed question version2"

  @javascript
  Scenario: Delete version of question has more than one version
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I choose "Edit question" action for "Test question to be deleted" in the question bank
    And I set the field "id_name" to "Renamed question version2"
    And I set the field "id_questiontext" to "edited question"
    And I press "id_submitbutton"
    And I should not see "Test question to be deleted"
    And I should see "Renamed question version2"
    And I choose "Edit question" action for "Renamed question version2" in the question bank
    And I set the field "id_name" to "Renamed question version3"
    And I set the field "id_questiontext" to "edited question"
    And I press "id_submitbutton"
    And I should not see "Test question to be deleted"
    And I should see "Renamed question version3"
    And I choose "Edit question" action for "Renamed question version3" in the question bank
    And I set the field "id_name" to "Renamed question version4"
    And I set the field "id_questiontext" to "edited question"
    And I press "id_submitbutton"
    And I should not see "Test question to be deleted"
    And I should see "Renamed question version4"
    And I choose "History" action for "Renamed question version4" in the question bank
    And I should see "Test question to be deleted"
    And I should see "Renamed question version2"
    And I should see "Renamed question version3"
    And I should see "Renamed question version4"
    And I choose "Delete" action for "Renamed question version3" in the question bank
    And I should see "The following question(s) will be deleted. You can't undo this."
    And I should not see "(* Denotes questions which can't be deleted because they are in use. Instead, they will be hidden in the question bank unless you select 'Show old questions'.)"
    And I press "Delete"
    Then I should not see "Renamed question version3"
    And I should see "Test question to be deleted"
    And I should see "Renamed question version2"
    And I should see "Renamed question version4"

  @javascript
  Scenario: Delete multiple question include question has multiple version
    Given the following "questions" exist:
      | questioncategory | qtype     | name       | questiontext   |
      | Test questions   | truefalse | Question A | First question |
    When I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    And I choose "Edit question" action for "Test question to be deleted" in the question bank
    And I set the field "id_name" to "Renamed question version2"
    And I set the field "id_questiontext" to "edited question"
    And I press "id_submitbutton"
    And I should not see "Test question to be deleted"
    And I should see "Renamed question version2"
    And I click on "Select all" "checkbox"
    And I click on "With selected" "button"
    And I click on question bulk action "deleteselected"
    And I should see "The following question(s) will be deleted. You can't undo this."
    And I should not see "(* Denotes questions which can't be deleted because they are in use. Instead, they will be hidden in the question bank unless you select 'Show old questions'.)"
    And I press "Delete"
    Then I should not see "Renamed question version2"
    And I should not see "Question A"

  @javascript
  Scenario: Delete specific version of question
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I choose "Edit question" action for "Test question to be deleted" in the question bank
    And I set the field "id_name" to "Renamed question version2"
    And I set the field "id_questiontext" to "edited question"
    And I press "id_submitbutton"
    And I choose "Edit question" action for "Renamed question version2" in the question bank
    And I set the field "id_name" to "Renamed question version3"
    And I set the field "id_questiontext" to "edited question"
    And I press "id_submitbutton"
    And I choose "Edit question" action for "Renamed question version3" in the question bank
    And I set the field "id_name" to "Renamed question version4"
    And I set the field "id_questiontext" to "edited question"
    And I press "id_submitbutton"
    And I choose "History" action for "Renamed question version4" in the question bank
    And I click on "Renamed question version2" "checkbox"
    And I click on "Renamed question version3" "checkbox"
    And I click on "With selected" "button"
    And I click on question bulk action "deleteselected"
    And I should see "The following question(s) will be deleted. You can't undo this."
    And I should not see "(* Denotes questions which can't be deleted because they are in use. Instead, they will be hidden in the question bank unless you select 'Show old questions'.)"
    And I press "Delete"
    And I choose "History" action for "Renamed question version4" in the question bank
    Then I should not see "Renamed question version2"
    And I should not see "Renamed question version3"
    And I should see "Test question to be deleted"
    And I should see "Renamed question version4"
