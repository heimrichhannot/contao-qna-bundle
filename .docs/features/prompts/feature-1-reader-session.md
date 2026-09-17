# Feature 1 — Reader mit fest konfigurierter Session

Lies zuerst `.docs/features/FEATURES.md` §0 vollständig, dann §1. Die
dortigen Regeln und das Zielbild gelten für diesen Prompt und werden hier
nicht wiederholt. Lies außerdem `AGENTS.md` und `AGENTS.local.md`.

**Verhaltensändernd:** ja — dokumentiert in `FEATURES.md` §1.3 (D13).
**Voraussetzung:** sauberer `main`, alle drei Checks aus §0.6 grün.
**Branch:** `feature/reader-fixed-session`

---

## Ziel

Das Inhaltselement `qna_session_reader` kann eine Fragerunde fest zugewiesen
bekommen. Dann wird diese Session verwendet und der URL-Item-Parameter bleibt
unberührt. Ohne Auswahl verhält sich das Element wie heute.

## Aufgabe

Arbeite die Schritte in dieser Reihenfolge ab. Verifiziere vor Schritt 1 die
genannten Core-Stellen im `vendor/`-Verzeichnis (§0.2).

### 1. DCA

`contao/dca/tl_content.php` gemäß `FEATURES.md` §1.3 „DCA". Prüfe vorher im
Core:

* `vendor/contao/core-bundle/contao/dca/tl_content.php` — das `form`-Feld als
  Vorbild für `select` + `foreignKey` + `includeBlankOption` + `chosen`.
* Ob `foreignKey` mit `CONCAT(...)`-Ausdruck im Core verwendet wird (`tl_member`
  in `tl_content.php`/`tl_module.php`). Wenn ja, darfst du Titel und Alias
  anzeigen; wenn nicht auffindbar, bleibt es bei `tl_qna_session.title`.

Übersetzungen `tl_content.qnaSession.0/1` in
`translations/contao_tl_content.de.php` **und** `.en.php`:

* DE: „Fragerunde" / „Wählen Sie eine Fragerunde, die fest angezeigt werden
  soll. Ohne Auswahl wird die Fragerunde aus dem Item-Parameter der URL
  geladen."
* EN: „Question session" / „Select a session to display permanently. Without a
  selection the session is resolved from the URL item parameter."

Passe `CTE.qna_session_reader.1` in `contao_default.{de,en}.php` an, so dass
beide Wege genannt sind.

### 2. Controller

`src/Controller/ContentElement/QnaSessionReaderController.php`:

* `resolveSession()` bekommt das `ContentModel` als Parameter (Signatur
  `protected function resolveSession(ContentModel $model): Session`).
  Die Methode bleibt `protected`, weil der Test sie über eine Unterklasse
  aufruft.
* Lies `qnaSession` aus `$model->row()` mit derselben defensiven Typprüfung
  wie `QnaSessionListController::getResponse()` für `jumpTo`
  (`src/Controller/ContentElement/QnaSessionListController.php:37-41`).
  PHPStan `level: max` erlaubt kein ungeprüftes `(int)` auf `mixed`.
* Konfigurierter Wert `> 0`: `findPublished()`. **Kein** Zugriff auf den
  `Input`-Adapter in diesem Pfad. Ergebnis `null` → leere `Response` gemäß D13.
  Der Cache-Tag `contao.db.tl_qna_session.<id>` wird auch in diesem Fall
  gesetzt.
* Wert `0`: bestehender `auto_item`-Pfad, Zeile für Zeile unverändert.
* Backend-Scope: Bei konfigurierter Session lade `find($configured)` (nicht
  `findPublished`) und übergib den Titel als `editor_session_title` ans
  Template; sonst `null`.

Die leere Response ist eine `Response('')` mit Status 200 — nicht das
Fragment-Template mit leerem `view`. Begründe im Code in einem Satz, warum
kein 404 (Verweis auf D13).

### 3. Template

`contao/templates/content_element/qna_session_reader.html.twig`: Im
`as_editor_view`-Zweig `qna.reader.editor_hint_session` mit dem Titel
ausgeben, wenn `editor_session_title` gesetzt ist, sonst der bestehende
Hinweis. Übersetzung in beiden Sprachen (DE: „Zeigt die Fragerunde „%s“.",
EN: „Displays the question session “%s”."). Positionsplatzhalter `%s`, siehe
`tests/Unit/QnaTemplateStructureTest.php::testParameterizedContaoTranslationsUsePositionalPlaceholders`.

### 4. Tests

* `tests/Unit/ContentElementDcaTest.php`: Den Test
  `testListOnlyConfiguresTheReaderPageAndReaderHasNoSessionSelection`
  umbenennen (z. B. `testReaderOffersAnOptionalSessionAndNoReaderPage`) und
  die Erwartung umkehren: Palette exakt
  `{type_legend},type;{qna_legend},qnaSession`, weiterhin kein `jumpTo`.
  Zusätzlich: Feld `qnaSession` hat `sql.type === 'integer'`, `default === 0`,
  `eval.includeBlankOption === true`.
* `tests/Unit/QnaSessionReaderControllerTest.php`: `TestableQnaSessionReaderController::resolveForTest()`
  erhält ein `ContentModel`-Stub mit gesetztem `row()`. Neue Fälle laut
  `FEATURES.md` §1.5 Nr. 1–5. Die bestehenden vier Fälle bleiben
  **unverändert** grün (nur der Aufruf bekommt ein Model mit `qnaSession = 0`).
  Für Nr. 1 und 2 muss der Test **beweisen**, dass `Input` nicht angefasst
  wurde — `$framework->expects(self::never())->method('getAdapter')` ist die
  einfachste Form, wenn im konfigurierten Pfad kein anderer Adapter gebraucht
  wird; sonst den `Input`-Fake um ein `accessed`-Flag erweitern.
* Falls `tests/Unit/QnaTemplateStructureTest.php` den Editor-Hinweis rendert,
  den neuen Zweig ergänzen.

### 5. Dokumentation

* `README.md`: „Contao setup" Schritt 3 um die feste Zuweisung ergänzen; neuer
  kurzer Absatz „Embedding a session in an existing page" mit dem Hinweis zur
  Wechselwirkung mit der Liste (`FEATURES.md` §1.3 letzter Absatz) und dem
  Verhalten bei unveröffentlichter Session (leere Ausgabe statt 404).
* `.docs/build/SPEC.md` §5.2: den Absatz „Keine Session-Auswahl im Backend"
  durch die neue Regel ersetzen; 404-Regel auf den Item-Pfad einschränken.
* `.docs/build/DECISIONS.md`: D13 im Stil von D8–D12.

### 6. DDEV-Verifikation

Im DDEV-Projekt (§0.7):

1. `cache:clear`, `contao:migrate --dry-run` (zeigt die neue Spalte
   `tl_content.qnaSession`), dann `contao:migrate --no-interaction`.
2. Auf einer regulären Seite ohne Item-Parameter ein Reader-Element mit fester
   Session anlegen und die Seite im Frontend aufrufen: Reader lädt, Frames
   erscheinen.
3. Die Session im Backend entveröffentlichen, Seite erneut aufrufen: Seite
   antwortet 200, Element ist leer.
4. Ein Reader-Element ohne Auswahl auf der bisherigen Reader-Seite mit
   Item-URL aufrufen: unverändert.

Ergebnis im Bericht dokumentieren (§0.3).

## Nicht-Ziele

* Keine Änderung an `qna_session_list`.
* Keine Auswahl mehrerer Sessions, keine Session-Gruppen.
* Kein Fallback „konfiguriert, aber nicht gefunden → Item-Parameter".
* Keine Änderung an Frame-Routen, Polling oder Caching.

## Akzeptanzkriterien

1. Reader mit `qnaSession > 0` liest `auto_item` nicht (Unit-Test beweist es).
2. Reader mit `qnaSession = 0` verhält sich byte-identisch zu heute; die
   bestehenden Tests sind inhaltlich unverändert.
3. Unveröffentlichte konfigurierte Session → 200 mit leerem Element.
4. `grep -rn "qnaSession" src contao translations tests` findet DCA, Controller,
   Übersetzungen (DE + EN) und Tests.
5. README, SPEC §5.2 und DECISIONS D13 sind nachgezogen.

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
```

## Bericht

Nach `FEATURES.md` §5. Zusätzlich: die finale Palette und das Feld-Array im
Wortlaut, sowie das Ergebnis der vier DDEV-Schritte.
