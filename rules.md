# Quiz Builder rules

These rules apply to every question you send to `local_quizbuilder_import_questions`.
Read them before writing questions. Pass the `rulesversion` from `local_quizbuilder_get_rules`
to `import_questions` to confirm that you have read them. When the rules change, the version changes
and you have to read them again.

Before your first import, tell the user in one or two sentences that you have received these rules
and what they mean for the questions you write.

## Language and tone

- Write questions, answers and feedback in the language of the course, unless the user asks for another one.
- Address students directly and informally (in German: „du“).
- Use short sentences. One question asks one thing.

## Moodle XML

- Start the file with a category question: `$course$/top/<category name>`.
- Give every question a `<name>` that is unique and says what it is about, e.g. `L03-02 Loop count`.
- Set `<defaultgrade>` and `<penalty>` explicitly.
- Put code in `<code>` or `<pre>` and escape `<`, `>` and `&`.

## Feedback

Feedback is part of the question, not decoration. Write it for the question at hand.

- **`generalfeedback`**: shown after the attempt to everyone. Explain the idea behind the correct answer
  in one or two sentences.
- **Per answer** (`<answer>…<feedback>`): for a wrong answer, name the typical mistake that leads to it.
- **Combined feedback** for multiple choice, matching, `randomsamatch`, ordering, `ddwtos`, `gapselect`,
  `ddimageortext` and `ddmarker` – all three are required, none may be empty:
  - `correctfeedback`: confirm what the student got right and why it is right.
  - `partiallycorrectfeedback`: say which part to check again, without listing the solution.
  - `incorrectfeedback`: give a hint towards the solution without revealing it.
- Add `<shownumcorrect/>` to these types so students see how many parts were right.

A file with missing or empty combined feedback is refused as a whole and nothing is imported.
The error message names the questions and fields; fix them and send the whole file again.

## Quizzes and choices

- New quizzes and choices are created hidden. Tell the user to check them before making them visible.
- Do not change grades, attempts or dates the user did not ask for.
