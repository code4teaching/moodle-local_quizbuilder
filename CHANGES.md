# Changes

## 0.2.0 (2026-11-05)

- New `add_random_questions`: adds a number of random questions from a category to a quiz. Every attempt
  draws its own questions, so students sitting next to each other get different quizzes (self-tests).
  Refuses to add more random questions than the category contains.

## 0.1.3 (2026-11-04)

- `import_questions` checks the combined feedback (correct, partially correct, incorrect) of multiple choice,
  matching, ordering and drag-and-drop questions. If one is missing or empty, nothing is imported and the
  message asks the caller to write feedback that fits each question, instead of failing with a database error.
- The function and parameter descriptions tell AI assistants about this up front.
- New `get_rules`: returns `rules.md` (how to write questions and feedback) and its version.
  `import_questions` requires this version as the new parameter `rulesversion`, so the rules have to be read
  before importing; the rules ask the assistant to confirm them to the user. Callers of 0.1.2 must add it.

## 0.1.2 (2026-10-29)

- Maturity: beta.

## 0.1.1 (2026-10-29)

- Requires Moodle 5.0 (question banks are course activities since 5.0).
- PHPUnit tests for all functions, including capability checks.
- GitHub Actions with moodle-plugin-ci (Moodle 5.0 and 5.2, PostgreSQL and MariaDB).
- Follows the Moodle coding style; no PHP warning when creating quizzes and choices.

## 0.1.0 (2026-10-29)

- First version: `import_questions`, `create_quiz`, `add_quiz_questions`, `create_choice`.
- Tested on Moodle 5.2.2: all four functions (question import, quiz creation, adding questions, choice).
