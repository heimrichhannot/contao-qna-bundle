# FEATURES — contao-qna-bundle

Referenzspezifikation für drei fachliche Erweiterungen der bestehenden
Erweiterung. Aufgestellt am 17.09.2026.

Diese Datei ist die **einzige fachliche Quelle** für die drei Features. Die
Prompts unter `prompts/feature-*.md` verweisen auf die Abschnitte dieser Datei
und wiederholen sie nicht. Jeder Prompt ist für sich in Codex ausführbar.

Fachliche Grundlage bleibt `.docs/build/SPEC.md`; die Architekturregeln aus
`.docs/refactor/REFACTOR.md` §0 gelten weiter. Anders als der Refactor sind
diese Features **verhaltensändernd** — jede Änderung am nach außen sichtbaren
Verhalten wird in `SPEC.md` nachgezogen und in `.docs/build/DECISIONS.md` als
neue Entscheidung (D13 ff.) begründet.

---

## 0. Verbindliche Rahmenbedingungen

Diese Regeln gelten für **jeden** Prompt. Ein Prompt wiederholt sie nicht.

### 0.1 Ausgangslage

Die Features setzen auf dem aktuellen `main` auf (Stand `134fcc8`, Refactor
Phasen 1–5 und 7 abgeschlossen). Die Schichtung
Gateway → Domain → Service → View → Controller bleibt unverändert; neue
Funktionalität wird in genau die Schicht eingebaut, in die sie gehört:

| Belang | Schicht | Beispiel |
| --- | --- | --- |
| SQL, Sperren, Hydrierung | `src/Gateway/` | neue Spalte lesen/schreiben |
| Statusregeln, Übergänge, Validierung | `src/Service/`, `src/Domain/` | Neustart einer Session |
| View-Modelle, Twig-Kontext | `src/View/` | neuer Button in der Bühne |
| HTTP: Routen, Statuscodes, Redirects, Cache-Header | `src/Controller/` | neue POST-Aktion |
| Backend-Callbacks | `src/EventListener/DataContainer/<Tabelle>/` | Hinweismeldung |

Unangetastet bleiben:

* die Sperrreihenfolge Session → Frage → Vote und die `FOR UPDATE`-Lesevorgänge,
* die bedingten Statuswechsel (`UPDATE … WHERE state = :expected`),
* die Fehlerpräzedenz in `LockedContextLoader` (Frage vor Session),
* die Behandlung eines Duplicate-Votes als Erfolg,
* der Grundsatz „Turbo stellt das Projekt bereit, nicht das Bundle" (D2, D12),
* PHPStan `level: max` über `src` **und** `tests`,
* die Nicht-Ziele aus `SPEC.md` §1.1 (keine Moderation, keine
  Benachrichtigungen, kein Export).

### 0.2 API-Verifikationspflicht

Unverändert aus `SPEC.md` §0.2: Jede verwendete Contao-Klasse, -Methode,
-Attribut-Signatur und -Konstante wird vor Verwendung unter
`vendor/contao/core-bundle/` nachgelesen. Nicht auffindbare APIs werden nicht
erfunden.

**Besonderheit für Feature 3:** `heimrichhannot/contao-encore-bundle` ist
**keine** Abhängigkeit dieses Repositories und liegt hier nicht unter
`vendor/`. Die Belege dafür stammen aus dem DDEV-Projekt:

```
/home/dev/Kunden/contao/contao_0507/vendor/heimrichhannot/contao-encore-bundle/
```

Dort nachlesen, hier zitieren. Das Bundle selbst darf keine Klasse aus dem
Encore-Bundle referenzieren (Abschnitt 0.4); es liest ausschließlich
Datenbankspalten und Modell-Eigenschaften.

### 0.3 Ehrlichkeitsregel für Checks

Unverändert aus `SPEC.md` §0.3: Behaupte nie, einen Check ausgeführt zu haben.
Jeder genannte Check enthält die tatsächlich abgesetzte Kommandozeile und die
reale Ausgabe (gekürzt). Nicht ausführbare Checks werden als **nicht
verifiziert** gekennzeichnet.

### 0.4 Abhängigkeiten

`composer.json` bleibt unverändert. Die drei Features brauchen keine neue
Abhängigkeit:

* Feature 1 nutzt nur Contao-Core-DCA und die bestehenden Gateways.
* Feature 2 nutzt DBAL und die bestehenden Services.
* Feature 3 nutzt `Contao\Message`, `Contao\StringUtil`, `Contao\PageModel`,
  `Contao\LayoutModel`, `Contao\ArticleModel` aus dem Core und die Konstanten
  `HeimrichHannot\ContaoUxTurboEncore\EncoreExtension::DEFAULT` /
  `::NO_DRIVER` aus `heimrichhannot/contao-ux-turbo-encore`, das bereits eine
  harte Abhängigkeit ist (`vendor/heimrichhannot/contao-ux-turbo-encore/src/EncoreExtension.php:10-11`).

### 0.5 Sprache und Stil

Code, Bezeichner, Kommentare, Commit-Nachrichten und README: **Englisch**.
Diese Dokumentation: Deutsch. Übersetzungen als Symfony-PHP-Ressourcen unter
`translations/contao_*.de.php` und `.en.php`, immer **beide** Sprachen.
`final readonly` als Regel, Konstruktor-Promotion, `declare(strict_types=1)`,
Listener ausschließlich per Attribut (`#[AsCallback]`), DCA-SQL in
Doctrine-Schemarepräsentation (`AGENTS.md`).

### 0.6 Reihenfolge und Abschluss

Empfohlene Reihenfolge: **Feature 1 → Feature 3 → Feature 2.**

Begründung: Feature 1 und 3 sind klein bzw. unabhängig; Feature 1 ergänzt nur eine
Spalte in `tl_content`, Feature 3 ändert kein Schema.
Feature 2 ist das größte, ändert zwei Tabellen und berührt Gateways, Services,
Views, Controller, Templates und Integrationstests. Es soll auf einer sauberen,
committeten Basis beginnen. Die Features haben keine Codeabhängigkeit
untereinander; eine andere Reihenfolge ist zulässig, wenn die Prompts einzeln
abgeschlossen werden.

Jedes Feature endet mit einem eigenen Commit (oder einer Commit-Serie auf einem
eigenen Branch) und einem grünen Durchlauf von:

```bash
vendor/bin/php-cs-fixer check --diff --sequential
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpunit --testsuite 'Q&A Bundle'
```

Feature 2 zusätzlich mit der Integrationssuite (Abschnitt 0.7).

Ein Feature, das diese Checks nicht besteht, ist nicht abgeschlossen.

### 0.7 Integrationsumgebung

DDEV-Projekt `contao0507.contao` unter `/home/dev/Kunden/contao/contao_0507`.
Das Bundle ist dort als Composer-Path-Repository eingebunden;
`vendor/heimrichhannot/contao-qna-bundle` ist ein Symlink auf dieses
Repository. Änderungen sind sofort wirksam — nach Änderungen an DCA,
Service-Definitionen, Routen oder Übersetzungen den Cache leeren:

```bash
cd /home/dev/Kunden/contao/contao_0507
ddev exec vendor/bin/contao-console cache:clear
```

Schemaänderungen (nur Feature 2):

```bash
ddev exec vendor/bin/contao-console contao:migrate --dry-run
ddev exec vendor/bin/contao-console contao:migrate --no-interaction
```

Integrationssuite (nur Feature 2), aus dem DDEV-Projektverzeichnis:

```bash
ddev exec -d /var/www/html/vendor/heimrichhannot/contao-qna-bundle env \
  QNA_DATABASE_TESTS=1 \
  QNA_TEST_OBSERVER_USER=root QNA_TEST_OBSERVER_PASSWORD=root \
  vendor/bin/phpunit --testsuite Integration --fail-on-skipped
```

Die Suite braucht eine leere Wegwerf-Datenbank mit den drei Bundle-Tabellen
(siehe README, „Database concurrency tests"). Sie erzeugt ihre Fixtures selbst.

Das Demo-Projekt enthält keine Produktionsdaten; Daten dürfen frei angelegt
und manipuliert werden (`AGENTS.local.md`).

---

## 1. Feature 1 — Reader mit fest konfigurierter Session

### 1.1 Anforderung

Das Inhaltselement `qna_session_reader` erhält ein optionales Feld zur Auswahl
einer Fragerunde. Ist eine Session gewählt, wird sie verwendet; der
Item-Parameter (`auto_item`) der URL wird dann weder gelesen noch verbraucht.
Bleibt das Feld leer, bleibt das heutige Verhalten unverändert (Auflösung über
`auto_item`, 404 bei fehlendem/unbekanntem/unveröffentlichtem Alias).

Zweck: eine Fragerunde in bestehende redaktionelle Seiten einbetten, ohne dass
die Seite eine Item-URL braucht.

### 1.2 Heutiger Stand

* `contao/dca/tl_content.php`: Reader-Palette `{type_legend},type`, keine
  Felder.
* `src/Controller/ContentElement/QnaSessionReaderController.php:64-83`:
  `resolveSession()` liest `Input::get('auto_item')` über den Framework-Adapter
  und wirft `PageNotFoundException` bei leerem oder unbekanntem Alias.
  Der Aufruf von `Input::get()` **verbraucht** den Parameter bewusst (D5).
* `tests/Unit/ContentElementDcaTest.php:11-39` schreibt fest, dass die
  Reader-Palette **kein** `session` enthält. Dieser Test kodiert das alte
  Verhalten und wird bewusst umgekehrt (nicht gelöscht).
* `tests/Unit/QnaSessionReaderControllerTest.php` testet `resolveSession()`
  über eine `TestableQnaSessionReaderController`-Unterklasse und einen
  `Input`-Adapter-Fake.
* `SPEC.md` §5.2 formuliert „Keine Session-Auswahl im Backend". Der Absatz wird
  ersetzt.

### 1.3 Zielbild

**DCA** — neues Feld `qnaSession` in `tl_content`:

```php
$GLOBALS['TL_DCA']['tl_content']['fields']['qnaSession'] = [
    'inputType' => 'select',
    'foreignKey' => 'tl_qna_session.title',
    'eval' => ['includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'],
    'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
    'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
];
$GLOBALS['TL_DCA']['tl_content']['palettes']['qna_session_reader'] =
    '{type_legend},type;{qna_legend},qnaSession';
```

Vorbild im Core: `vendor/contao/core-bundle/contao/dca/tl_content.php:754-759`
(`form`-Feld mit `foreignKey`, `includeBlankOption`, `chosen`). Der Blank-Wert
bedeutet „Session aus der URL". Auch unveröffentlichte Sessions sind wählbar —
die Redaktion baut Seiten typischerweise vor der Veröffentlichung.

Feldname camelCase wie die Core-Felder (`jumpTo`, `reg_jumpTo` ist die
Ausnahme, nicht die Regel). Optional darf der `foreignKey` den Alias
mitzeigen: `'tl_qna_session.CONCAT(title, " [", alias, "]")'` — Core-Vorbild
`tl_member.CONCAT(firstname, " ", lastname)` in `tl_content.php`/`tl_module.php`.

**Controller** — `resolveSession()` erhält das `ContentModel`:

1. `$configured = (int) ($model->row()['qnaSession'] ?? 0)` mit derselben
   Typprüfung wie `QnaSessionListController::getResponse()` für `jumpTo`
   (`is_int`/`is_string`, sonst `0`).
2. `$configured > 0` → `$this->sessionGateway->findPublished($configured)`.
   `Input` wird **nicht** angefasst; der Framework-Adapter für `Input` wird in
   diesem Pfad nicht angefordert.
3. Sonst → bestehender `auto_item`-Pfad, unverändert.

**Entscheidung D13 — konfigurierte, aber nicht auffindbare Session:** Die
Seite existiert redaktionell unabhängig von der Session. Ein 404 aus einem
eingebetteten Element würde eine ganze Programmseite unerreichbar machen.
Deshalb: Ist die konfigurierte Session unveröffentlicht oder gelöscht, liefert
das Element eine **leere `Response`** (kein Markup, kein 404) und taggt
weiterhin `contao.db.tl_qna_session.<id>`, damit eine spätere
Veröffentlichung den Seiten-Cache invalidiert. Das ist das Verhalten, das
Contao für unveröffentlichte Inhalte ohnehin zeigt. Der `auto_item`-Pfad
behält seinen 404 (D5) — dort ist die URL selbst die Behauptung, dass die
Session existiert.

**Backend-Ansicht** (`as_editor_view`): Der Hinweis
`qna.reader.editor_hint` nennt heute nur den Item-Parameter. Bei
konfigurierter Session zeigt die Editor-Ansicht stattdessen den Titel der
gewählten Session (`qna.reader.editor_hint_session`, Platzhalter `%s`). Dafür
lädt der Controller im Backend-Scope `sessionGateway->find($configured)` (nicht
`findPublished`, die Redaktion soll auch eine unveröffentlichte Auswahl sehen).

**Wechselwirkung mit `qna_session_list`:** Die Liste verlinkt auf `jumpTo`
**mit** Alias als Item. Zeigt sie auf eine Seite, deren Reader eine feste
Session hat, bleibt das Item unverbraucht und Contao antwortet mit 404
(`LegacyRouteParametersListener`, siehe DECISIONS „Item-Parameter"). Das ist
korrekt und wird im README dokumentiert: Die feste Session ist für Seiten
gedacht, die **nicht** Ziel der Liste sind.

### 1.4 Betroffene Dateien

| Datei | Änderung |
| --- | --- |
| `contao/dca/tl_content.php` | Feld `qnaSession`, Reader-Palette |
| `translations/contao_tl_content.{de,en}.php` | `tl_content.qnaSession.0/1` |
| `translations/contao_default.{de,en}.php` | `CTE.qna_session_reader.1` (Beschreibung), `qna.reader.editor_hint_session` |
| `src/Controller/ContentElement/QnaSessionReaderController.php` | `resolveSession(ContentModel)`, leere Response, Editor-Hinweis |
| `contao/templates/content_element/qna_session_reader.html.twig` | Editor-Hinweis mit Session-Titel |
| `tests/Unit/ContentElementDcaTest.php` | Erwartung umkehren: Palette enthält `qnaSession`, weiterhin kein `jumpTo` |
| `tests/Unit/QnaSessionReaderControllerTest.php` | neue Fälle (Abschnitt 1.5) |
| `README.md` | „Contao setup" Schritt 3, neuer Absatz zur festen Session, Hinweis zur Listen-Wechselwirkung |
| `.docs/build/SPEC.md` §5.2 | Absatz ersetzen |
| `.docs/build/DECISIONS.md` | D13 |

### 1.5 Pflicht-Testfälle

1. Konfigurierte, veröffentlichte Session wird verwendet; der `Input`-Adapter
   wird **nie** angefordert (`$framework->expects(self::never())->method('getAdapter')->with(Input::class)`
   bzw. der Fake meldet keinen Zugriff auf `auto_item`).
2. Konfigurierte Session **und** vorhandenes `auto_item`: das Item bleibt
   unverbraucht (der Fake meldet keinen Lesezugriff).
3. Konfigurierte, unveröffentlichte Session → leere Response mit Status 200,
   keine `PageNotFoundException`, Cache-Tag gesetzt.
4. Feld leer / `0` → bestehender Pfad, alle vorhandenen Tests bleiben grün.
5. Backend-Scope mit konfigurierter Session → Template erhält den Titel.
6. DCA-Test: Reader-Palette ist `{type_legend},type;{qna_legend},qnaSession`,
   `sql` des Feldes ist Doctrine-Schema mit `default => 0`.

---

## 2. Feature 2 — Neustart einer Fragerunde (Durchgänge)

### 2.1 Anforderung

Eine beendete Session kann von der Bühne aus erneut gestartet werden. Nach dem
Beenden erscheint für Operatoren ein Button „Neue Fragerunde starten". Die
Fragen des vorherigen Durchgangs erscheinen danach nicht mehr — weder in der
Bühne noch im Reader — bleiben aber in der Datenbank und im Backend erhalten.

Hintergrund: eine Session pro Bühne mit mehreren Programmpunkten, statt einer
Session pro Programmpunkt.

### 2.2 Heutiger Stand

* `SPEC.md` §3: `closed` ist final, `closed → open` wird abgewiesen.
  `SessionService::start()` (`src/Service/SessionService.php:24-43`)
  verlangt `WAITING`; `QnaSessionGateway::markOpen()` schreibt bedingt
  `WHERE state = 'waiting'`.
* `QnaQuestionGateway::LIST_SQL` filtert nur `q.pid = :sessionId`.
* `StageViewFactory::create()` kennt `showStartButton` (waiting) und
  `showStopButton` (open); `closed` hat keine Aktion.
* Die Bedingung `$view->showStartButton || $view->showStopButton` steht
  **dreimal** und steuert zwei sicherheitsrelevante Dinge: ob ein CSRF-Token
  gerendert wird und ob die Bühnenantwort geteilt gecacht werden darf
  (`src/Controller/QnaFrameController.php:83-93`,
  `src/Controller/QnaActionController.php:220-222`). Ein Button im Zustand
  `closed` **muss** in diese Bedingung aufgenommen werden, sonst würde eine
  Operator-Antwort mit Token und Formular öffentlich cachebar.
* `tests/Unit/SessionServiceTest.php:43` (`testClosedSessionCannotBeStartedAgain`)
  bleibt gültig: `start()` verlangt weiterhin `WAITING`; der Neustart ist eine
  **eigene** Operation.

### 2.3 Modellentscheidung D14 — Durchgangszähler statt Archiv-Flag

Zwei Varianten wurden erwogen:

| | A: `round`-Zähler | B: `archived`-Flag je Frage |
| --- | --- | --- |
| Neustart | `UPDATE tl_qna_session … round = round + 1` — eine Zeile | `UPDATE tl_qna_question SET archived = 1 WHERE pid = ?` — alle Fragen der Session unter der Session-Sperre |
| Lesepfad | `WHERE q.pid = :sessionId AND q.round = :round` | `WHERE q.pid = :sessionId AND q.archived = 0` |
| Backend | Frage bleibt ihrem Durchgang zugeordnet, filterbar | nur „alt/aktuell", Historie geht bei zweitem Neustart verloren |
| Konsistenz | Frage trägt `round` beim Insert aus der gesperrten Session-Zeile — kein nachträgliches Umschreiben | Massen-Update konkurriert mit laufenden Votes/Antwort-Markierungen |

**Entschieden: Variante A.** Der Neustart bleibt eine O(1)-Operation unter
derselben Session-Sperre wie Start und Stop, alte Fragen werden nicht
angefasst, und das Backend kann Durchgänge unterscheiden. „Deaktivieren" aus
der Anforderung ist damit ein Lesefilter, keine Schreiboperation.

Begriffe: Englisch `round` (Spalte, Code), Deutsch **„Durchgang"** in den
Übersetzungen — nicht „Runde", das kollidiert mit „Fragerunde" = Session.
`ROUND` ist in MySQL/MariaDB ein Funktionsname, **kein reserviertes Wort**;
die Spalte braucht kein Quoting. Vor dem Einsatz mit
`ddev exec vendor/bin/contao-console contao:migrate --dry-run` belegen.

### 2.4 Datenmodell

`tl_qna_session`:

| Feld | Typ | Anmerkung |
| --- | --- | --- |
| `round` | unsigned int, Default 1 | aktueller Durchgang; nur die Bühne erhöht ihn |

`tl_qna_question`:

| Feld | Typ | Anmerkung |
| --- | --- | --- |
| `round` | unsigned int, Default 1 | Durchgang der Session zum Zeitpunkt der Einreichung |

Indizes `tl_qna_question`: `pid,createdAt` wird zu **`pid,round,createdAt`**.
`pid`, `createdAt` und `pid,memberId,createdAt` bleiben. Nachweis per
`EXPLAIN` über `tests/Fixtures/explain-list.php` (an `round` anpassen).

**Keine eigene Migrationsklasse.** Beide Spalten haben Default 1; Contaos
Schema-Update (`contao:migrate`) legt sie an, Bestandsdaten sind damit
konsistent (Session Durchgang 1, alle bestehenden Fragen Durchgang 1).
`tests/Fixtures/create-schema.php` baut die Tabellen aus dem DCA und braucht
keine Anpassung.

**Cooldown bleibt durchgangsübergreifend.** `findLatestCreatedAt()` filtert
weiter nur `pid AND memberId`. Der Cooldown ist Missbrauchsschutz; ein Neustart
darf ihn nicht zurücksetzen. Das steht so in D14.

### 2.5 Statusmaschine (neu, ersetzt SPEC §3)

```
waiting --start--> open --stop--> closed --restart--> open (round + 1)
```

* Neue Session: `state = waiting`, `round = 1`
* Start: `state = open`, `startedAt = jetzt` (nur aus `waiting`)
* Ende: `state = closed`, `endedAt = jetzt` (nur aus `open`)
* Neustart: `state = open`, `startedAt = jetzt`, `endedAt = NULL`,
  `round = round + 1` (nur aus `closed`)
* `closed → open` über **`start()`** bleibt verboten; nur `restart()` darf es.

Alle Übergänge werden ausschließlich im `SessionService` validiert und mit
bedingtem `UPDATE … WHERE state = :expected` ausgeführt.

### 2.6 Zielbild je Schicht

**Domain**

* `Session`: neue Eigenschaft `public int $round = 1` als **letzter**
  Konstruktorparameter mit Default, damit die zahlreichen positionalen
  `new Session(7, 'Mobility', …)` in Tests kompilieren. Neue Methode
  `withRestart(int $timestamp): self` (open, `startedAt = $timestamp`,
  `endedAt = null`, `round + 1`).
* `Question`, `QuestionListItem`: `public int $round = 1`, ebenfalls am Ende.

**Gateway**

* `QnaSessionGateway`: `round` in allen `SELECT`s und in `hydrate()`. Neue
  Methode `markReopened(int $sessionId, int $timestamp): bool`:

  ```sql
  UPDATE tl_qna_session
  SET state = :newState, startedAt = :timestamp, endedAt = NULL,
      round = round + 1, tstamp = :timestamp
  WHERE id = :id AND state = :expectedState   -- 'closed'
  ```

* `QnaQuestionGateway`: `create()` erhält `int $round`; `find()` liest
  `round`; `LIST_SQL` erhält `AND q.round = :round`; `findForSession()` und
  `findForStage()` erhalten `int $round` direkt nach `$sessionId`. Der
  interpolierte Teil bleibt ausschließlich `QuestionSort::orderBySql()`.

**Service**

* `SessionService::restart(int $sessionId): Session` — analog zu `start()`:
  Session sperren, `assertPublished()`, `CLOSED` verlangen (sonst
  `InvalidSessionTransitionException($state, SessionState::OPEN)`),
  `markReopened()`, bei `false` erneut lesen und werfen, sonst
  `$session->withRestart($timestamp)`.
* `QuestionService::create()`: `$this->questionGateway->create($session->id,
  $memberId, $question, $timestamp, $session->round)`; die zurückgegebene
  `Question` trägt den `round`. Der Wert stammt aus der **gesperrten**
  Session-Zeile — ein gleichzeitiger Neustart ist damit serialisiert: gewinnt
  der Neustart, landet die Frage im neuen Durchgang, sonst im alten.
* `LockedContextLoader::lockOpenSessionWithQuestion()`: nach `assertOpen()`
  zusätzlich `if ($question->round !== $session->round) throw new
  QuestionArchivedException()`. Damit sind Votes **und** Antwort-Markierungen
  auf Fragen alter Durchgänge abgewiesen, auch von veralteten Seiten.
  Fehlerpräzedenz: Frage fehlt/fremd → Session fehlt → Session nicht offen →
  Frage archiviert.
* Neue Exception `QuestionArchivedException extends QnaDomainException`,
  422, Schlüssel `qna.error.question_archived`.

**View**

* `StageUrlSet`: neue Eigenschaft `restart`.
* `StageView`: neue Eigenschaft `showRestartButton`; neue Methode
  `hasControls(): bool` (`start || stop || restart`). Die drei bestehenden
  `showStartButton || showStopButton`-Stellen werden durch `hasControls()`
  ersetzt. **Das ist die sicherheitsrelevante Änderung dieses Features** —
  siehe 2.2.
* `StageViewFactory::create()`: `$showRestartButton = $canControl &&
  SessionState::CLOSED === $session->state`; URL für
  `contao_qna_session_restart` mit denselben `$routeParameters`.
  `answerUrls` bleiben an `showStopButton` (offene Session) gebunden.
* `ReaderViewFactory::createContext()`: `findForSession($session->id,
  $session->round, $memberId ?? 0)`.

**Controller**

* `QnaActionController`: neue Route

  ```php
  #[Route('/_qna/session/{sessionId}/restart', name: 'contao_qna_session_restart',
      requirements: ['sessionId' => '\\d+'], defaults: ['_token_check' => true], methods: ['POST'])]
  ```

  Ablauf identisch zu `start()`/`stop()`: `requireControl()`, Sort aus dem
  Query, `sessionService->restart()`, bei `QnaDomainException` → 404 oder
  `renderStage(...)` mit 422, sonst 303 auf `contao_qna_stage_questions`
  mit `sort`. Die drei nahezu gleichen Methoden dürfen auf eine private
  Hilfsmethode zusammengeführt werden, die den Service-Aufruf als Closure
  bekommt.

**Templates**

* `contao/templates/qna/stage_content.html.twig`: neuer Zweig
  `{% elseif show_restart_button %}` mit Formular auf `restart_url`, Button-ID
  `qna-session-{{ session.id }}-restart`, Text `qna.stage.restart`. Das
  Formular trägt `data-turbo-confirm` mit `qna.stage.restart_confirm`: Ein
  versehentlicher Klick blendet alle sichtbaren Fragen aus. Verifiziert:
  Turbo 8 wertet `data-turbo-confirm` in `FormSubmission.start()` für **alle**
  Turbo-Formularübermittlungen aus, auch in Frames und bei deaktiviertem Drive
  (`/home/dev/Kunden/contao/contao_0507/node_modules/@hotwired/turbo/dist/turbo.es2017-esm.js:1154-1161`).
* `stage_detail.html.twig` und `stage_overview.html.twig`: **optional** die
  Anzeige „Durchgang %s" bei `round > 1` (`qna.stage.round`). Wenn umgesetzt,
  dann nur aus dem View-Modell, nicht aus dem Gateway im Template.
* Reader: keine Templateänderung nötig. Das Controls-Stream-Ziel
  `:not([data-qna-state='…'])` ersetzt den Controls-Inhalt beim Wechsel
  `closed → open` von selbst; die Fragenliste des neuen Durchgangs ist beim
  nächsten Poll leer.

**Backend (DCA)**

* `tl_qna_session`: Feld `round` (nicht in der Palette, `eval` mit
  `rgxp => 'natural'`), erscheint in `show`.
* `tl_qna_question`: Feld `round` mit `filter => true`; in
  `list.sorting.headerFields` zusätzlich `round`; `list.label.fields` um
  `round` ergänzen, damit der Durchgang in der Liste sichtbar ist (z. B.
  `'fields' => ['question', 'round'], 'format' => '%s <span class="label-info">%s</span>'`
  mit `label_callback`-freier Darstellung — die konkrete Form entscheidet der
  Prompt).
* Übersetzungen `tl_qna_session.round.0/1`, `tl_qna_question.round.0/1`.

**Frontend-Texte** (`contao_default`, DE/EN):

| Schlüssel | DE | EN |
| --- | --- | --- |
| `qna.stage.restart` | Neue Fragerunde starten | Start a new round |
| `qna.stage.restart_confirm` | Die bisherigen Fragen werden ausgeblendet. Neue Fragerunde starten? | The current questions will be hidden. Start a new round? |
| `qna.error.question_archived` | Diese Frage gehört zu einem früheren Durchgang und kann nicht mehr bearbeitet werden. | This question belongs to an earlier round and can no longer be changed. |
| `qna.stage.round` (optional) | Durchgang %s | Round %s |

### 2.7 Caching und Polling

* `QnaFrameController::stage()`: `$hasControls = $view->hasControls()`. Eine
  geschlossene Session für einen Operator ist damit `private, no-store`; für
  cookie-freie Zuschauer bleibt sie eine Sekunde geteilt cachebar. Der
  Test `tests/Unit/QnaFrameCacheTest.php::testStageCacheDependsOnRenderedControls`
  bekommt den Fall `CLOSED + control = true → privat`.
* Polling: `PollingPolicy` unverändert. Nach einem Neustart erkennt der Reader
  den Zustandswechsel spätestens nach dem Idle-Intervall (Default 10 s), wie
  heute beim Start.

### 2.8 Betroffene Dateien

| Datei | Änderung |
| --- | --- |
| `contao/dca/tl_qna_session.php` | Feld `round` |
| `contao/dca/tl_qna_question.php` | Feld `round`, Index `pid,round,createdAt` statt `pid,createdAt`, `headerFields`, Label, Filter |
| `src/Domain/Session.php`, `Question.php`, `QuestionListItem.php` | `round`, `withRestart()` |
| `src/Gateway/QnaSessionGateway.php` | `round` lesen, `markReopened()` |
| `src/Gateway/QnaQuestionGateway.php` | `round` schreiben/lesen/filtern |
| `src/Gateway/LockedContextLoader.php` | Archiv-Prüfung |
| `src/Exception/QuestionArchivedException.php` | neu |
| `src/Service/SessionService.php` | `restart()` |
| `src/Service/QuestionService.php` | `round` beim Insert |
| `src/View/Model/StageView.php`, `StageUrlSet.php` | `showRestartButton`, `restart`, `hasControls()` |
| `src/View/StageViewFactory.php`, `ReaderViewFactory.php` | Button, URL, `round`-Filter |
| `src/Controller/QnaActionController.php` | Route `restart`, `hasControls()` |
| `src/Controller/QnaFrameController.php` | `hasControls()` |
| `contao/templates/qna/stage_content.html.twig` | Restart-Formular |
| `translations/contao_default.{de,en}.php`, `contao_tl_qna_session.*`, `contao_tl_qna_question.*` | Texte |
| `tests/Fixtures/explain-list.php` | `round` |
| `tests/Unit/*` (Abschnitt 2.9) | |
| `tests/Integration/QuestionAnswerDatabaseTest.php` | Nebenläufigkeit (Abschnitt 2.9) |
| `README.md` | Statusmaschine, neuer Abschnitt „Restarting a session", Kapazitätshinweis unverändert |
| `.docs/build/SPEC.md` §2.1, §2.2, §3, §6.3 | nachziehen |
| `.docs/build/DECISIONS.md` | D14 |

### 2.9 Pflicht-Testfälle

Unit:

1. `SessionServiceTest`: `restart()` aus `closed` → `open`, `round + 1`,
   `startedAt` gesetzt, `endedAt` null; aus `waiting`/`open` →
   `InvalidSessionTransitionException`, `markReopened` nie gerufen;
   unveröffentlicht → `SessionNotPublishedException`; `markReopened() === false`
   → erneutes `find()` und Exception. `testClosedSessionCannotBeStartedAgain`
   bleibt unverändert grün.
2. `QnaSessionGatewayTest`: `markReopened()`-SQL enthält `round = round + 1`,
   `endedAt = NULL` und den `state`-Guard auf `closed`; alle `SELECT`s lesen
   `round`.
3. `QnaQuestionGatewayTest`: Listenabfrage bindet `round`; `create()` schreibt
   `round`.
4. `LockedContextLoaderTest`: Frage mit abweichendem `round` →
   `QuestionArchivedException`, **nach** der Prüfung auf offene Session
   (Datenprovider um den Fall erweitern).
5. `VoteServiceTest`, `QuestionAnswerServiceTest`: archivierte Frage wird vor
   jedem Schreibzugriff abgewiesen.
6. `QuestionServiceTest`: Insert erhält den `round` der gesperrten Session.
7. `QnaActionControllerTest`: Restart prüft Voter, ruft den Service, antwortet
   303 privat mit `sort`; ungültiger Restart (Session offen) → 422 mit
   Bühnen-Frame; fehlende Berechtigung → `AccessDeniedHttpException` vor jeder
   Mutation.
8. `QnaFrameCacheTest`: `CLOSED + control` → `private, no-store` mit Token;
   `CLOSED` ohne control → weiterhin `public, …, s-maxage=1` ohne Token.
9. `QnaTemplateStructureTest`: Restart-Formular nur bei
   `show_restart_button`; `data-turbo-confirm` vorhanden; niemals Start- und
   Restart-Formular gleichzeitig.
10. `DcaConfigurationTest`: Default `1` beider `round`-Felder, Index
    `pid,round,createdAt`, `round` nicht in der Session-Palette.

Integration (`QuestionAnswerDatabaseTest`, gleiche Zwei-Prozess-Technik wie
`testStartCoordinatesWithSubmission`):

11. Neustart vs. Einreichung in beiden Reihenfolgen: Die Frage trägt genau den
    `round`, der zum Commit-Zeitpunkt galt; die Liste des neuen Durchgangs
    zeigt sie nur, wenn sie danach eingereicht wurde.
12. Neustart vs. Vote: Vote auf eine Frage des alten Durchgangs nach dem
    Neustart → `QuestionArchivedException`; kein Zähler verändert.
13. Neustart vs. Antwort-Markierung: analog zu 12.
14. Zweifacher Neustart: `round` 1 → 2 → 3, Fragenlisten je Durchgang korrekt,
    Backend-Filter (`WHERE pid = ? AND round = ?`) liefert die alten Fragen
    weiterhin.
15. `EXPLAIN` der Listenabfrage mit `round`-Filter nutzt den neuen Index
    (Ausgabe im Bericht oder **nicht verifiziert**).

---

## 3. Feature 3 — Backend-Hinweis bei fehlendem Turbo-Entry

### 3.1 Anforderung

Im Backend erscheint ein Hinweis über `Contao\Message`, wenn keiner der beiden
Turbo-Entries (`huh_ux_turbo_encore`, `huh_ux_turbo_encore_no_drive`) im
Seitenlayout oder in der Seitenstruktur aktiviert ist. Ohne Turbo laden die
Q&A-Frames nicht; heute meldet das nur die Browser-Konsole (README,
„Requirements").

### 3.2 Belegte Fakten (Encore-Bundle, aus dem DDEV-Projekt)

Pfad-Präfix: `/home/dev/Kunden/contao/contao_0507/vendor/heimrichhannot/contao-encore-bundle/`

| Fakt | Beleg |
| --- | --- |
| Feldname `encoreEntries` auf `tl_layout` und `tl_page` | `src/Dca/EncoreEntriesSelectField.php:7` (`NAME_DEFAULT`), `contao/dca/tl_layout.php`, `contao/dca/tl_page.php` |
| Spaltentyp `blob NULL`, Inhalt serialisiertes Array von Zeilen `['active' => '1'\|'', 'entry' => '<name>']`; `active` existiert nur mit `setIncludeActiveCheckbox(true)` — für beide Tabellen gesetzt | `src/EventListener/DcaField/EncoreEntriesSelectFieldListener.php:22-72`, beide DCA-Dateien |
| Aktiv-Semantik: Zeile zählt, wenn `active` fehlt **oder** truthy ist | `src/Asset/PageEntrypoints.php:53-55` |
| Sammelreihenfolge: Layout, dann Elternseiten rekursiv über `pid`, dann die Seite selbst; Deserialisierung mit `StringUtil::deserialize($value, true)` | `src/Asset/PageEntrypoints.php:80-110` |
| Das Layout muss `addEncore` gesetzt haben, sonst wird Encore für die Seite nicht aktiviert | `src/Helper/ConfigurationHelper.php:74`, `src/DataContainer/LayoutContainer.php:44` |
| Entry-Namen | `vendor/heimrichhannot/contao-ux-turbo-encore/src/EncoreExtension.php:10-11` (**in diesem Repository** vorhanden) |

Core-APIs (in diesem Repository verifiziert):

| API | Beleg |
| --- | --- |
| `Message::addInfo($message)`, `addError()` — statisch, im Core über `$framework->getAdapter(Message::class)->addInfo()` aufgerufen | `vendor/contao/core-bundle/contao/library/Contao/Message.php:67`, Verwendung `vendor/contao/core-bundle/src/EventListener/DataContainer/LegacyTemplatesListener.php:46` |
| `#[AsCallback(table: …, target: 'config.onload')]` | `vendor/contao/core-bundle/src/EventListener/DataContainer/LegacyTemplatesListener.php:30`, `ContentElementViewListener.php:36` |
| `act` aus dem Request lesen | `vendor/contao/core-bundle/src/EventListener/DataContainer/PreviewLinkListener.php:85,113` |
| `DataContainer::getCurrentRecord()` | `vendor/contao/core-bundle/contao/classes/DataContainer.php:1446`, `drivers/DC_Table.php:280` |
| `PageModel::findWithDetails()`, `loadDetails()` setzt geerbtes `layout` und `trail` | `vendor/contao/core-bundle/contao/models/PageModel.php:877,921,931,1001-1003` |
| `Model::findById()`, `StringUtil::deserialize()` | `contao/library/Contao/Model.php:954`, `contao/library/Contao/StringUtil.php:1037` |

### 3.3 Zielbild

**Ein Prüfservice** `src/Asset/TurboEntryAvailability.php` (`final readonly`,
neben `EncoreExtension`):

* `isActiveForPage(PageModel $page): bool` — `$page->loadDetails()`; sammelt
  die `encoreEntries`-Blobs des Layouts (`LayoutModel::findById($page->layout)`,
  nur wenn `addEncore` truthy), aller Seiten aus `$page->trail` und der Seite
  selbst; gibt `true` zurück, sobald eine aktive Zeile einen der beiden
  Entry-Namen trägt. Fehlt die Spalte (Encore-Bundle nicht installiert), ist
  das Ergebnis `false` — ohne Encore-Bundle kann Turbo auf diesem Weg nicht
  bereitstehen.
* `isActiveAnywhere(): bool` — DBAL-Abfrage über
  `SELECT encoreEntries FROM tl_layout WHERE addEncore = '1'` und
  `SELECT encoreEntries FROM tl_page WHERE encoreEntries IS NOT NULL`, gleiche
  Zeilenprüfung. Spaltenexistenz vorab einmal über den DBAL-Schema-Manager
  prüfen (`listTableColumns`), damit ein Projekt ohne Encore-Bundle keinen
  SQL-Fehler im Backend sieht.
* Die Zeilenprüfung ist **eine** private Methode, die beide öffentlichen
  Methoden nutzen. Die Entry-Namen kommen aus den Konstanten des
  ux-turbo-Pakets, nicht aus Literalen.

**Ein Meldungsservice** `src/Asset/TurboHintMessenger.php` mit
`warn(?PageModel $page): void`: ruft je nach Argument `isActiveForPage()` oder
`isActiveAnywhere()` und fügt bei `false` über
`$framework->getAdapter(Message::class)->addInfo()` die übersetzte Meldung
ein — **höchstens einmal pro Request**. Dafür ist die Klasse `final`, aber
nicht `readonly`, und merkt sich den ersten Aufruf in `private bool $warned`;
Symfony baut den Service je Request neu auf, ein Request-Guard genügt. Zwei
Texte:

| Schlüssel | Wann | DE |
| --- | --- | --- |
| `qna.backend.turbo_missing_page` | Seite bekannt | Auf dieser Seite ist kein Turbo-Entry aktiv. Aktivieren Sie „huh_ux_turbo_encore" oder „huh_ux_turbo_encore_no_drive" im Seitenlayout oder in der Seitenstruktur, sonst werden die Q&A-Elemente nicht geladen. |
| `qna.backend.turbo_missing_global` | keine Seite bestimmbar | In keinem Seitenlayout und auf keiner Seite ist ein Turbo-Entry aktiv. Aktivieren Sie „huh_ux_turbo_encore" oder „huh_ux_turbo_encore_no_drive", sonst werden die Q&A-Elemente nicht geladen. |

Englische Fassungen sinngemäß. Meldungstyp `addInfo` gemäß Anforderung
(„Hinweis"). Wer den Hinweis als Fehler darstellen will, tauscht nur die
Methode — das bleibt eine Zeile.

**Drei Listener** (je eine Klasse pro Callback, `AGENTS.md`):

| Klasse | Tabelle | Bedingung | Seite |
| --- | --- | --- | --- |
| `EventListener/DataContainer/Content/ConfigOnloadListener` | `tl_content` | `act === 'edit'`, `type` ∈ {`qna_session_list`, `qna_session_reader`} | `ptable === 'tl_article'` → `ArticleModel::findById(pid)` → `PageModel::findWithDetails(article->pid)`; sonst `null` (globale Prüfung) |
| `EventListener/DataContainer/Page/ConfigOnloadListener` | `tl_page` | `act === 'edit'`, `type === 'qna_stage'` | `PageModel::findWithDetails(id)` |
| `EventListener/DataContainer/Session/ConfigOnloadListener` | `tl_qna_session` | immer (Einstieg ins Q&A-Modul) | `null` (globale Prüfung) |

Datensatz über `$dc->getCurrentRecord()`; `act` über den `RequestStack`
(Core-Vorbild `PreviewLinkListener`). Alle Contao-Klassen über
`ContaoFramework::getAdapter()`, damit die Listener ohne Framework-Bootstrap
testbar bleiben (Vorbild `tests/Unit/QnaSessionReaderControllerTest.php`,
`ReaderInputAdapter`).

**Nicht** geprüft wird beim Speichern (`onsubmit`) oder in Listenansichten von
Artikeln — der Hinweis erscheint dort, wo die Redaktion das Q&A-Element oder
die Bühnenseite bearbeitet, und beim Betreten des Q&A-Moduls. Das ist
ausreichend und hält die Zahl der Datenbankabfragen im Backend klein.

### 3.4 Betroffene Dateien

| Datei | Änderung |
| --- | --- |
| `src/Asset/TurboEntryAvailability.php` | neu |
| `src/Asset/TurboHintMessenger.php` | neu |
| `src/EventListener/DataContainer/Content/ConfigOnloadListener.php` | neu |
| `src/EventListener/DataContainer/Page/ConfigOnloadListener.php` | neu |
| `src/EventListener/DataContainer/Session/ConfigOnloadListener.php` | neu |
| `translations/contao_default.{de,en}.php` | zwei Schlüssel |
| `tests/Unit/TurboEntryAvailabilityTest.php` | neu |
| `tests/Unit/TurboHintListenerTest.php` | neu (alle drei Listener) |
| `README.md` | Absatz unter „Requirements": Hinweis im Backend |
| `.docs/build/DECISIONS.md` | D15 mit den Belegen aus 3.2 |

### 3.5 Pflicht-Testfälle

1. `TurboEntryAvailability`: aktive Zeile mit `huh_ux_turbo_encore` im Layout
   → `true`; nur in einer Elternseite → `true`; nur auf der Seite selbst →
   `true`; Zeile vorhanden, aber `active === ''` → `false`; Zeile ohne
   `active`-Schlüssel → `true`; anderer Entry-Name (`huh_qna`) → `false`;
   Layout mit Entry, aber `addEncore` leer → `false`; leerer/NULL-Blob →
   `false`; Spalte fehlt → `false` ohne Exception.
2. `isActiveAnywhere()`: Treffer in `tl_layout` bzw. `tl_page`; kein Treffer;
   fehlende Spalte.
3. Listener `tl_content`: `act=edit` + Q&A-Typ → Meldung genau einmal; anderer
   Typ → keine Meldung, keine Modellabfragen; `act` leer → keine Meldung;
   `ptable !== 'tl_article'` → globale Prüfung.
4. Listener `tl_page`: nur `qna_stage` mit `act=edit`.
5. Listener `tl_qna_session`: immer globale Prüfung; bei aktivem Entry keine
   Meldung.
6. Manuelle Verifikation im DDEV-Projekt (Bericht mit Screenshot-Beschreibung
   oder **nicht verifiziert**): Q&A-Element auf einer Seite ohne Turbo-Entry
   öffnen → Hinweis; Entry im Layout aktivieren → Hinweis verschwindet.

---

## 4. Dokumentationspflichten (alle Features)

* `README.md` ist die Nutzer-Dokumentation und wird je Feature ergänzt
  (Abschnitte 1.4, 2.8, 3.4). Englisch, im bestehenden Stil.
* `.docs/build/SPEC.md` wird an den genannten Stellen **geändert**, nicht
  kommentiert — sie bleibt die fachliche Quelle. Ein Hinweis „ergänzt durch
  FEATURES.md §n" am geänderten Absatz genügt.
* `.docs/build/DECISIONS.md` erhält D13 (Feature 1), D14 (Feature 2), D15
  (Feature 3) im Stil von D8–D12: Entscheidung, Alternativen, Belegpfade.

## 5. Berichtsformat je Feature

Der Abschlussbericht eines Prompts enthält:

1. Liste der geänderten/neuen Dateien mit einem Satz je Datei.
2. Tatsächliche Kommandozeilen und reale (gekürzte) Ausgaben aller Checks aus
   Abschnitt 0.6 bzw. 0.7.
3. Abweichungen vom Zielbild mit Begründung — insbesondere, wenn eine hier
   angenommene API im `vendor/` anders aussieht.
4. Offene Punkte, die bewusst nicht umgesetzt wurden.
