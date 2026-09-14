# Phase 1 — Sperrreihenfolge kapseln

Lies zuerst `.docs/refactor/REFACTOR.md` §0 vollständig. Die dortigen Regeln
gelten für diese Phase und werden hier nicht wiederholt.

**Befunde dieser Phase:** B1, B2
**Verhaltensneutral:** ja (§0.4)

---

## Ziel

Die beiden Invarianten aus B1 — Sperrreihenfolge Session → Frage und die
Fehlerpräzedenz Frage-vor-Session — existieren an genau einer dokumentierten
Stelle statt in jedem Service erneut.

Das ist der Umbau mit dem höchsten Korrektheitswert im gesamten Programm.
Nimm ihn entsprechend ernst: Wenn du hier die Fehlerpräzedenz kippst, fällt es
in keinem Unit-Test auf, sondern erst im Betrieb.

## Ausgangslage

Der Block steht wortgleich in:

* `src/Service/VoteService.php:38-46`
* `src/Service/QuestionAnswerService.php:26-34`

Lies **beide** Stellen samt Kommentaren, bevor du etwas änderst. Die Kommentare
begründen die auf den ersten Blick merkwürdige Reihenfolge — insbesondere das
nachgestellte `($session ?? throw new SessionNotFoundException($sessionId))`.

## Aufgabe

### 1. `LockedContextLoader` einführen

Neu: `src/Gateway/LockedContextLoader.php`

```php
public function lockOpenSessionWithQuestion(
    int $sessionId,
    int $questionId,
): LockedQuestionContext
```

Verhalten, exakt wie heute:

1. Session mit `FOR UPDATE` lesen. Ergebnis **nicht** sofort auf `null` prüfen.
2. Frage mit `FOR UPDATE` lesen; bei `null` → `QuestionNotFoundException`.
3. Gehört die Frage nicht zu `$sessionId` → `QuestionNotFoundException`.
4. Erst jetzt: Session `null` → `SessionNotFoundException`.
5. `assertOpen()` auf der Session.

Die Methode setzt voraus, dass sie innerhalb einer Transaktion des Aufrufers
läuft. Dokumentiere das im Docblock — sie eröffnet selbst keine.

Ein Klassenkommentar hält fest, **warum** die Reihenfolge so ist: Punkt 1-2
wegen Deadlock-Vermeidung, Punkt 3-4 wegen Fehlerpräzedenz. Dieser Kommentar
ist der eigentliche Zweck der Klasse; schreibe ihn für jemanden, der in zwei
Jahren einen fünften Schreibpfad hinzufügt.

### 2. `LockedQuestionContext` als Rückgabetyp

Neu: kleines `final readonly` Objekt mit den beiden bereits gesperrten
Modellen (Session und Frage). Kein Verhalten, keine Gateways darin.

Ort: passend zur bestehenden Struktur. `src/Gateway/` ist vertretbar, weil das
Objekt ein Ergebnis des Sperrvorgangs ist und kein Domänenmodell. Falls du
`src/Model/` bevorzugst, beachte, dass Phase 4 (B10) dieses Verzeichnis erst
anlegt — dann gehört es hierher und wird in Phase 4 mitverschoben.

### 3. Beide Services umstellen

`VoteService::vote()` und `QuestionAnswerService::setAnswered()` rufen den
Loader auf. Übrig bleibt in beiden Methoden nur noch die jeweils eigene
Fachlogik:

* `VoteService`: `answered`-Prüfung, Mitglieds-ID, Insert mit
  `UniqueConstraintViolationException`-Behandlung, Zustand zurücklesen.
* `QuestionAnswerService`: Vergleich `$question->answered !== $answered` und
  ggf. `setAnswered()`.

Die Transaktionsklammer (`$this->connection->transactional(...)`) bleibt in den
Services.

### 4. B2 — Discovery-Read entfernen

`VoteService::vote()`:

* `?int $expectedSessionId = null` wird zu einem verpflichtenden `int $sessionId`,
* die Zeilen 33-35 (Vorab-`find()`) entfallen ersatzlos,
* die Parameterreihenfolge wird so gewählt, dass sie zur Route passt
  (`sessionId`, dann `questionId`) — passe `QnaActionController::vote()`
  entsprechend an.

Prüfe mit `grep`, ob es weitere Aufrufer gibt, bevor du die Signatur änderst.

## Nicht-Ziele

* Keine Änderung an den Gateways selbst (`find()`, `setAnswered()`, …).
* Kein Interface, keine Abstraktion „für später". Eine konkrete Klasse genügt.
* Kein Umbau von `QuestionService` oder `SessionService` — die sperren nur die
  Session, nicht die Kombination, und haben den Block nicht.
* Keine Änderung an Exception-Klassen. Das ist Phase 2.

## Akzeptanzkriterien

1. Der Block aus B1 kommt im gesamten `src/` nur noch **einmal** vor.
   Nachweis: `grep -rn "assertOpen" src/`
2. `VoteService::vote()` hat keinen Parameter mit Defaultwert mehr und führt
   vor `transactional()` keine Abfrage aus.
3. Die Fehlerpräzedenz ist unverändert. Konkret gilt weiterhin:
   * unbekannte Frage **und** unbekannte Session → `QuestionNotFoundException`
   * Frage aus fremder Session, Session existiert → `QuestionNotFoundException`
   * Frage existiert, Session existiert, Session nicht offen →
     `SessionNotOpenException`
4. `tests/Unit/VoteServiceTest.php` und die Answer-Tests laufen **ohne
   inhaltliche Anpassung** durch. Wird eine Anpassung nötig, ist das ein Signal:
   Begründe im Bericht, warum das kein Verhaltensbruch ist.
5. Ergänze in `tests/Unit/` einen Test, der die Fehlerpräzedenz aus Punkt 3
   direkt am `LockedContextLoader` festschreibt. Diese Regel war bisher nur
   implizit getestet.

## Checks

```bash
vendor/bin/php-cs-fixer check --diff --sequential
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpunit
```

Zusätzlich, falls die Datenbankumgebung erreichbar ist (`SPEC.md` §0.5):

```bash
QNA_DATABASE_TESTS=1 vendor/bin/phpunit tests/Integration
```

Beachte: Bis Phase 5 (B12) ist `tests/Integration` in keiner Suite registriert
— der obige Aufruf mit explizitem Pfad funktioniert trotzdem. Wenn du ihn nicht
ausführen kannst, kennzeichne das nach §0.3 als **nicht verifiziert** und
erfinde kein Ergebnis.

## Bericht

* Vorher/Nachher-Zeilenzahl von `VoteService` und `QuestionAnswerService`.
* Der Klassenkommentar des `LockedContextLoader` im Wortlaut.
* Tatsächliche Kommandozeilen und reale Ausgaben aller Checks.
