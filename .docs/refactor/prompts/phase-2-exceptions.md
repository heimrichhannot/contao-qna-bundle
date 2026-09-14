# Phase 2 — Fehlerabbildung an die Exception

Lies zuerst `.docs/refactor/REFACTOR.md` §0 vollständig. Die dortigen Regeln
gelten für diese Phase und werden hier nicht wiederholt.

**Befunde dieser Phase:** B3
**Verhaltensneutral:** ja (§0.4)
**Voraussetzung:** Phase 1 ist abgeschlossen und committet.

---

## Ziel

Die Zuordnung Domain-Fehler → Übersetzungsschlüssel → HTTP-Status steht an der
Exception, nicht im Controller. Jede Aktion in `QnaActionController` kommt mit
**einem** `catch`-Block aus.

## Ausgangslage

`src/Controller/QnaActionController.php`, 268 Zeilen, 16 `catch`-Blöcke:

| Methode | Zeilen | Blöcke |
| --- | --- | --- |
| `question()` | 54-103 | 6 |
| `vote()` | 112-139 | 4 |
| `start()` | 148-170 | 2 |
| `stop()` | 179-201 | 2 |
| `changeAnswered()` | 227-241 | 2 |

Die Blöcke unterscheiden sich ausschließlich in Übersetzungsschlüssel und
Statuscode. Die vollständige heutige Zuordnung — **lies sie aus dem Code ab und
gleiche sie gegen diese Tabelle ab, bevor du etwas änderst**:

| Exception | Schlüssel | Status |
| --- | --- | --- |
| `AuthenticationRequiredException` | `qna.error.authentication_required` | 401 |
| `EmptyQuestionException` | `qna.error.empty_question` | 422 |
| `QuestionTooLongException` | `qna.error.question_too_long` | 422 |
| `QuestionCooldownException` | `qna.error.question_cooldown` | 422 |
| `SessionNotOpenException` | `qna.error.session_not_open` | 422 |
| `QuestionAnsweredException` | `qna.error.question_answered` | 422 |
| `InvalidSessionTransitionException` | `qna.error.invalid_transition` | 422 |
| `SessionNotFoundException` | — | 404 |
| `SessionNotPublishedException` | — | 404 |
| `QuestionNotFoundException` | — | 404 |

Weicht der Code von dieser Tabelle ab, gilt **der Code**. Melde die Abweichung
im Bericht.

## Aufgabe

### 1. Basisklasse erweitern

`src/Exception/QnaDomainException.php`:

```php
abstract class QnaDomainException extends \RuntimeException
{
    abstract public function translationKey(): string;

    public function statusCode(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
```

422 als Default deckt die Mehrheit ab. Die drei Not-Found-Fehler überschreiben
`statusCode()` mit 404, `AuthenticationRequiredException` mit 401.

Für die Not-Found-Fälle, die heute keinen Schlüssel haben: Sie werden nie als
Meldung gerendert, sondern in eine `PageNotFoundException` übersetzt. Vergib
trotzdem einen sinnvollen Schlüssel — die Methode ist `abstract`, und ein
`throw new \LogicException` als Implementierung wäre schlechter als ein
ungenutzter, korrekter Wert.

Prüfe, ob `Symfony\Component\HttpFoundation\Response` als Abhängigkeit im
`Exception`-Namespace vertretbar ist. Alternative: nackte `int`-Literale mit
Kommentar. Entscheide dich, begründe es kurz im Bericht — beides ist
verteidigbar, ein Mischmasch nicht.

### 2. Controller vereinfachen

Muster pro Aktion:

```php
try {
    // …
} catch (QnaDomainException $exception) {
    if (Response::HTTP_NOT_FOUND === $exception->statusCode()) {
        throw new PageNotFoundException();
    }

    return $this->responseFactory->renderReaderControls(
        $sessionId,
        $exception->translationKey(),
        $exception->statusCode(),
        $question,
    );
}
```

**Wichtig:** Welches Fragment neu gerendert wird, bleibt Sache des
Controllers. Das ist eine Präsentationsentscheidung und gehört *nicht* an die
Exception. Konkret behalten:

* `question()` → `renderReaderControls(...)` mit `$question` als Wert
* `vote()` → `renderReaderQuestions(...)`
* `start()`, `stop()`, `changeAnswered()` → `renderStage(...)` mit `$sort`

Die 404-Übersetzung wiederholt sich in jeder Aktion. Zieh sie in einen kleinen
privaten Helfer, wenn das lesbarer ist — aber nicht in eine Basisklasse und
nicht in einen `kernel.exception`-Listener.

### 3. Kein globaler Exception-Listener

Ausdrücklich **nicht** gewünscht: ein `kernel.exception`-Listener, der die
Abbildung zentral übernimmt. Er müsste erraten, welches Fragment zu rendern
ist, und würde damit genau die Information verlieren, die der Controller hat.

## Nicht-Ziele

* Keine neuen Domain-Exceptions.
* Keine Änderung an Übersetzungsdateien — die Schlüssel existieren bereits in
  `translations/contao_default.*.php`. Prüfe das, statt es anzunehmen.
* Keine Änderung an `QnaFrameResponseFactory`. Das ist Phase 4.
* Keine Änderung an den Services aus Phase 1.

## Akzeptanzkriterien

1. `grep -c "} catch" src/Controller/QnaActionController.php` liefert höchstens
   5 (einer je Aktion; `answered()`/`unanswered()` teilen sich
   `changeAnswered()`).
2. Jede konkrete Klasse unter `src/Exception/` implementiert
   `translationKey()`; PHPStan `level: max` bestätigt die Vollständigkeit.
3. Statuscodes und Übersetzungsschlüssel sind unverändert. Der maßgebliche
   Nachweis ist `tests/Unit/QnaActionControllerTest.php` — er prüft genau das
   und muss **ohne inhaltliche Anpassung** grün bleiben.
4. `QnaActionController` ist spürbar kürzer. Richtwert: unter 150 Zeilen.

## Checks

```bash
vendor/bin/php-cs-fixer check --diff --sequential
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpunit
```

## Bericht

* Vorher/Nachher-Zeilenzahl von `QnaActionController`.
* Die tatsächlich im Code vorgefundene Zuordnungstabelle, falls sie von der
  oben abgedruckten abweicht.
* Entscheidung und Begründung zur `Response`-Abhängigkeit im
  `Exception`-Namespace.
* Tatsächliche Kommandozeilen und reale Ausgaben aller Checks.
