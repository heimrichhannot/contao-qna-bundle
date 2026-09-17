# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]
- Added: optional **Question session** field (`tl_content.qnaSession`) on the reader content element to embed a fixed session in any page instead of resolving it from the URL item; an unpublished or deleted fixed session renders an empty element instead of a 404
- Added: restart of closed sessions from the stage (**Start a new round**); every restart increments `tl_qna_session.round`, questions store the round they were submitted in and reader and stage only show the current round
- Added: route `contao_qna_session_restart`, `SessionService::restart()` and `QnaSessionGateway::markReopened()`; `start()` still requires a waiting session
- Added: `QuestionArchivedException` (422) – votes and answered toggles on questions from earlier rounds are rejected, including from stale pages
- Added: `round` column, list label and filter for questions in the back end; the parent header shows the session round
- Added: back end info message via `Contao\Message` when neither `huh_ux_turbo_encore` nor `huh_ux_turbo_encore_no_drive` is active for the edited Q&A content element or stage page, or anywhere in the project when opening the Q&A module
- Changed: database update required – new columns `tl_content.qnaSession`, `tl_qna_session.round` and `tl_qna_question.round` (default 1), index `pid,round,createdAt` replaces `pid,createdAt` on `tl_qna_question`
- Changed: `closed` is no longer a final session state (`waiting → open → closed → open`)
- Changed: `QnaQuestionGateway::create()`, `findForSession()` and `findForStage()` take the session round; `QnaSessionReaderController::resolveSession()` takes the `ContentModel` and returns `?Session`; `StageView` and `StageUrlSet` gained restart parameters
- Changed: stage responses containing any control button (start, stop or restart) are private and carry a request token (`StageView::hasControls()`)

## [0.1.0] - 2026-09-15
Initial release
