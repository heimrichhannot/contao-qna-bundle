# Phase 4 — View-Schicht auftrennen

Lies zuerst `.docs/refactor/REFACTOR.md` §0 vollständig. Die dortigen Regeln
gelten für diese Phase und werden hier nicht wiederholt.

**Befunde dieser Phase:** B8, B9, B10, B11
**Verhaltensneutral:** ja (§0.4)
**Voraussetzung:** Phasen 1-3 sind abgeschlossen und committet.

---

## Ziel

`QnaFrameResponseFactory` hört auf, vier Dinge gleichzeitig zu sein. Der
Bühnen-Pfad bekommt denselben typisierten Vertrag, den der Reader-Pfad schon
hat. Domänenmodelle und View-Modelle liegen in getrennten Verzeichnissen.

Das ist der größte Umbau des Programms. Er steht bewusst hinter den Phasen 1-3,
weil die dort entfernten `catch`-Ketten, Sort-Strings und
Hydrierungs-Helfer ihn spürbar verkleinert haben.

## Ausgangslage

`src/View/QnaFrameResponseFactory.php`, 260 Zeilen, vier Verantwortungen
(B8). Dazu die Asymmetrie aus B9: `createReaderContext()` liefert ein
typisiertes `QnaReaderView` plus Kontext-Array, `createStageContext()`
(Zeilen 150-201) ein untypisiertes Array mit 18 Schlüsseln.

Lies vor Beginn:

* `src/View/QnaFrameResponseFactory.php` vollständig
* `src/View/QnaReaderViewFactory.php` — das ist das Zielmuster
* alle Templates unter `contao/templates/qna/` — sie definieren den Vertrag,
  den du typisieren willst

## Aufgabe

Arbeite in dieser Reihenfolge. Jeder Schritt ist einzeln lauffähig.

### 1. B10 — Verzeichnisse trennen

`src/Dto/` wird aufgelöst:

| heute | künftig |
| --- | --- |
| `Dto/QnaSession` | `Domain/Session` |
| `Dto/QnaQuestion` | `Domain/Question` |
| `Dto/QnaQuestionListItem` | `Domain/QuestionListItem` |
| `Dto/QnaVoteState` | `Domain/VoteState` |
| `Dto/QnaReaderView` | `View/Model/ReaderView` |
| `Dto/QnaReaderInitialView` | `View/Model/ReaderInitialView` |
| `Dto/QnaSessionListItemView` | `View/Model/SessionListItemView` |

Das `Qna`-Präfix entfällt hier mit (B17.6), weil die Klassen ohnehin verschoben
werden — eine zweite Umbenennungsrunde in Phase 7 wäre unnötiger Lärm.

`Session` behält `assertOpen()`, `assertPublished()` und `withState()`. Es ist
ein Domänenmodell, kein DTO; genau deshalb wandert es nach `Domain/`.

Falls Phase 1 `LockedQuestionContext` unter `src/Gateway/` abgelegt hat: hier
ist die Gelegenheit, das zu prüfen. Es darf bleiben — es beschreibt ein
Sperrergebnis, kein Domänenkonzept.

Denk an `config/services.yaml` (Autowiring über Namespace-Resource — sollte
ohne Änderung greifen, prüfe es) und an die Twig-Templates, die auf Feldnamen
zugreifen. **Feldnamen ändern sich nicht**, nur Klassennamen.

### 2. B9 — `StageView` typisieren

Neu: `src/View/StageViewFactory.php`, analog zu `QnaReaderViewFactory`, liefert
ein `View/Model/StageView`.

Nimm die 18 Schlüssel aus `createStageContext()` als Ausgangspunkt, aber
übernimm sie nicht blind. Manches ist echter View-Zustand
(`show_start_button`, `sort`, `status_translation_key`), manches sind
vorgerechnete URLs (`start_url`, `sort_votes_url`, `answer_urls`), manches
gehört gar nicht ins View-Model (`request_token` — das ist ein Sicherheits-,
kein Darstellungsbelang; `polling_interval` — siehe Schritt 4).

Ein vertretbarer Zuschnitt: `StageView` trägt Zustand und IDs, die URLs kommen
aus einem schmalen `StageUrlSet`, Token und Polling-Intervall setzt die
Response-Ebene dazu. Entscheide begründet — Hauptsache, das Ergebnis ist
typisiert und die Templates greifen nicht mehr auf ein `mixed`-Array zu.

Die Autorisierungsabfrage (`security->isGranted`, Zeile 157) gehört
**nicht** in die View-Factory. Der Aufrufer ermittelt `canControl` und übergibt
es — genauso, wie `QnaReaderViewFactory::createDynamic()` bereits
`bool $canInteract` entgegennimmt. Das Muster ist da; wende es an.

### 3. B8 — Response-Factory zerlegen

Aus einer Klasse werden drei:

* **`View/ReaderViewFactory`** (bestehend, erweitert): lädt Fragen, baut den
  Reader-Kontext.
* **`View/StageViewFactory`** (neu, Schritt 2).
* **`View/TurboResponseFactory`** (neu): ausschließlich HTTP. Zwei Methoden —
  eine für HTML, eine für Turbo-Streams —, die Content-Type, Status und
  Cache-Header setzen. Rund 20 Zeilen. Das ist der einzige Teil, der wirklich
  von HTTP handelt.

Die 404-Entscheidung aus `requirePublishedSession()` (Zeile 222) wandert in die
Controller. `PageNotFoundException` darf nach diesem Schritt in `src/View/`
nicht mehr vorkommen.

Dasselbe gilt für `QnaSessionReaderController::resolveSession()`
(Zeilen 57-76) — dort ist die Exception bereits am richtigen Ort, aber prüfe,
ob die Session-Auflösung selbst besser in eine Factory gehört.

Die Controller lesen danach: laden → präsentieren → verpacken.

### 4. B11 — Konfiguration bündeln

Neu: ein `QnaOptions`-Objekt (`final readonly`, ein Service) mit
`pollingInterval`, `maxQuestionLength`, `questionCooldown`.

`config/services.yaml`: der `bind:`-Block mit den drei namensgebundenen
Skalaren entfällt. Stattdessen wird `QnaOptions` als Service mit den
Parametern aus `HeimrichHannotQnaBundle::loadExtension()` definiert und normal
autowired.

Zusätzlich eine `PollingPolicy` (oder Methoden auf `QnaOptions`), die die heute
verstreuten Multiplikatoren an einer Stelle bündelt:

| heute | Bedeutung |
| --- | --- |
| `pollingInterval * 16` (zweimal) | maximales Backoff-Intervall |
| `IDLE_INTERVAL_MULTIPLIER = 4` | Intervall für `waiting`/`closed` |

Die Faktoren 4 und 16 werden benannte Konfigurationswerte mit genau diesen
Defaults. Ergänze sie in `HeimrichHannotQnaBundle::configure()` und im README —
die Werte sind heute schon im README beschrieben („Waiting and closed sessions
poll at four times the configured base interval"), aber nicht einstellbar.

Damit verschwinden die Konstruktor-Parameter `int $pollingInterval` aus
`QnaSessionReaderController`, `QnaStageController` und der Response-Factory.

## Nicht-Ziele

* Keine Änderung am Turbo-Verhalten, an Frame-IDs oder Stream-Zielen. Die
  Bezeichner in `QnaReaderViewFactory::frameId()` und Konsorten bleiben
  wortgleich — daran hängt der Browser-Zustand.
* Keine Änderung an den Templates außer Umbenennungen, die aus Schritt 1
  zwingend folgen. Insbesondere keine Umstrukturierung der Includes.
* Keine Cache-Header-Änderung. Das ist Phase 5 (B14).
* Kein Legacy-Layout-Umbau (B16 — inzwischen zurückgestellt). `QnaStageController` wird
  hier nur insoweit angefasst, wie B10 und B11 es erzwingen.

## Akzeptanzkriterien

1. Keine Klasse in `src/View/` greift auf `Security` zu oder wirft
   `PageNotFoundException`.
   Nachweis: `grep -rn "PageNotFoundException\|isGranted" src/View/`
2. `src/Dto/` existiert nicht mehr.
3. Kein `array<string, mixed>` als View-Kontext-Rückgabetyp; PHPStan
   `level: max` bestätigt die Typisierung.
4. `TurboResponseFactory` kennt weder Gateway noch Twig-Kontextaufbau — nur
   fertigen String, Status und Header.
5. `config/services.yaml` enthält keinen `bind:`-Block mit Skalaren mehr.
6. Alle bestehenden View-Tests
   (`QnaFrameResponseFactoryTest`, `QnaReaderViewFactoryTest`,
   `QnaSessionListViewFactoryTest`, `QnaTemplateStructureTest`) bleiben grün.
   Reine Klassennamen-Anpassungen sind hier erwartbar und in Ordnung; eine
   geänderte **Erwartung** ist begründungspflichtig.
7. Gerenderte Ausgabe ist byte-identisch zu vorher. Beleg: rendere im
   DDEV-Projekt je eine Reader- und eine Bühnenansicht vor und nach dem Umbau
   und vergleiche mit `diff`.

## Checks

```bash
vendor/bin/php-cs-fixer check --diff --sequential
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpunit
ddev exec vendor/bin/contao-console cache:clear
```

Für Akzeptanzkriterium 7 im DDEV-Projekt (`SPEC.md` §0.5) jeweils vor und nach
dem Umbau abrufen und die Ausgaben vergleichen. Wenn du das nicht ausführen
kannst, kennzeichne es nach §0.3 als **nicht verifiziert**.

## Bericht

* Klassendiagramm oder Aufzählung: welche Klasse hat nach dem Umbau welche
  Verantwortung, und wer ruft wen.
* Der gewählte Zuschnitt von `StageView` mit Begründung, warum welcher der 18
  Schlüssel wo gelandet ist.
* `diff`-Ergebnis aus Akzeptanzkriterium 7, oder Kennzeichnung als nicht
  verifiziert.
* Tatsächliche Kommandozeilen und reale Ausgaben aller Checks.
