# Phase 5 — Testnetz und Lesepfad

Lies zuerst `.docs/refactor/REFACTOR.md` §0 vollständig. Die dortigen Regeln
gelten für diese Phase und werden hier nicht wiederholt.

**Befunde dieser Phase:** B12, B18, B13, B14
**Verhaltensneutral:** nein — B13 und B14 ändern Schema bzw. Cache-Verhalten
**Voraussetzung:** Phasen 1-4 sind abgeschlossen und committet.

---

## Ziel

Erst das Sicherheitsnetz, dann der Eingriff. Die Integrationstests laufen in
CI, die Template-Invariante wird echt geprüft — und **erst danach** wird der
Lesepfad optimiert.

Die Reihenfolge innerhalb dieser Phase ist verbindlich. B13 vor B12 zu bauen
hieße, die Denormalisierung ohne Nebenläufigkeitstests zu bauen.

## Schritt 1 — B12: Integrationstests ausführbar machen

### Ausgangslage

`phpunit.xml.dist` registriert nur `<directory>tests/Unit</directory>`.
`tests/Integration/QuestionAnswerDatabaseTest.php` läuft damit in keiner Suite;
`.github/workflows/ci.yml` ruft `vendor/bin/phpunit` ohne Argumente und ohne
Datenbankdienst auf. Die Tests für Sperren, Nebenläufigkeit und
Fehlerpräzedenz sind totes Gewicht.

### Aufgabe

1. Zweite Suite in `phpunit.xml.dist`:

   ```xml
   <testsuite name="Integration">
       <directory>tests/Integration</directory>
   </testsuite>
   ```

   Die bestehende Suite behält ihren Namen, damit `--testsuite` in CI gezielt
   wählen kann. Der `getenv('QNA_DATABASE_TESTS')`-Guard im Test bleibt — ohne
   gesetzte Variable wird weiterhin übersprungen, nicht rot.

2. CI erweitern (`.github/workflows/ci.yml`): MySQL oder MariaDB als
   `services:`-Container, plus ein eigener Schritt

   ```yaml
   - name: PHPUnit (integration)
     run: vendor/bin/phpunit --testsuite Integration
     env:
       QNA_DATABASE_TESTS: '1'
   ```

   Der Test prüft in `setUp()`, dass die Tabellen InnoDB sind, und braucht
   `pcntl` sowie `posix` für die Fork-basierten Nebenläufigkeitstests. Ergänze
   die Extensions in `setup-php`.

   **Aus Phase 1 bekannt:** Der Observer-Verbindungspfad braucht das
   MySQL-Recht `PROCESS`, das ein normaler Anwendungsbenutzer nicht hat. Lokal
   wurde das über einen separaten Root-Zugang gelöst
   (`QNA_TEST_OBSERVER_USER` / `QNA_TEST_OBSERVER_PASSWORD`). Bilde dasselbe in
   CI ab, sonst laufen die Nebenläufigkeitstests dort anders als lokal — oder
   schlimmer: sie überspringen sich still.

   Die Tabellen müssen vor dem Lauf existieren — kläre, wie das Schema in CI
   entsteht (Contao-Migration gegen eine Minimalinstallation oder ein
   Schema-Dump im Test-Setup) und dokumentiere den gewählten Weg.

3. Prüfe, dass ein **fehlschlagender** Integrationstest die Pipeline tatsächlich
   rot färbt. Ein Test, der nur `markTestSkipped()` erreicht, ist kein Netz.
   Baue dafür kurzzeitig einen Fehler ein, belege den roten Lauf, nimm ihn
   zurück.

Punkt 3 ist nicht optional. Genau dieser Fehler — eine Suite, die grün meldet,
weil sie nichts ausführt — ist der Grund für diese Phase.

## Schritt 2 — B18: Template-Invariante echt prüfen

`tests/Unit/QnaTemplateStructureTest.php` zählt heute Zeichenketten im
Quelltext. Die geschützte Invariante ist echt und sicherheitsrelevant: **Im
initial ausgelieferten, cachefähigen Markup darf kein CSRF-Token und kein
mitgliedsbezogener Zustand stehen.**

Schreibe den Test so um, dass er das Template mit einer Twig-`Environment`
rendert und die **Ausgabe** prüft. Grep auf Quelltext entfällt.

Behalte dabei die Aussage, nicht die Formulierung: `substr_count($template,
'<turbo-frame')` wird zu einer Assertion über die gerenderte Struktur, nicht zu
einer über die Quelldatei.

`tests/Unit/DcaConfigurationTest.php` prüft auf wörtliche Palettenstrings. Das
ist weniger schlimm — eine Palette *ist* ein String. Lass es, wenn der Umbau
mehr kostet als er bringt; notiere die Entscheidung.

## Schritt 3 — B13: `voteCount` denormalisieren

**Erst ausführen, wenn Schritt 1 grün ist und die Nebenläufigkeitstests
nachweislich laufen.**

### Warum

Die beherrschende Last sind N Teilnehmende, die alle 2,5 Sekunden pollen. Jede
Anfrage aggregiert heute über alle Votes der Session.

### Aufgabe

1. Spalte `voteCount` auf `tl_qna_question` (`contao/dca/tl_qna_question.php`,
   Doctrine-Schema-Darstellung, Default 0, unsigned).
2. Hochzählen in derselben Transaktion, die den Vote schreibt. Die
   Session-Sperre wird dort ohnehin gehalten (Phase 1, `LockedContextLoader`),
   das Inkrement ist konsistent und kostenlos.
3. Der Duplicate-Vote-Pfad (`UniqueConstraintViolationException`) darf **nicht**
   hochzählen. Das ist die Stelle, an der der Zähler auseinanderläuft — prüfe
   sie mit einem gezielten Nebenläufigkeitstest.
4. Die Listenabfrage aus Phase 3 verliert `LEFT JOIN … GROUP BY` für den
   Zähler. `hasVoted` braucht weiterhin einen Zugriff auf `tl_qna_vote`;
   optimiere das getrennt und nur, wenn du den Gewinn belegen kannst.
5. `MemberDataEraser` muss die Zähler der betroffenen Fragen korrigieren, wenn
   Votes eines Mitglieds gelöscht werden. Diese Stelle wird beim
   Denormalisieren am leichtesten übersehen.

### Wahrheit und Cache

`UNIQUE(pid, memberId)` auf `tl_qna_vote` bleibt die Wahrheit. `voteCount` ist
ein Cache und **muss aus den Votes wiederherstellbar sein**. Schreibe einen
Test, der nach einer Folge von Operationen (Vote, Duplicate-Vote,
Mitgliedslöschung, Fragenlöschung) den Zähler gegen `COUNT(*)` prüft.

Eine Contao-Migrationsklasse im Bundle füllt die Spalte für Bestandsdaten.
`SPEC.md` §2 sieht diesen Weg ausdrücklich vor.

## Schritt 4 — B14: Bühnen-Fragment cachebar machen

`findForStage()` liefert bewusst `hasVoted = 0` — der Inhalt ist für alle
Betrachtenden identisch. Trotzdem setzt die Response-Factory pauschal
`private, no-store`.

### Aufgabe

Kurze geteilte TTL (1-2 s) **nur** für das Bühnen-Fragment. Reader-Antworten
bleiben unverändert `private, no-store`; sie enthalten mit `hasVoted` und dem
CSRF-Token mitgliedsbezogene Daten.

Nach Phase 4 ist `TurboResponseFactory` die einzige Stelle, an der Header
gesetzt werden. Der Aufrufer entscheidet, welche Politik gilt — nicht die
Factory anhand einer Heuristik.

### Die Falle

Der Start/Stop-Zustand darf nicht hinter der TTL hängen bleiben. Ein Vortrag,
bei dem die Bühne zwei Sekunden zu spät „offen" zeigt, ist gerade noch
akzeptabel; fünf Sekunden nicht.

Halte die TTL deshalb unter dem Polling-Intervall und begründe den Wert in
`.docs/build/DECISIONS.md`. Prüfe außerdem, ob das Bühnen-Fragment für
kontrollberechtigte Nutzende überhaupt geteilt zwischengespeichert werden darf
— es enthält für sie ein CSRF-Token und die Start/Stop-Formulare. Wahrscheinlich
brauchst du zwei Wege: geteilt cachebar ohne Steuerung, `private` mit.

Diese Unterscheidung ist der eigentliche Inhalt von Schritt 4. Wenn sie nicht
sauber gelingt, liefere Schritt 1-3 ab und lass B14 offen, mit Begründung —
ein falsch geteilter Cache mit CSRF-Token ist ein Sicherheitsfehler, keine
Performance-Optimierung.

## Nicht-Ziele

* Kein Mercure, kein SSE, keine WebSockets. `SPEC.md` §1.1 schließt sie aus.
  Wenn du beim Messen zu dem Schluss kommst, dass Polling nicht trägt, ist das
  ein Eintrag in `DECISIONS.md`, keine Implementierung.
* Keine weiteren denormalisierten Spalten.
* Keine Änderung an der Sperrlogik aus Phase 1.

## Akzeptanzkriterien

1. `vendor/bin/phpunit --testsuite Integration` führt die Tests tatsächlich aus
   (nicht: überspringt sie), wenn `QNA_DATABASE_TESTS=1` gesetzt ist.
2. CI hat einen Datenbankdienst und einen eigenen Integrationsschritt; ein
   absichtlich eingebauter Fehler färbt die Pipeline nachweislich rot.
3. Der Template-Test rendert und prüft Ausgabe statt Quelltext; die
   CSRF-Invariante ist weiterhin abgedeckt.
4. `voteCount` stimmt nach jeder Operationsfolge mit `COUNT(*)` auf
   `tl_qna_vote` überein — inklusive Duplicate-Vote und Mitgliedslöschung.
5. Die Listenabfrage enthält keine Aggregation über `tl_qna_vote` mehr für den
   Zähler. Beleg per `EXPLAIN` vorher/nachher.
6. Reader-Antworten sind unverändert `private, no-store`. Bühnen-Antworten mit
   Steuerelementen ebenfalls.

## Checks

```bash
vendor/bin/php-cs-fixer check --diff --sequential
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpunit
QNA_DATABASE_TESTS=1 vendor/bin/phpunit --testsuite Integration
ddev exec vendor/bin/contao-console contao:migrate --dry-run
```

## Bericht

* Nachweis des roten Laufs aus Akzeptanzkriterium 2 (Ausgabe des
  fehlschlagenden Tests).
* `EXPLAIN` der Listenabfrage vorher/nachher.
* Gewählte TTL für das Bühnen-Fragment mit Begründung — oder die Begründung,
  warum B14 offen bleibt.
* Der Eintrag, den du in `.docs/build/DECISIONS.md` ergänzt hast, im Wortlaut.
* Tatsächliche Kommandozeilen und reale Ausgaben aller Checks.
