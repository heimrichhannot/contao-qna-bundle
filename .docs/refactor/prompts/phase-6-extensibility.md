# Phase 6 — Erweiterungspunkte und Legacy-Pfad

Lies zuerst `.docs/refactor/REFACTOR.md` §0 vollständig. Die dortigen Regeln
gelten für diese Phase und werden hier nicht wiederholt.

**Befunde dieser Phase:** B15, B16
**Verhaltensneutral:** nein — B16 entfernt oder isoliert einen Renderpfad
**Voraussetzung:** Phasen 1-5 sind abgeschlossen und committet.

---

## Ziel

Das Paket wird von einer Anwendung zu einem Bundle: Ein Host-Projekt bekommt
Nähte, an denen es andocken kann. Gleichzeitig verschwindet der Renderpfad, der
die eigenen Regeln verletzt.

Diese Phase enthält als einzige echte **fachliche Entscheidungen**. Wo unten
eine Wahl steht, triff sie begründet und trage sie in
`.docs/build/DECISIONS.md` nach — nicht in einen Kommentar im Code.

## Schritt 1 — B15: Domain-Events

### Warum

Alle Klassen sind `final readonly` ohne Interfaces; die einzige dokumentierte
Naht ist `QnaSessionControlVoter`. Ein Host, der bei einer neuen Frage
benachrichtigen, eine Session automatisch schließen oder Statuswechsel
protokollieren will, hat keinen Ansatzpunkt.

Das verletzt `SPEC.md` §1.1 **nicht**: Dort sind Benachrichtigungen als
*Funktionsumfang* ausgeschlossen, nicht die Naht, an der ein Host sie selbst
anbringt. Halte diese Unterscheidung im Blick — du baust den Haken, nicht das,
was daran hängt.

### Aufgabe

Drei Events unter `src/Event/`:

| Event | Ausgelöst von | Nutzlast |
| --- | --- | --- |
| `QuestionCreatedEvent` | `QuestionService::create()` | erstellte Frage |
| `SessionStartedEvent` | `SessionService::start()` | Session nach Wechsel |
| `SessionClosedEvent` | `SessionService::stop()` | Session nach Wechsel |

**Die entscheidende Regel: dispatch nach dem Commit, nie innerhalb der
Transaktion.**

Heute liegt der gesamte Methodenrumpf in
`$this->connection->transactional(...)`. Ein Listener, der dort feuert, läuft
in einer offenen Transaktion — er sieht ungeschriebenen Zustand, verlängert die
Sperre, und ein Fehler in ihm rollt die fachliche Operation zurück. Das ist
genau die Art Fehler, die erst unter Last auffällt.

Konkret: `transactional()` gibt bereits das Ergebnis zurück
(`QnaQuestion` bzw. `QnaSession`). Dispatche danach, im äußeren Methodenrumpf.

Prüfe, ob Contao/Symfony in dieser Version einen fertigen
Nach-Commit-Mechanismus bietet, bevor du selbst einen baust — und belege das
Ergebnis nach §0.2 mit einem `vendor/`-Pfad.

### Nicht bauen

* Keine Listener im Bundle selbst. Die Events sind die Naht, mehr nicht.
* Keine Interfaces für Services „damit man sie ersetzen kann". Events lösen
  das Problem; Interfaces ohne zweiten Implementierer sind Ballast.
* Keine Events für Votes. Bei einem Vote pro Sekunde und Teilnehmer ist das
  eine Lastquelle ohne erkennbaren Nutzen. Wenn du anderer Meinung bist,
  begründe es in `DECISIONS.md`, statt es einfach zu bauen.

### Dokumentation

Die Events sind öffentliche API. Ergänze im README einen Abschnitt mit den drei
Klassen, ihrer Nutzlast und einem knappen Listener-Beispiel. Ein
Erweiterungspunkt, den niemand findet, ist keiner.

## Schritt 2 — B16: Legacy-Layout-Pfad

### Ausgangslage

`src/Controller/Page/QnaStageController.php:104-137` verbiegt zur Laufzeit
`$GLOBALS['TL_HOOKS']['generatePage']` und legt die Renderargumente in
`private ?array $legacyArguments` ab — veränderlicher Zustand auf einem
geteilten Service (Zeile 32).

Drei Probleme:

1. `AGENTS.md` verbietet wörtlich das Eintragen von Hooks in
   `$GLOBALS['TL_HOOKS']`.
2. Ein Subrequest, der eine zweite Bühnenseite rendert, überschreibt das Feld.
   Der Pfad ist nicht reentrant.
3. Das veränderliche Feld ist der Grund, warum die Klasse als einzige nicht
   `final readonly` sein kann.

`FrontendIndex::renderPage()` ist laut `.docs/build/DECISIONS.md` in Contao 6
ohnehin zur Entfernung vorgesehen.

### Entscheidung

Wähle **eine** Variante und begründe sie in `DECISIONS.md`:

**A — Entfernen.** `default`-Layouts werden nicht mehr unterstützt. Der
`match` in `executeRender()` wirft für `default` eine sprechende Exception mit
Hinweis auf modernes Layout. `renderPageContent()`, `legacyArguments` und die
beiden `TL_HOOKS`-Helfer entfallen; die Klasse wird `final`.

**B — Isolieren.** Ein `LegacyStageRenderer`, der seine Argumente **explizit**
als Methodenparameter bekommt statt über ein Feld. `@deprecated` markiert, im
README als „entfällt im nächsten Major" dokumentiert. `QnaStageController` wird
`final` und zustandslos.

Variante A ist vorzuziehen, wenn kein Host-Projekt bekannt ist, das ein
`default`-Layout mit Bühnenseite nutzt — **kläre das, statt es anzunehmen.**
Variante B ist der richtige Weg, wenn es solche Projekte gibt; sie löst die
Reentranz-Frage aber nur, wenn der Zustand wirklich aus dem Service verschwindet
und nicht bloß in eine andere Klasse wandert.

Ein `$GLOBALS['TL_HOOKS']`-Eingriff bleibt in beiden Varianten unzulässig. Wenn
Variante B ohne ihn technisch nicht geht, ist das ein Argument für A — und
gehört so in `DECISIONS.md`.

### Zusatz

Unabhängig von der Variante: `QnaStageController::getContent()` (Zeilen
147-180) baut die Übersichts-Arrays inline zusammen und dupliziert, wofür
`QnaSessionListViewFactory` existiert. Nach Phase 4 ist der richtige Ort dafür
eine View-Factory. Zieh das hier nach.

## Akzeptanzkriterien

1. Die drei Events werden **nach** dem Commit dispatcht. Nachweis: ein Test mit
   einem Listener, der die Datenbank liest und den geschriebenen Zustand
   tatsächlich sieht.
2. Ein Listener, der eine Exception wirft, rollt die fachliche Operation
   **nicht** zurück. Auch das gehört getestet — es ist die Eigenschaft, wegen
   der die Dispatch-Position gewählt wurde.
3. `grep -rn "TL_HOOKS" src/` liefert keine Treffer.
4. `QnaStageController` ist `final` und hat keine veränderlichen Felder.
5. Die drei Events sind im README dokumentiert.
6. `.docs/build/DECISIONS.md` enthält je einen Eintrag zu Variante A/B und zur
   Nach-Commit-Position der Events, jeweils mit `vendor/`-Beleg, wo eine
   Contao- oder Symfony-API im Spiel ist.

## Checks

```bash
vendor/bin/php-cs-fixer check --diff --sequential
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpunit
QNA_DATABASE_TESTS=1 vendor/bin/phpunit --testsuite Integration
ddev exec vendor/bin/contao-console cache:clear
```

Zusätzlich im DDEV-Projekt: Bühnenseite mit modernem Layout aufrufen und
Start/Stop auslösen. Bei Variante B zusätzlich mit `default`-Layout.

## Bericht

* Gewählte Variante für B16 mit Begründung, inklusive der Antwort auf die
  Frage, ob ein `default`-Layout-Host bekannt ist.
* Wie die Nach-Commit-Position technisch umgesetzt ist, mit `vendor/`-Beleg
  falls eine Framework-API genutzt wurde.
* Die README-Ergänzung zu den Events im Wortlaut.
* Tatsächliche Kommandozeilen und reale Ausgaben aller Checks.
