# Contao Q&A Bundle

Question-and-answer sessions for events on Contao 5.7. Front end members can
submit questions and vote; authenticated operators can open and close a
session on a protected stage page.

## Requirements

- PHP 8.4 or newer
- Contao 5.7
- a database supported by Contao and Doctrine DBAL
- Turbo Frames in the browser
- a project set up for [Contao Encore Bundle](https://github.com/heimrichhannot/contao-encore-bundle)

The bundle ships its front end assets as Webpack Encore entries rather than as
prebuilt files. Its own entry is `huh_qna`; the controllers activate it for the
pages that need it.

**Turbo has to be provided by the project.** Activate one of the two entries
from [contao-ux-turbo-encore](https://github.com/heimrichhannot/contao-ux-turbo-encore)
in the layout or page settings:

| Entry | Turbo Drive |
| --- | --- |
| `huh_ux_turbo_encore` | enabled |
| `huh_ux_turbo_encore_no_drive` | disabled |

The bundle works with either and deliberately does not activate one itself:
whether Turbo Drive intercepts navigation is a site-wide decision, and a bundle
silently turning it off would break projects that rely on it. Both entries are
head scripts exposing the instance as `window.Turbo`, which the Q&A entry
reuses instead of bundling a second copy. If neither is active, the Q&A
JavaScript logs an error to the browser console.

Consequence: the host project needs an Encore build (`yarn`/`webpack`). See
the Encore Bundle documentation for the project setup.

## Installation

Install the package in the Contao project and run the regular Contao database
migration:

```bash
composer require heimrichhannot/contao-qna-bundle
vendor/bin/contao-console contao:migrate
```

The database update can alternatively be run through Contao Manager. The
package provides its bundle registration, services, DCA, routes, translations,
Twig templates and Encore entries itself; no code has to be copied into the
host project.

After installing, regenerate the Encore entries and build them:

```bash
vendor/bin/contao-console huh:encore:prepare
yarn install
yarn encore dev
```

The `huh_qna` entry is activated by the bundle's controllers for the pages that
need it and does not have to be switched on in the back end. The Turbo entry
does — see Requirements above.

Optional technical limits can be set in the host configuration:

```yaml
# config/config.yaml
contao_qna:
    polling_interval: 2500
    max_question_length: 500
    question_cooldown: 20
    idle_polling_interval_multiplier: 4
    max_polling_interval_multiplier: 16
```

The values shown are the defaults. `polling_interval` and
`question_cooldown` are milliseconds and seconds respectively. Waiting and
closed sessions poll at `idle_polling_interval_multiplier` times the configured
base interval. Client-side retry backoff is capped at
`max_polling_interval_multiplier` times the base interval.

## Contao setup

1. Open **Q&A > Question sessions** in the Contao back end, create the
   sessions and publish them.
2. Create a regular page for the session list, for example `fragerunden`.
   Add the **Q&A session list** content element and select the reader page in
   its **Reader page** field.
3. Create that regular reader page and add the **Q&A session reader** content
   element. The reader resolves the session from the URL item; it needs no
   session selection in its content element.
4. Create a page with page type **Q&A stage** (`qna_stage`), for example
   `buehne`, and assign a page layout. The controller supports a modern Twig
   slot layout and the classic Contao layout fallback. The page type does not
   permit article assignment because its `main` slot is supplied by the
   controller.
5. Protect the stage page with Contao's page protection and select the front
   end member groups allowed to operate it. This host-side configuration is
   mandatory even if a custom voter is used.

For a project without a URL suffix, the resulting URLs are:

```text
/fragerunden
/fragerunden/<alias>
/buehne
/buehne/<alias>
```

Contao appends the root page's configured URL suffix where applicable, for
example `.html`. The list displays published sessions only. A missing,
unknown or unpublished reader/stage alias returns 404.

## Technical architecture

The bundle contains two content elements and one page controller:

- `qna_session_list` renders published sessions and links to the selected
  reader page through Contao's `ContentUrlGenerator`.
- `qna_session_reader` renders cache-neutral lazy Turbo Frame shells for the
  URL's session alias: one stable controls frame and one polling questions
  frame.
- page type `qna_stage` renders either the published-session overview or one
  session's operator view. Modern layouts use Contao's Twig-slot
  `ContentComposition`; classic layouts use the documented legacy fallback.

Three DBAL gateways isolate persistence. Validation and state transitions are
implemented in services, HTTP semantics in controllers, and presentation in
Twig. The tables are:

- `tl_qna_session`: title, unique alias, publication flag, state
  (`waiting`, `open`, `closed`) and start/end timestamps.
- `tl_qna_question`: parent session, author member ID, question text and
  creation timestamp and a recoverable `voteCount` cache.
- `tl_qna_vote`: parent question, member ID and creation timestamp. A unique
  database index on `(pid, memberId)` makes one vote per member and question
  an invariant. Duplicate inserts are also handled idempotently by the vote
  service.

The parent/child DCA chain is session -> question -> vote. Deleting a session
in the Contao back end therefore removes its questions and votes. Member-data
erasure removes the member's questions and votes; account deactivation keeps
them.

## Turbo actions and polling

The reader starts with two lazy Turbo Frames. Its non-polling controls frame
contains status, form errors and the question form. Its polling questions
frame contains questions, vote counts and vote buttons. A status-selective
Turbo Stream updates the controls only when the server-side session state has
changed, so normal question polling never replaces text being edited. The
stage detail uses one polling frame.

Question, vote, start and stop forms use POST and a Contao `REQUEST_TOKEN`.
Successful writes return a frame-local `303 See Other`; the redirected GET
uses Turbo Streams to update the affected regions. A created question updates
the list and resets the form once. A business rejection that must remain
visible returns `422 Unprocessable Entity` as HTML for the originating frame;
rejected question text is rendered back into the form. Missing authentication,
failed CSRF validation and denied authorization retain their own hard error
status.

Only frames are refreshed; Turbo Drive is not enabled by this bundle. Polling
pauses while the tab is hidden, removes timers for detached/cached frames,
avoids duplicate timers and backs off exponentially after transport or frame
errors, capped at sixteen times the base interval. A polling reload is delayed
while its frame contains keyboard focus or is processing an action. Polling
reloads morph elements with stable IDs, protecting vote and sort interactions
that overlap an already running request. Sorting by votes or time is performed
in the database, and the selected sort remains in the frame URL.

Reader frames, action responses and stage frames containing controls use
`Cache-Control: private, no-store`. Cookie-free spectator stage fragments without
an Authorization header allow one second of shared caching, below the default
2.5-second polling interval (`public, max-age=0, s-maxage=1, must-revalidate`).
They vary by Accept, Accept-Language, Cookie and Authorization. Polling intervals
of one second or less disable shared caching. Contao may additionally make
responses private when a session or response cookie is present.

The
reader shell and stage detail shell are cache-neutral and contain no member
state, token, live question data or current session state. The stage overview
is private because it contains current states. Frame routes deliberately do
not emit `ETag`: conditional requests would still execute the private database
queries and would not reduce the dominant request rate.

## Session-control authorization

Start and stop require the voter attribute `QNA_SESSION_CONTROL`. The bundle's
default `QnaSessionControlVoter` grants it to every authenticated Contao front
end member. Page protection is the first access boundary; a host project that
needs roles, groups or per-session assignments must replace the default voter
service with a stricter implementation.

For example, define an application voter that supports the same attribute,
then replace the bundle service ID:

```yaml
# config/services.yaml
services:
    HeimrichHannot\QnaBundle\Security\Voter\QnaSessionControlVoter:
        class: App\Security\Voter\RestrictedQnaSessionControlVoter
        autowire: true
        autoconfigure: true
```

Merely adding a second denying voter is not equivalent when Symfony uses an
affirmative access-decision strategy: the bundle's granting voter could still
win. Replace it or configure an access-decision strategy whose behavior has
been deliberately reviewed.

## Operations and capacity

The reader detail page and the stage detail page each contain one polling frame
per viewer. At the default 2.5-second open interval, one viewer therefore
produces `1 frame / 2.5 seconds = 0.4` non-cacheable requests per second.
Approximate open-session load per viewed detail page is:

| Concurrent viewers | Polling frames per viewer | Requests/second |
| ---: | ---: | ---: |
| 100 | 1 | 40 |
| 500 | 1 | 200 |
| 1,000 | 1 | 400 |

Waiting and closed sessions poll every 10 seconds by default, resulting in
approximately 10, 50 and 100 requests/second for the same viewer counts. A
person who opens both reader and stage detail pages concurrently creates two
polling frames; for capacity planning, count each concurrently viewed detail
page once. Form actions add short bursts. Deployments must size PHP workers and
database capacity for concurrent viewers; this polling design is not a push
system.

## Security measures

- question and vote writes require an authenticated front end member; the
  member ID comes only from Symfony's security context, never request data;
- all four writes are POST-only and CSRF-protected;
- stage control additionally checks `QNA_SESSION_CONTROL`;
- questions are accepted only for published, open sessions, are trimmed,
  length-limited and subject to a per-member/per-session cooldown;
- votes are accepted only for published, open sessions and are protected by
  both a database unique constraint and idempotent duplicate handling;
- Twig escapes submitted question text by default;
- dynamic responses are private and non-storable.

## Known limitations

- There is deliberately no moderation workflow. Authenticated questions are
  visible immediately. Authentication, session publication/state checks,
  length limits, cooldown, output escaping and back-end deletion reduce abuse
  but do not replace approval, reporting or moderation. Projects that require
  pre-publication review must add it separately.
- The default stage-control voter treats every authenticated front end member
  as an operator. Page protection and appropriate member-group assignment are
  mandatory; replace the voter for finer rules.
- The modern Contao `ContentComposition` API used by the stage controller is
  marked experimental. It is isolated in that controller but can change in a
  future Contao minor release.
- Classic layouts depend on Contao 5.7's deprecated
  `FrontendIndex::renderPage()` and a temporary `generatePage` hook. This
  fallback must be replaced for Contao 6.
- With an optional alias placed before a root URL suffix, Contao 5.7 can
  resolve the stage overview but neither the Symfony router nor
  `ContentUrlGenerator::generate($pageModel)` can generate that overview with
  an empty alias. The bundle therefore cannot add a generated back link from
  the stage detail. Navigation supplied by the page layout remains available.
- Polling scales linearly with concurrent viewers (one visible frame per reader
  or stage detail view) and has no shared cache, push channel or cross-client
  coalescing. Exponential backoff helps failures, not normal peak load.
- Member cleanup is guaranteed for Contao's back-end delete and front-end
  account-closing flows. Direct SQL deletion, third-party CLI deletion and
  external privacy tools that bypass Contao callbacks/events are outside this
  guarantee.

No other host code or build integration is required. The necessary host
configuration is limited to the regular/list/reader/stage pages and layouts,
the stage page's protection and member groups, optional limits, and an
optional stricter voter.

## Answered questions

During an open session, stage operators with `QNA_SESSION_CONTROL` can mark
questions as answered and undo that mark. The stage groups unanswered questions
first and answered questions last, retaining the selected vote/time ordering in
each section. Closed sessions retain the groups and badges without controls.
Participants keep their vote-sorted list and see an “Answered” badge. Answered
questions cannot receive votes, including from stale pages; undo restores voting.
Existing votes remain intact. No answer text or completion timestamp is stored.

Run the Contao database update before serving the changed code: the additive
`tl_qna_question.answered` boolean defaults to false for existing and new rows.
All interactive writes use the transaction and lock policy below. Explicit
answered/unanswered POST actions retain Contao CSRF, ownership validation and the
existing stage voter, with private, no-store 303 redirects retaining the sort.

## Transactions and locking

`QuestionService`, `VoteService`, `QuestionAnswerService`, and `SessionService`
each own a DBAL transaction on the same connection as their gateways. The lock
order is **session row → question row(s) → vote row(s)**. Acquire session locks
before any child locks; callers composing operations across multiple sessions
must acquire all session locks in ascending ID order first. Gateway mutations do
not start transactions or enforce business rules themselves.

Every service locks the session with `SELECT … FOR UPDATE`, then checks its
publication and required state using the shared `QnaSession` assertions. The
lock remains held through commit or rollback. Start/stop therefore coordinate
with submission, voting, and both answered/unanswered actions. If closure wins,
the waiting write rejects the closed session; if a write wins, it completes
before closure. Existing authorization, error responses, and validation
precedence are retained.

Submission checks the clock and the member's latest question only after locking
the session. Its current (locking) history read also works when a caller already
has a repeatable-read snapshot. The session row exists even when that member has
no previous question, so concurrent first submissions cannot both pass a positive
cooldown. Question creation and the author's automatic vote commit or roll back
together. A zero cooldown still permits repeated submissions.

Voting performs an unlocked discovery read to resolve the session, then locks
the session and re-reads the question under lock, checking ownership and answered
state again. Answering uses the same order and retains its question lock.
Duplicate votes still succeed idempotently through the unique `(pid, memberId)`
index; a current vote-state read returns the committed count even after waiting.

This deliberately serializes interactive writes within each session, including
votes on different questions. Keep these transactions short and free of network
I/O. There is no application-wide lock for interactive writes, although InnoDB
range/gap locks can cause additional contention across sessions. This policy
removes the session/question lock-order inversion; it is not a promise that
arbitrary external transactions can never deadlock. Database lock timeouts and
deadlocks propagate and roll back; no automatic replay is added.

`MemberDataEraser` is maintenance, independent of publication/state: it locks all
sessions in ascending ID order, then authored questions, before deleting votes
and questions atomically. This intentionally pauses interactive writes across
sessions during erasure and prevents a vote/question deletion lock inversion.
It does not revoke authentication or prevent a still-authenticated client from
creating new data after erasure; account lifecycle remains Contao's responsibility.

Backend publication updates acquire the same InnoDB session-row lock even without
calling these services. An unpublish committed first is seen by the locking read;
an unpublish arriving second waits until the already validated write finishes.
The guarantee is database serialization order, not HTTP request arrival order or
retroactive cancellation. Standard backend deletion/cascades, custom SQL and
other extensions are not made into atomic service transactions by this policy;
code composing such writes must follow the same ordering. Use InnoDB tables and
one shared connection; an outer transaction retains locks until its own completion.
No schema migration is introduced by this locking change.

## Database concurrency tests

The integration suite is opt-in and requires a disposable, migrated MySQL/MariaDB
InnoDB database, PHP `pdo_mysql`, `pcntl` and `posix`. It uses separate processes
and connections, waits for an actual `INNODB_TRX` lock wait on the session, then
releases the first transaction and checks persisted outcomes. The observer needs
`PROCESS` privilege; the service connections use the normal database account.
The suite removes its own fixtures, but a forcibly killed runner may need cleanup.

From a configured DDEV project directory (locally, `contao0507.contao`):

```bash
ddev exec -d /path/to/contao-qna-bundle env \
  QNA_DATABASE_TESTS=1 \
  QNA_TEST_OBSERVER_USER=root QNA_TEST_OBSERVER_PASSWORD=root \
  vendor/bin/phpunit --testsuite Integration --fail-on-skipped
```

The bundle path must be accessible inside that container. Outside DDEV, run the
same PHPUnit command with the environment variables set for your test database.
`QNA_TEST_DB_HOST`, `QNA_TEST_DB_PORT`, `QNA_TEST_DB_NAME`, `QNA_TEST_DB_USER`, and
`QNA_TEST_DB_PASSWORD` are configurable (defaults: `db`, `3306`, `db`, `db`, `db`).
The observer uses the same host/database and defaults to the service credentials;
override its user/password as above when those lack `PROCESS`. No project name
is hardcoded. Tests explicitly exercise repeatable-read isolation, including
pre-existing snapshots for submission and duplicate voting.

Coverage includes cooldown races with and without previous questions; closure
before/after submission, voting, answering and unanswering; both answer/vote
orderings; concurrent duplicate votes; start versus submission; backend
unpublication orderings; and a real unique-constraint failure during automatic
author-vote insertion, proving rollback of both the question and vote.


CI runs the named unit and integration suites separately, supplies MariaDB 10.11
and builds the three InnoDB tables from the DCA definitions using
`QNA_DATABASE_TESTS=1 php tests/Fixtures/create-schema.php`. This bootstrap only
creates tables and must target an empty disposable database. Without the opt-in
variable the integration suite skips; CI treats any skipped integration test as
a failure. The root observer credentials are separate from the application user.

Votes update the cached question count in the same transaction as their insert;
duplicate votes do not increment it. Member erasure adjusts the affected counts.
`RebuildVoteCountMigration` adds/backfills the column on upgrade and repairs
mismatches on later Contao migration runs. Direct SQL changes must either maintain
this cache or be followed by the migration. Integration tests cover counter
consistency after duplicate votes, erasure, question removal and migration repair,
including concurrent erasure/voting and guest votes with member ID zero.
