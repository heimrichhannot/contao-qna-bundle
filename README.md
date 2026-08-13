# Contao Q&A Bundle

Question-and-answer sessions for events on Contao 5.7. Front end members can
submit questions and vote; authenticated operators can open and close a
session on a protected stage page.

## Requirements

- PHP 8.4 or newer
- Contao 5.7
- a database supported by Contao and Doctrine DBAL
- Turbo Frames in the browser

The package ships pinned Turbo 8.0.23. Its JavaScript module imports that copy
only if `window.Turbo` does not already exist. If the host already provides
Turbo, the bundle uses that instance and does not change
`Turbo.session.drive`. No JavaScript build step is required in the host
project.

## Installation

Install the package in the Contao project and run the regular Contao database
migration:

```bash
composer require heimrichhannot/contao-qna-bundle
vendor/bin/contao-console contao:migrate
```

The database update can alternatively be run through Contao Manager. The
package provides its bundle registration, services, DCA, routes, translations,
Twig templates and public assets itself; no code has to be copied into the host
project.

Optional technical limits can be set in the host configuration:

```yaml
# config/config.yaml
contao_qna:
    polling_interval: 2500
    max_question_length: 500
    question_cooldown: 20
```

The values shown are the defaults. `polling_interval` and
`question_cooldown` are milliseconds and seconds respectively. Waiting and
closed sessions poll at four times the configured base interval.

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
  creation timestamp.
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

Every frame and action response uses `Cache-Control: private, no-store`. The
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
