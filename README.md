# Quiz Builder (local_quizbuilder)

Web service functions that let external tools – for example an AI assistant connected through the
[MCP web service plugin](https://github.com/onbirdev/moodle-webservice_mcp) – build automatically graded
quizzes in a Moodle course.

Moodle core cannot import questions or create quizzes through web services. This plugin adds that:

| Function | What it does |
| --- | --- |
| `local_quizbuilder_get_rules` | Returns the rules for writing questions (`rules.md`) and their version. Call it first. |
| `local_quizbuilder_import_questions` | Imports questions in Moodle XML format into the question bank of a course. Categories given in the file (`$course$/top/…`) are created. |
| `local_quizbuilder_create_quiz` | Creates a quiz in a course section (attempts, question behaviour, shuffling, time limit, grade, open/close dates, review options). |
| `local_quizbuilder_add_quiz_questions` | Adds questions to a quiz, either by ID or all current questions of a category. |
| `local_quizbuilder_create_choice` | Creates a choice activity, e.g. a poll where students show which lesson they are working on. |

## Requirements

- Moodle 5.0 or later (question banks are course activities since 5.0; tested on Moodle 5.2)
- Web services enabled

## Installation

1. Install the plugin as `local/quizbuilder` (Site administration → Plugins → Install plugins, or unzip into `local/quizbuilder`).
2. Site administration → Server → Web services → External services: open **Quiz Builder Service** and add the users who may use it.
3. Create a token for such a user and the **Quiz Builder Service**.

The functions can also be added to any custom external service.

## Permissions

Every function checks the capabilities of the token's user in the target course, as the Moodle user interface does:

- `import_questions`: `moodle/course:manageactivities` in the course and `moodle/question:add` in the question bank
- `create_quiz`: `moodle/course:manageactivities` and `mod/quiz:addinstance`
- `add_quiz_questions`: `mod/quiz:manage` and `moodle/question:useall` for every question
- `create_choice`: `moodle/course:manageactivities` and `mod/choice:addinstance`

New activities are created hidden by default.

## Example (REST)

```
POST /webservice/rest/server.php
wstoken=…&moodlewsrestformat=json
wsfunction=local_quizbuilder_create_quiz
courseid=5&sectionnum=1&name=Quiz 1&attempts=1&grade=10
```

## Rules for AI assistants

`rules.md` says how questions should be written: language and tone, Moodle XML details and, above all,
feedback that fits each question. `get_rules` returns it together with a version (a short hash of the file).
`import_questions` requires this version as `rulesversion`, so an assistant has to fetch the rules before it can
import anything. The rules also ask the assistant to confirm them to the user before the first import.
When `rules.md` is edited, the version changes and the rules have to be read again.

## Notes on Moodle XML

Multiple choice, matching, ordering and drag-and-drop questions have combined feedback
(`correctfeedback`, `partiallycorrectfeedback`, `incorrectfeedback`). `import_questions` refuses a file
in which one of these is missing or empty and imports nothing. The error message names the questions and
fields and says what to write, so an AI assistant can fill them in for each question and send the file again.
(Without this check, missing fields end in a database error from Moodle.)

## License

GNU GPL v3 or later. © 2026 Holger Sebastião
