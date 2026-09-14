# Phase 3 — Gateways entrümpeln

Lies zuerst `.docs/refactor/REFACTOR.md` §0 vollständig. Die dortigen Regeln
gelten für diese Phase und werden hier nicht wiederholt.

**Befunde dieser Phase:** B4, B5, B6, B7
**Verhaltensneutral:** ja (§0.4)
**Voraussetzung:** Phasen 1-2 sind abgeschlossen und committet.

---

## Ziel

Die Gateways enthalten Abfragen statt Rauschen: ein Enum für die Sortierung,
eine SQL-Vorlage statt vier, ein Hydrierer statt drei.

Diese Phase ist mechanisch und vollständig von PHPStan `level: max` abgesichert.
Sie ist trotzdem nicht trivial — die `memberId = 0`-Falle in B5 ist real.

## Aufgabe

### 1. B4 — `QuestionSort` einführen

Neu: `src/Enum/QuestionSort.php`

```php
enum QuestionSort: string
{
    case VOTES = 'votes';
    case TIME = 'time';
}
```

Regel: `QuestionSort::tryFrom($value) ?? QuestionSort::VOTES` wird
**ausschließlich an der HTTP-Grenze** aufgerufen. Innerhalb der Anwendung
wandert der Enum-Typ durch — nie wieder ein String.

Die HTTP-Grenzen sind genau diese vier Stellen:

* `src/Controller/QnaFrameController.php:55` (heute unnormalisiert!)
* `src/Controller/QnaActionController.php:151` (`start`)
* `src/Controller/QnaActionController.php:182` (`stop`)
* `src/Controller/QnaActionController.php:230` (`changeAnswered`, heute inline)
* `src/Controller/Page/QnaStageController.php:49`

Ersatzlos entfallen danach:

* `QnaStageController::normalizeSort()` (Zeile 190)
* `QnaFrameResponseFactory::normalizeSort()` (Zeile 240)
* die Default-Strings `string $sort = 'votes'` in `QnaQuestionGateway`

Ein sinnvoller Ort für die Umwandlung ist eine statische Methode am Enum
(`fromRequestValue(string $value): self`), damit das `tryFrom() ?? VOTES` nicht
fünfmal dasteht.

Beim Erzeugen von URLs wird `->value` verwendet. Prüfe, dass die generierten
Query-Parameter unverändert `sort=votes` bzw. `sort=time` lauten.

### 2. B5 — SQL-Matrix auflösen

`src/Gateway/QnaQuestionGateway.php:14-80` — vier Konstanten werden eine
Vorlage.

Die beiden Achsen:

* **Sortierung**: `ORDER BY voteCount DESC, q.createdAt ASC` bzw.
  `ORDER BY q.createdAt ASC` — kommt aus `QuestionSort`.
* **Mitgliedsbindung**: `hasVoted` wird berechnet (Reader) oder ist konstant 0
  (Bühne).

**Die Falle:** Die `STAGE_`-Varianten existieren nicht aus Bequemlichkeit,
sondern weil `MAX(CASE WHEN v.memberId = 0 …)` bei einem echten Mitglied mit
ID 0 falsch anschlagen würde. Der Fall ist in
`tests/Integration/QuestionAnswerDatabaseTest.php` real vorhanden.

Löse das über die Bedingung, nicht über eine zweite Abfrage — z. B.
`:memberId > 0 AND v.memberId = :memberId`. Dann genügt eine Vorlage und eine
Methode mit `?int $memberId`; `null` bzw. `0` bedeutet Bühnenansicht.

Ob `findForSession()` und `findForStage()` als zwei öffentliche Methoden
bestehen bleiben (mit gemeinsamer privater Implementierung) oder zu einer
zusammenfallen, ist deine Entscheidung. Zwei sprechende Methoden sind
vertretbar; zwei SQL-Konstanten nicht.

**Sicherheit:** Interpoliert werden ausschließlich Fragmente aus dem Enum bzw.
einer festen Allowlist im Gateway. Kein Wert aus dem Request erreicht die
Vorlage. Halte das im Code als Kommentar fest.

### 3. B6 — `Row`-Hydrierer

Neu: `src/Gateway/Row.php`, ein `final readonly` Wrapper um
`array<string, mixed>` mit:

```php
public function int(string $column): int;
public function nullableInt(string $column): ?int;
public function string(string $column): string;
public function bool(string $column): bool;
```

Fehlerverhalten unverändert: `\UnexpectedValueException` mit derselben
Meldung wie heute (`'Column "%s" is not an integer value.'` usw.). Die
Meldungen sind Teil des beobachtbaren Verhaltens — kopiere sie wörtlich.

Ersatzlos entfallen danach:

* `QnaSessionGateway::intValue/nullableIntValue/stringValue/boolValue`
  (Zeilen 145-176)
* `QnaQuestionGateway::intValue/stringValue/boolValue` (Zeilen 206-233)
* die inline nachgebaute Prüfung in `QnaVoteGateway::getState()` (Zeilen 50-66)

Erwartete Ersparnis: rund 80 Zeilen.

### 4. B7 — Index ergänzen

`contao/dca/tl_qna_question.php`, `config.sql.keys`:

```php
'pid,memberId,createdAt' => 'index',
```

Begründung: `findLatestCreatedAt()` filtert auf `pid AND memberId` und sortiert
nach `createdAt DESC`, und läuft bei jeder Fragenerstellung innerhalb der
Sperre. Der vorhandene Index `pid,createdAt` deckt den `memberId`-Filter nicht.

Ob der bestehende Einzelindex `createdAt` danach noch gebraucht wird, prüfst du
mit `EXPLAIN` gegen die tatsächlichen Abfragen — entferne ihn nur, wenn du das
belegen kannst.

Ziehe `.docs/build/SPEC.md` §2.2 (Indexliste) entsprechend nach.

Nach der DCA-Änderung im DDEV-Projekt migrieren und das Ergebnis belegen:

```bash
ddev exec vendor/bin/contao-console contao:migrate --dry-run
```

## Nicht-Ziele

* Keine Denormalisierung von `voteCount`. Das ist Phase 5 (B13) und braucht
  erst die Integrationstests als Netz.
* Kein Wechsel auf den Doctrine `QueryBuilder`, wenn eine Heredoc-Vorlage
  genügt. Der bestehende Stil bleibt.
* Keine Änderung an `QnaSessionGateway`s Abfragen selbst — nur die Hydrierung.
* Keine Umbenennung von Gateway-Klassen (B17.6 ist Phase 7).

## Akzeptanzkriterien

1. `grep -c "SQL;" src/Gateway/QnaQuestionGateway.php` ist deutlich kleiner als
   heute; es existiert **eine** Listen-Vorlage.
2. Im gesamten `src/` steht kein String-Literal `'votes'` oder `'time'` mehr
   außerhalb von `QuestionSort`.
   Nachweis: `grep -rn "'votes'\|'time'" src/`
3. `intValue`/`stringValue`/`boolValue` existieren genau einmal, in `Row`.
4. Die Bühnenansicht zeigt weiterhin für **alle** Betrachtenden
   `hasVoted = false`, auch für ein Mitglied mit ID 0. Schreibe genau dafür
   einen Unit-Test — dieser Fall war bisher nur implizit durch die zweite
   SQL-Variante abgedeckt und geht bei diesem Umbau am ehesten verloren.
5. `tests/Unit/QnaQuestionGatewayTest.php` und
   `tests/Unit/QnaSessionGatewayTest.php` bleiben inhaltlich unverändert grün.

## Checks

```bash
vendor/bin/php-cs-fixer check --diff --sequential
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpunit
ddev exec vendor/bin/contao-console contao:migrate --dry-run
```

Falls die Datenbankumgebung erreichbar ist, zusätzlich:

```bash
QNA_DATABASE_TESTS=1 vendor/bin/phpunit tests/Integration
```

## Bericht

* Die neue SQL-Vorlage im Wortlaut, mit markierten Einsetzstellen.
* `EXPLAIN`-Ausgabe für `findLatestCreatedAt()` vor und nach dem neuen Index,
  oder die Kennzeichnung **nicht verifiziert** nach §0.3.
* Gesamte Zeilenersparnis in `src/Gateway/`.
* Tatsächliche Kommandozeilen und reale Ausgaben aller Checks.
