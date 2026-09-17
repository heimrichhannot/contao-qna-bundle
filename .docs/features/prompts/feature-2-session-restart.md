# Feature 2 — Neustart einer Fragerunde (Durchgänge)

Lies zuerst `.docs/features/FEATURES.md` §0 vollständig, dann §2 **komplett**
— insbesondere die Modellentscheidung D14 (§2.3) und den Sicherheitshinweis
zu `hasControls()` (§2.2, §2.7). Lies außerdem `AGENTS.md`, `AGENTS.local.md`
und den README-Abschnitt „Transactions and locking".

**Verhaltensändernd:** ja — Statusmaschine, Schema, Bühne.
**Voraussetzung:** Feature 1 und Feature 3 sind committet, alle Checks aus
§0.6 grün, Integrationssuite (§0.7) läuft auf dem Ausgangsstand grün.
**Branch:** `feature/session-restart`

Dieses Feature ist das größte der drei. Arbeite die Schichten **von innen
nach außen** ab (Schema → Domain → Gateway → Service → View → Controller →
Template → Backend → Doku) und lass nach jeder Schicht PHPStan laufen. Ein
Zwischen-Commit je Schicht ist erwünscht.

---

## Ziel

Eine geschlossene Session lässt sich von der Bühne aus neu starten. Der
Zähler `round` der Session steigt um eins; Fragen tragen den Durchgang, in dem
sie eingereicht wurden; Reader und Bühne zeigen nur den aktuellen Durchgang.
Votes und Antwort-Markierungen auf Fragen alter Durchgänge werden abgewiesen.

## Aufgabe

### 1. Schema (DCA)

`FEATURES.md` §2.4.

* `contao/dca/tl_qna_session.php`: Feld `round`, unsigned int, Default 1,
  nicht in der Palette. `eval => ['rgxp' => 'natural']`.
* `contao/dca/tl_qna_question.php`: Feld `round`, unsigned int, Default 1,
  `filter => true`. `config.sql.keys`: `'pid,createdAt'` **ersetzen** durch
  `'pid,round,createdAt'`. `list.sorting.headerFields` um `round` ergänzen.
  Den Durchgang in `list.label` sichtbar machen — entscheide zwischen
  `fields => ['question', 'round']` mit passendem `format` oder einem
  `label_callback` als `#[AsCallback(table: 'tl_qna_question', target: 'list.label.label')]`-Listener
  in `src/EventListener/DataContainer/Question/`. Kein Callback im DCA-Array.
* Übersetzungen `tl_qna_session.round.0/1` und `tl_qna_question.round.0/1`
  (DE „Durchgang", EN „Round") in beiden Sprachen.
* `tests/Unit/DcaConfigurationTest.php`: Default 1 beider Felder, neuer Index,
  `round` nicht in der Session-Palette.

Danach im DDEV-Projekt:

```bash
ddev exec vendor/bin/contao-console contao:migrate --dry-run
```

Die Ausgabe muss genau zwei `ALTER TABLE … ADD round …` und die Indexänderung
zeigen. Bestätige, dass `round` **ohne** Quoting akzeptiert wird (kein
reserviertes Wort). Dann `contao:migrate --no-interaction`.

### 2. Domain

`FEATURES.md` §2.6 „Domain". `round` immer als **letzter** Parameter mit
Default `1`, damit positionale Konstruktoraufrufe in Tests kompilieren.
`Session::withRestart(int $timestamp): self`. Prüfe mit
`vendor/bin/phpstan analyse --no-progress`, dass keine Aufrufstelle bricht.

### 3. Gateways

`FEATURES.md` §2.6 „Gateway".

* `QnaSessionGateway`: `round` in den vier `SELECT`s und in `hydrate()`;
  `markReopened()` mit dem SQL aus §2.6 und `expectedState = 'closed'`.
* `QnaQuestionGateway`: `create(int $sessionId, int $memberId, string $question,
  int $createdAt, int $round): int`; `find()` liest `round`;
  `LIST_SQL` bekommt `AND q.round = :round`; `findForSession(int $sessionId,
  int $round, int $memberId, QuestionSort $sort = …)` und
  `findForStage(int $sessionId, int $round, QuestionSort $sort = …)`.
  `findLatestCreatedAt()` bleibt **unverändert** (Cooldown ist
  durchgangsübergreifend, §2.4).
* `tests/Fixtures/explain-list.php` an die neue Signatur anpassen (`round = 1`
  binden) und `EXPLAIN` gegen die Datenbank ausführen; belegen, dass der Index
  `pid,round,createdAt` genutzt wird.
* Tests: `QnaSessionGatewayTest`, `QnaQuestionGatewayTest` gemäß §2.9 Nr. 2–3.

### 4. Services und Sperrkontext

`FEATURES.md` §2.6 „Service".

* `src/Exception/QuestionArchivedException.php` (422,
  `qna.error.question_archived`).
* `LockedContextLoader::lockOpenSessionWithQuestion()`: Archiv-Prüfung
  **nach** `assertOpen()`. Ergänze den Klassen-Docblock um die neue
  Präzedenzregel. Test-Datenprovider in `LockedContextLoaderTest` erweitern.
* `SessionService::restart()`. Halte dich an die Struktur von `start()`,
  einschließlich des erneuten `find()` bei fehlgeschlagenem bedingtem Update.
  `start()` und `stop()` bleiben unverändert — `testClosedSessionCannotBeStartedAgain`
  muss ohne Anpassung grün bleiben.
* `QuestionService::create()`: `round` aus der **gesperrten** Session an
  `create()` übergeben und in die zurückgegebene `Question` schreiben.
* Tests: `SessionServiceTest`, `QuestionServiceTest`, `VoteServiceTest`,
  `QuestionAnswerServiceTest` gemäß §2.9 Nr. 1, 4–6.

### 5. View

`FEATURES.md` §2.6 „View".

* `StageUrlSet::$restart`, `StageView::$showRestartButton`,
  `StageView::hasControls()`. Ergänze das Array-Shape in
  `StageView::templateContext()` um `show_restart_button` und `restart_url`.
* `StageViewFactory::create()`: Button-Bedingung und Restart-URL.
* `ReaderViewFactory::createContext()`: `round` an `findForSession()`.
* Ersetze **alle** Vorkommen von `showStartButton || showStopButton` durch
  `hasControls()`. Nachweis: `grep -rn "showStopButton" src/` liefert danach
  nur noch `StageView` und `StageViewFactory`.

### 6. Controller

`FEATURES.md` §2.6 „Controller" und §2.7.

* `QnaActionController::restart()` mit Route `contao_qna_session_restart`.
  Wenn du `start()`, `stop()` und `restart()` auf eine private Hilfsmethode
  zusammenführst, müssen die Routen-Attribute an den drei öffentlichen
  Methoden bleiben und die bestehenden Tests unverändert grün sein.
* `QnaFrameController::stage()`: `$hasControls = $view->hasControls()`.
* Tests: `QnaActionControllerTest` und `QnaFrameCacheTest` gemäß §2.9
  Nr. 7–8. Der Cache-Test ist der wichtigste dieses Features: `CLOSED` mit
  Steuerrecht darf **nie** `public` sein.

### 7. Template und Texte

`FEATURES.md` §2.6 „Templates" und „Frontend-Texte".

* `stage_content.html.twig`: `{% elseif show_restart_button %}`-Zweig,
  `data-turbo-confirm`. Verifiziere die Turbo-Stelle aus §2.6 im
  `node_modules`-Pfad des DDEV-Projekts, bevor du das Attribut setzt; ist sie
  nicht auffindbar, lass das Attribut weg und vermerke es im Bericht.
* Übersetzungen `qna.stage.restart`, `qna.stage.restart_confirm`,
  `qna.error.question_archived` in DE und EN. `qna.stage.round` nur, wenn du
  die optionale Anzeige umsetzt.
* `QnaTemplateStructureTest` gemäß §2.9 Nr. 9. Der Test rendert die echten
  Templates über `tests/Fixtures/TemplateEnvironment.php`.
* `assets/js/qna.js` braucht keine Änderung; der Restart-Button liegt in der
  Bühne innerhalb des pollenden Frames und wird wie Start/Stop behandelt.
  Prüfe das im Browser (Schritt 9).

### 8. Integrationstests

`tests/Integration/QuestionAnswerDatabaseTest.php` gemäß §2.9 Nr. 11–14.
Nutze die vorhandene Zwei-Prozess-Technik (`testStartCoordinatesWithSubmission`,
`testClosureCoordinatesWithEveryWrite`) und die vorhandenen Fixture-Helfer.
Die Suite muss mit `--fail-on-skipped` grün sein.

### 9. DDEV-Verifikation im Browser

1. Session anlegen, veröffentlichen, auf der Bühne starten, zwei Fragen aus
   dem Reader stellen, eine als beantwortet markieren, Session beenden.
2. Bühne zeigt den Button „Neue Fragerunde starten"; Klick zeigt die
   Bestätigung; nach Bestätigen: Status „läuft", leere Fragenliste.
3. Reader (zweiter Tab, weiter offen gelassen) zeigt spätestens nach dem
   Idle-Intervall Status „geöffnet", Formular und leere Liste.
4. Mit einem veralteten Reader-Tab (vor dem Neustart geladen) auf eine alte
   Frage voten → 422 mit `qna.error.question_archived` im Fragen-Frame.
5. Backend: Fragenliste der Session zeigt beide Durchgänge, Filter nach
   `round` funktioniert; Session-Detail (`show`) zeigt `round = 2`.

### 10. Dokumentation

* `README.md`: Abschnitt „Technical architecture" (Tabellenbeschreibung um
  `round`), neuer Abschnitt „Restarting a session" nach „Answered questions",
  Statusmaschine in „Transactions and locking" um `restart` ergänzen,
  Fehlerliste um „archived question".
* `.docs/build/SPEC.md`: §2.1 und §2.2 (Felder, Index), §3 (Statusmaschine,
  Text aus `FEATURES.md` §2.5), §6.3 (`closed`: Button „Neue Fragerunde
  starten" für Operatoren).
* `.docs/build/DECISIONS.md`: D14 (Inhalt aus `FEATURES.md` §2.3 und §2.4,
  plus Belege: DDL-Ausgabe von `contao:migrate --dry-run`, `EXPLAIN`).

## Nicht-Ziele

* Kein Zurücksetzen des Cooldowns beim Neustart.
* Kein Löschen, Verschieben oder Anonymisieren alter Fragen.
* Keine Anzeige alter Durchgänge im Frontend (Reader oder Bühne).
* Keine Änderung an `PollingPolicy`, Frame-IDs oder Stream-Zielen.
* Keine eigene Migrationsklasse — die DCA-Defaults genügen (§2.4).
* Kein Umbau von `start()`: `closed → open` über `start()` bleibt verboten.

## Akzeptanzkriterien

1. `contao:migrate --dry-run` auf dem migrierten Stand zeigt keine offenen
   Änderungen.
2. `grep -rn "showStartButton || \$view->showStopButton\|showStartButton || showStopButton" src/`
   liefert nichts; `hasControls()` ist die einzige Quelle.
3. `QnaFrameCacheTest` deckt `CLOSED` mit und ohne Steuerrecht ab.
4. Alle Integrationstests aus §2.9 Nr. 11–14 existieren und laufen mit
   `--fail-on-skipped` grün.
5. Die bestehenden Unit-Tests sind — bis auf hinzugefügte Fälle und die
   erweiterten Konstruktor-/Signaturaufrufe — inhaltlich unverändert.
6. README, SPEC (§2.1, §2.2, §3, §6.3) und DECISIONS D14 sind nachgezogen.

## Checks

```bash
vendor/bin/php-cs-fixer check --diff --sequential
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpunit --testsuite 'Q&A Bundle'
```

```bash
cd /home/dev/Kunden/contao/contao_0507
ddev exec vendor/bin/contao-console cache:clear
ddev exec vendor/bin/contao-console contao:migrate --dry-run
ddev exec -d /var/www/html/vendor/heimrichhannot/contao-qna-bundle env \
  QNA_DATABASE_TESTS=1 \
  QNA_TEST_OBSERVER_USER=root QNA_TEST_OBSERVER_PASSWORD=root \
  vendor/bin/phpunit --testsuite Integration --fail-on-skipped
```

## Bericht

Nach `FEATURES.md` §5. Zusätzlich:

* die DDL aus `contao:migrate --dry-run` im Wortlaut,
* `EXPLAIN` der Listenabfrage mit `round`-Filter,
* das SQL von `markReopened()` im Wortlaut,
* die Liste der Stellen, an denen `hasControls()` die alte Bedingung ersetzt,
* Ergebnis der fünf Browser-Schritte aus Aufgabe 9.
