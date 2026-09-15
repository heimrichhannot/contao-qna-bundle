# REFACTOR — contao-qna-bundle

Referenzspezifikation für den Architektur-Umbau der bestehenden Erweiterung.

Diese Datei ist die **einzige fachliche Quelle** für den Umbau. Die
Phasen-Prompts (`prompts/phase-*.md`) verweisen auf die Befunde dieser Datei
und wiederholen sie nicht.

Fachliche Grundlage bleibt `.docs/build/SPEC.md`. Der Umbau ändert **kein**
nach außen sichtbares Verhalten, solange ein Befund das nicht ausdrücklich
vorsieht (B13, B14, B15, B16). `.docs/build/DECISIONS.md` bleibt gültig und
wird bei neuen API-Entscheidungen ergänzt, nicht ersetzt.

---

## 0. Verbindliche Rahmenbedingungen

Diese Regeln gelten für **jede** Phase. Ein Phasen-Prompt wiederholt sie nicht.

### 0.1 Ausgangslage

Der Umbau setzt auf dem aktuellen `main` auf. Die Schichtung
Gateway → Model → Service → View → Controller ist im Kern richtig und wird
**nicht** ersetzt, sondern bereinigt. Wer eine grundlegend andere Architektur
vorschlägt (Doctrine ORM, CQRS, Event Sourcing, Contao-`Model`-Klassen), hat
die Aufgabe missverstanden.

Ebenfalls unangetastet bleiben:

* die Sperrreihenfolge Session → Frage und die `FOR UPDATE`-Lesevorgänge,
* die bedingten Statuswechsel (`UPDATE … WHERE state = :expected`),
* die Behandlung eines Duplicate-Votes als Erfolg,
* PHPStan `level: max` über `src` **und** `tests`,
* die Nicht-Ziele aus `SPEC.md` §1.1.

### 0.2 API-Verifikationspflicht

Unverändert gültig aus `SPEC.md` §0.2: Jede verwendete Contao-Klasse,
-Methode, -Attribut-Signatur und -Konstante wird vor Verwendung unter
`vendor/contao/core-bundle/src/` nachgelesen. Nicht auffindbare APIs werden
nicht erfunden. Jede neue zentrale API-Entscheidung wird in
`.docs/build/DECISIONS.md` mit dem konkreten `vendor/`-Pfad als Beleg
nachgetragen.

### 0.3 Ehrlichkeitsregel für Checks

Unverändert gültig aus `SPEC.md` §0.3: Behaupte nie, einen Check ausgeführt zu
haben. Jeder genannte Check enthält die tatsächlich abgesetzte Kommandozeile
und die reale Ausgabe (gekürzt). Nicht ausführbare Checks werden als **nicht
verifiziert** gekennzeichnet — das ist ein akzeptables Ergebnis, eine
erfundene Erfolgsmeldung nicht.

### 0.4 Verhalten bleibt gleich

Reine Umbauphasen (1, 2, 3, 4, 7) sind verhaltensneutral. Das heißt konkret:

* identische HTTP-Statuscodes,
* identische Übersetzungsschlüssel in der Ausgabe,
* identische Turbo-Frame- und Turbo-Stream-Zielbezeichner,
* identische Cache-Header,
* identische Fehlerpräzedenz (siehe B1).

Die bestehenden Unit-Tests sind der Gradmesser. Wo ein Test nur deshalb
angepasst werden muss, weil er eine Implementierung statt eines Verhaltens
festschreibt, wird das im Bericht ausdrücklich begründet.

### 0.5 Sprache und Stil

Code, Bezeichner, Kommentare, Commit-Nachrichten und README: **Englisch**.
Diese Bau-Dokumentation: Deutsch. Der Code folgt dem bestehenden Stil:
`final readonly` als Regel, Konstruktor-Promotion, `declare(strict_types=1)`,
keine Contao-`Model`-Statics, keine Registrierung von Hooks oder Callbacks in
Konfiguration (siehe `AGENTS.md`).

### 0.6 Reihenfolge und Abschluss

Die Phasen werden **in der Nummernreihenfolge** abgearbeitet; Phase *n* setzt
den abgeschlossenen Zustand von *n-1* voraus. Jede Phase endet mit einem
eigenen Commit und einem grünen Durchlauf von:

```bash
vendor/bin/php-cs-fixer check --diff --sequential
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpunit
```

Eine Phase, die diese drei Checks nicht besteht, ist nicht abgeschlossen.

### 0.7 Integrationsumgebung

Unverändert gültig aus `SPEC.md` §0.5: DDEV-Projekt `contao0507.contao`,
Kommandos laufen im Container (`ddev exec`, `ddev php`, `ddev composer`,
`ddev mysql`). Nach Änderungen an DCA, Service-Definitionen, Routen oder
Übersetzungen wird der Contao-Cache geleert.

---

## 1. Befunde

Die Befunde sind nach Priorität geordnet und werden von den Phasen-Prompts
über ihre Kennung (`B1` … `B18`) referenziert.

### B1 — Sperrreihenfolge wird durch Konvention statt durch Code erzwungen

`src/Service/VoteService.php:38-46` und
`src/Service/QuestionAnswerService.php:26-34` enthalten denselben Block
wortgleich:

```php
$session = $this->sessionGateway->find($sessionId, true);
$question = $this->questionGateway->find($questionId, true)
    ?? throw new QuestionNotFoundException($questionId);

if ($question->sessionId !== $sessionId) {
    throw new QuestionNotFoundException($questionId);
}

($session ?? throw new SessionNotFoundException($sessionId))->assertOpen();
```

Zwei Invarianten stecken darin, beide nur durch Kommentare dokumentiert:

1. **Sperrreihenfolge**: Session vor Frage, sonst Deadlock zwischen
   nebenläufigen Vote- und Answer-Transaktionen.
2. **Fehlerpräzedenz**: Eine fehlende Frage bzw. eine Frage aus einer fremden
   Session muss *vor* der fehlenden Session gemeldet werden. Deshalb das
   auffällige `($session ?? throw …)` erst am Ende.

Vier Services müssen diese Regeln künftig unabhängig voneinander erinnern. Das
ist die Stelle, an der ein späterer Schreibpfad die Konsistenz bricht.

**Zielbild:** ein `Gateway\LockedContextLoader` mit einer Methode
`lockOpenSessionWithQuestion(int $sessionId, int $questionId): LockedQuestionContext`,
die beide Regeln an genau einer dokumentierten Stelle kapselt.

### B2 — `VoteService` liest vor der Transaktion überflüssig

`src/Service/VoteService.php:33-35` führt eine Discovery-Abfrage aus, nur um
`$sessionId` zu ermitteln, wenn der optionale Parameter
`?int $expectedSessionId = null` nicht gesetzt ist. Die einzige reale
Aufrufstelle — `QnaActionController::vote()` — übergibt die Session-ID immer,
weil sie aus der Route stammt.

**Zielbild:** Parameter verpflichtend, Discovery-Read und die nullable
Verzweigung entfallen.

### B3 — Exception-auf-Response-Abbildung ist eine Tabelle, geschrieben als Kontrollfluss

`src/Controller/QnaActionController.php` hat 268 Zeilen und **16**
`catch`-Blöcke. Allein `question()` (Zeilen 54-103) enthält sechs Blöcke, die
sich ausschließlich in Übersetzungsschlüssel und Statuscode unterscheiden.
Jede neue Domain-Exception erzwingt heute Änderungen an mehreren Stellen.

**Zielbild:** Die Abbildung wandert an die Exception.

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

`SessionNotFoundException`, `SessionNotPublishedException` und
`QuestionNotFoundException` liefern 404; der Controller übersetzt 404 in eine
`PageNotFoundException`. Welches Fragment neu gerendert wird, bleibt Sache des
Controllers — das ist eine Präsentationsentscheidung, keine Eigenschaft des
Fehlers.

Ergebnis: ein `catch`-Block pro Aktion.

### B4 — Sortierung ist ein magischer String, viermal normalisiert

`'votes'` bzw. `'time'` werden an vier Stellen unabhängig normalisiert:

| Ort | Zeile |
| --- | --- |
| `QnaStageController::normalizeSort()` | `src/Controller/Page/QnaStageController.php:190` |
| `QnaFrameResponseFactory::normalizeSort()` | `src/View/QnaFrameResponseFactory.php:240` |
| inline in `QnaActionController::changeAnswered()` | `src/Controller/QnaActionController.php:230` |
| Default-Argument in `QnaQuestionGateway` | `findForSession()`, `findForStage()` |

`QnaFrameController::stage()` (`src/Controller/QnaFrameController.php:55`)
reicht den Wert dagegen unnormalisiert weiter.

**Zielbild:** `Enum\QuestionSort: string { VOTES = 'votes'; TIME = 'time'; }`
mit `tryFrom() ?? self::VOTES` **ausschließlich an der HTTP-Grenze**. Innerhalb
der Anwendung wird der Enum-Typ durchgereicht, nie ein String.

### B5 — Vier fast identische SQL-Konstanten

`src/Gateway/QnaQuestionGateway.php:14-80` definiert `LIST_BY_VOTES_SQL`,
`LIST_BY_TIME_SQL`, `STAGE_LIST_BY_VOTES_SQL` und `STAGE_LIST_BY_TIME_SQL` —
eine 2×2-Matrix, ausgeschrieben als rund 60 Zeilen Copy-Paste. Die Varianten
unterscheiden sich nur in `ORDER BY` und darin, ob `hasVoted` berechnet wird.

Die `STAGE_`-Varianten existieren, weil `memberId = 0` in der
`MAX(CASE WHEN …)`-Berechnung sonst echte Votes eines Mitglieds mit ID 0
treffen könnte. Der Fall ist real: `tests/Integration/QuestionAnswerDatabaseTest.php`
legt Fragen mit `memberId 0` an.

**Zielbild:** eine SQL-Vorlage. `ORDER BY` stammt aus `QuestionSort` (B4),
die Mitgliedsbindung aus einer Bedingung, die `0`/`null` sauber behandelt
(z. B. `:memberId > 0 AND v.memberId = :memberId`). Die eingesetzten Fragmente
kommen ausschließlich aus einer festen Allowlist bzw. dem Enum — kein
Nutzereingabe-String erreicht die Vorlage.

### B6 — Hydrierungs-Boilerplate dreifach

`intValue`, `stringValue`, `boolValue`, `nullableIntValue` stehen wortgleich in
`src/Gateway/QnaSessionGateway.php:145-176` und
`src/Gateway/QnaQuestionGateway.php:206-233`; `QnaVoteGateway::getState()`
(`src/Gateway/QnaVoteGateway.php:50-66`) baut dieselbe Prüfung noch einmal
inline nach.

**Zielbild:** ein `Gateway\Row`-Wrapper (`$row->int('id')`,
`$row->nullableInt('startedAt')`, `$row->string('title')`, `$row->bool('answered')`)
mit einer Implementierung. Die Gateways verlieren je rund 30 Zeilen Rauschen,
das heute die eigentlichen Abfragen verdeckt.

### B7 — Fehlender Index auf dem Schreibpfad

`QnaQuestionGateway::findLatestCreatedAt()` filtert auf `pid AND memberId` und
sortiert nach `createdAt DESC`. `tl_qna_question` hat laut
`contao/dca/tl_qna_question.php` nur `pid`, `createdAt` und `pid,createdAt`.
Die Abfrage läuft bei **jeder** Fragenerstellung innerhalb der Sperre.

**Zielbild:** zusätzlicher Index `pid,memberId,createdAt`. `SPEC.md` §2.2 wird
entsprechend nachgezogen.

### B8 — `QnaFrameResponseFactory` ist der eigentliche Architekturbefund

`src/View/QnaFrameResponseFactory.php`, 260 Zeilen, erledigt vier
zusammenhanglose Aufgaben:

1. Datenbeschaffung (`QnaSessionGateway`, `QnaQuestionGateway`),
2. Autorisierung (`security->isGranted`, Zeile 157),
3. View-Model-Aufbau (`createReaderContext`, `createStageContext`),
4. HTTP-Antworten (Status, Content-Type, Cache-Header).

Zusätzlich wirft `requirePublishedSession()` (Zeile 222) eine
`PageNotFoundException` — ein HTTP-Belang aus der `View`-Schicht heraus.

Dieselbe Verletzung noch einmal in
`src/Controller/ContentElement/QnaSessionReaderController.php:57-76`
(`resolveSession()`).

### B9 — Reader und Bühne haben unterschiedliche Template-Verträge

Der Reader-Pfad hat ein typisiertes View-Model (`Dto\QnaReaderView`, gebaut von
`View\QnaReaderViewFactory`). Der Bühnen-Pfad baut stattdessen in
`QnaFrameResponseFactory::createStageContext()` (Zeilen 150-201) ein
untypisiertes `array<string, mixed>` mit 18 Schlüsseln.

Zwei Renderpfade, zwei völlig verschiedene Verträge mit den Templates. Das
Muster für die Lösung existiert bereits — die Bühne hat es nur nie bekommen.

**Zielbild:** `View\StageViewFactory` liefert ein typisiertes `StageView`,
analog zu `QnaReaderViewFactory`.

### B10 — `Dto/` vermischt zwei Objektarten

`src/Dto/` enthält nebeneinander:

* Persistenz-Lesemodelle: `QnaSession`, `QnaQuestion`, `QnaQuestionListItem`,
  `QnaVoteState`
* View-Modelle: `QnaReaderView`, `QnaReaderInitialView`, `QnaSessionListItemView`

`QnaSession` ist zudem kein DTO: Es trägt mit `assertOpen()`,
`assertPublished()` und `withState()` Domänenverhalten. Die Benennung führt in
die Irre.

**Zielbild:** `src/Domain/` für Domänenmodelle, `src/View/Model/` für
View-Modelle. `Dto/` entfällt.

**Nicht `src/Model/`.** Dieses Verzeichnis und das Klassensuffix `*Model` sind
in Contao für Active-Record-Klassen belegt (`Contao\Model`-Ableitungen,
registriert in `$GLOBALS['TL_MODELS']`). Technisch erzwingt das Framework
keinen Pfad — `Model::getClassFromTable()`
(`vendor/contao/core-bundle/contao/library/Contao/Model.php:1401`) löst
ausschließlich über die Registrierung in
`vendor/contao/core-bundle/contao/config/config.php:467` auf, und der Core legt
seine eigenen Models unter `contao/models/` ab. Es ist also eine Konvention,
keine Sperre. Sie greift hier trotzdem: Das Bundle bringt drei eigene Tabellen
mit, und ein späterer echter `QnaSessionModel` stünde sonst neben einem
gleichnamigen Objekt ohne jede Active-Record-Semantik. Siehe `AGENTS.md`.

### B11 — Polling-Konfiguration ist über fünf Dateien verteilt

Die Multiplikatoren sind magisch und uneinheitlich:

| Ort | Wert |
| --- | --- |
| `QnaSessionReaderController.php:52` | `pollingInterval * 16` |
| `QnaStageController.php:178` | `pollingInterval * 16` |
| `QnaFrameResponseFactory.php:25` | `IDLE_INTERVAL_MULTIPLIER = 4` |

Zusätzlich bindet `config/services.yaml` die Skalare `$pollingInterval`,
`$maxQuestionLength` und `$questionCooldown` global **über den
Argumentnamen**. Jeder künftige Service mit einem gleichnamigen Parameter
bekommt den Wert stillschweigend injiziert.

**Zielbild:** ein `QnaOptions`-Objekt (readonly, ein Service) statt drei
namensgebundener Skalare, und eine `PollingPolicy`, die `idle` und `max` aus
benannten Konfigurationswerten statt aus verstreuten Literalen ableitet.

### B12 — Integrationstests laufen nirgends

`phpunit.xml.dist` registriert ausschließlich `<directory>tests/Unit</directory>`.
`tests/Integration/QuestionAnswerDatabaseTest.php` ist damit in keiner Suite;
`vendor/bin/phpunit` führt die Datei auch mit `QNA_DATABASE_TESTS=1` nicht aus.
`.github/workflows/ci.yml` ruft `vendor/bin/phpunit` ohne Argumente und ohne
Datenbankdienst auf.

Ausgerechnet die Tests für den heikelsten Code — Sperren, Nebenläufigkeit,
Fehlerpräzedenz — sind totes Gewicht.

**Zielbild:** zweite `<testsuite name="Integration">`, MySQL/MariaDB als
Service-Container in CI, eigener CI-Schritt mit `QNA_DATABASE_TESTS=1`.

**Diese Phase geht B13 und B14 voraus** — die Integrationstests sind das
Sicherheitsnetz für beide.

### B13 — Der Lesepfad ist das Produkt und der unoptimierte Teil

Die Erweiterung ist eine Live-Anwendung. Die beherrschende Last sind N
Teilnehmende, die alle 2,5 Sekunden pollen. Jede dieser Anfragen führt
`LEFT JOIN tl_qna_vote v ON v.pid = q.id … GROUP BY q.id` über alle Votes der
Session aus.

**Zielbild:** `voteCount` als denormalisierte Spalte auf `tl_qna_question`. Die
Schreibtransaktion hält die Session-Sperre ohnehin, das Hochzählen ist dort
kostenlos und konsistent. Aus der Aggregation wird ein indizierter Lesevorgang.

Der `UNIQUE(pid, memberId)`-Index auf `tl_qna_vote` bleibt die Wahrheit; die
Spalte ist ein Cache und muss aus den Votes wiederherstellbar sein.

### B14 — Die Bühnenansicht ist mitgliedsunabhängig und trotzdem `no-store`

`QnaQuestionGateway::findForStage()` liefert bewusst `hasVoted = 0`. Der Inhalt
ist für alle Betrachtenden identisch. Trotzdem setzt
`QnaFrameResponseFactory::createResponse()` (Zeile 245) pauschal
`Cache-Control: private, no-store`.

**Zielbild:** kurze, geteilte TTL (1-2 s) **nur** für das Bühnen-Fragment. Das
faltet Projektor und alle Zuschauenden auf eine Abfrage pro Intervall
zusammen. Reader-Antworten bleiben unverändert `private, no-store` — sie
enthalten mit `hasVoted` und dem CSRF-Token mitgliedsbezogene Daten.

**Vorsicht:** Der Start/Stop-Zustand darf nicht hinter der TTL hängen bleiben.
Die TTL wird deshalb bewusst unter dem Polling-Intervall gehalten und in
`DECISIONS.md` begründet.

### B15 — Keine Erweiterungspunkte für ein verteilbares Bundle — **ZURÜCKGESTELLT**

Alle Klassen sind `final readonly` ohne Interfaces; die einzige dokumentierte
Naht ist `QnaSessionControlVoter`. Ein Host-Projekt, das bei einer neuen Frage
benachrichtigen, eine Session automatisch schließen oder Statuswechsel
protokollieren will, hat keinen Ansatzpunkt.

**Lösungsweg, falls der Bedarf entsteht:** `QuestionCreatedEvent`,
`SessionStartedEvent`, `SessionClosedEvent` — dispatcht **nach** dem Commit, nie
innerhalb der Transaktion. `transactional()` gibt das Ergebnis bereits zurück
(`QuestionService::create()`, `SessionService::start()`/`stop()`), dispatcht wird
im äußeren Methodenrumpf. Ein Listener innerhalb der Transaktion sähe
ungeschriebenen Zustand, verlängerte die Session-Sperre, und eine Exception in
ihm rollte die fachliche Operation zurück.

**Zurückgestellt am 15.09.2026.** Begründung: Es gibt keinen benannten Abnehmer,
und `SPEC.md` §1.1 schließt Benachrichtigungen, Moderation und Export
ausdrücklich als Funktionsumfang aus. Entscheidend ist, dass Events **rein
additiv** sind — anders als die Schichtungsbefunde werden sie durch Warten nicht
teurer. Sobald ein Host-Projekt konkret einen Haken braucht, lassen sie sich
ohne Bruch an bestehendem Code nachrüsten. Bis dahin wären es drei Klassen,
eine neue Dispatcher-Abhängigkeit und zwei Tests für einen hypothetischen
Konsumenten.

Wieder aufgreifen, wenn ein Host-Projekt einen konkreten Anwendungsfall nennt.

### B16 — Der Legacy-Layout-Pfad verletzt die eigenen Regeln — **ZURÜCKGESTELLT**

`src/Controller/Page/QnaStageController.php:113-144` verbiegt zur Laufzeit
`$GLOBALS['TL_HOOKS']['generatePage']` und legt die Renderargumente in
`private ?array $legacyArguments` ab — **veränderlicher Zustand auf einem
geteilten Service** (Zeile 34).

Zwei Probleme:

1. `AGENTS.md` verbietet wörtlich das Eintragen von Hooks in
   `$GLOBALS['TL_HOOKS']`.
2. Ein Subrequest, der eine zweite Bühnenseite rendert, überschreibt das Feld.
   Der Pfad ist nicht reentrant.

Ein drittes Problem stand hier ursprünglich — das Feld verhindere `final`. Das
war falsch: Es verhindert nur `readonly`. `final` ist unabhängig davon möglich
und wird in B17.4 erledigt.

#### Lösungsweg über `$GLOBALS['TL_PTY']` (recherchiert, nicht umgesetzt)

Der Legacy-Pfad lässt sich **ohne** Laufzeit-Hook bauen. Die Kette im Core:

| Schritt | Beleg |
| --- | --- |
| `AbstractPageController::renderPage()` verzweigt per `match` auf `layout->type` | `vendor/contao/core-bundle/src/Controller/Page/AbstractPageController.php:50-54` |
| `handleDefaultLayout()` (protected) delegiert an den Legacy-Renderer | ebenda, `:65-68` |
| `FrontendIndex::renderLegacy()` löst den Handler auf | `vendor/contao/core-bundle/contao/controllers/FrontendIndex.php:45` |
| `$GLOBALS['TL_PTY'][$objPage->type] ?? PageRegular::class`, dann `new $pageType()` | ebenda, `:64-67` |
| Einsprungpunkt `PageRegular::createTemplate()` ist `protected`; `main` wird dort auf `''` gesetzt | `vendor/contao/core-bundle/contao/pages/PageRegular.php:383` bzw. `:515` |

`$GLOBALS['TL_PTY']` ist eine statische Registrierung zur Konfigurationszeit
(Core: `contao/config/config.php:354`); dieses Bundle nutzt dieselbe Datei
bereits für `$GLOBALS['BE_MOD']`. Eine `PageRegular`-Unterklasse, dort
registriert, setzt den Inhalt am Ende eines überschriebenen `createTemplate()` —
weil die Bühnenseite mit `contentComposition: false` registriert ist, gibt es
keine Artikelschleife, die `main` danach überschreibt. Damit entfallen beide
Probleme: statische statt dynamischer Registrierung, und `new $pageType()`
erzeugt eine Instanz pro Request.

Nebenbei würde `QnaStageController::executeRender()` überflüssig — die
Verzweigung modern/default macht `AbstractPageController::renderPage()` selbst.

Einschränkung: Der Handler wird per `new` ohne DI erzeugt und müsste seine
Abhängigkeiten über `System::getContainer()` ziehen.

#### Zurückgestellt am 15.09.2026

Drei Gründe:

1. **Der Reentranz-Fehler ist latent, nicht aktiv.** Er braucht einen Subrequest,
   der eine zweite Bühnenseite rendert. Die Bühne ist ein Seitentyp mit
   `contentComposition: false` — keine Artikel, keine Inhaltselemente —, und das
   Bundle bringt keinen Mechanismus mit, der eine Seite in eine Seite rendert.
   Innerhalb eines Requests stellt der `finally`-Block Hook und Feld korrekt
   wieder her.
2. **Contao 6 erzwingt den Umbau ohnehin.** `FrontendIndex::renderPage()` ist
   seit 5.7 deprecated, `renderLegacy()` ist `@internal`, `PageRegular`
   verschwindet. Auch die `TL_PTY`-Variante wäre nur bis dahin haltbar. Der
   Aufwand fiele beim Contao-6-Wechsel erneut an, dann unter Randbedingungen,
   die heute niemand kennt.
3. **Der Pfad wird gebraucht.** Ein Entfernen von `default`-Layouts (die
   ursprünglich erwogene Variante A) steht nicht zur Debatte.

Solange der Hook bleibt, trägt die Codestelle einen Kommentar, der auf diesen
Abschnitt verweist — damit die Abweichung von `AGENTS.md` nicht beiläufig
„repariert" oder als Präzedenzfall gelesen wird.

Wieder aufgreifen beim Contao-6-Wechsel, spätestens wenn der Legacy-Pfad
tatsächlich bricht.

### B17 — Kleinere Befunde

| Kennung | Befund |
| --- | --- |
| B17.1 | `DELETE_MODE` steht doppelt in `EventListener/CloseAccountEventListener.php` und `EventListener/Hook/CloseAccountListener.php`; beide liegen zudem auf unterschiedlicher Verzeichnistiefe, obwohl `AGENTS.md` für Hooks `EventListener/Hook/` vorschreibt. |
| B17.2 | ~~`public/manifest.json` pflegt Hashes von Hand; `public/qna.js:4` wiederholt den Turbo-Hash ein zweites Mal.~~ **Erledigt am 15.09.2026** durch die Umstellung auf Encore (siehe unten): Manifest, vendorierte Turbo-Kopie und die Hash-Dopplung sind ersatzlos entfallen. |
| B17.3 | ~~`assets/` enthält nur `.gitkeep`, während `public/` die Quellen hält.~~ **Erledigt am 15.09.2026**: `assets/js/` und `assets/css/` sind jetzt die echten Quellen, `public/` ist entfallen. Die Beschreibung in `DECISIONS.md` stimmt damit erstmals. |
| B17.4 | Die drei Contao-Controller sind nicht `final`, während alles andere `final readonly` ist. Nur beim Voter gibt es dafür einen dokumentierten Grund. |
| B17.5 | `QuestionService::create()` überschreibt seinen eigenen `string`-Parameter `$question` (`src/Service/QuestionService.php:39`) und gibt am Ende ein `QnaQuestion` zurück. |
| B17.6 | Alle Klassen tragen das Präfix `Qna`, obwohl der Namespace es bereits sagt (`HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway`). Kosmetisch, wird nur im Zuge ohnehin verschobener Klassen bereinigt. |
| B17.7 | `QnaStageController::getContent()` (Zeilen 149-182) baut die Übersichts-Arrays inline zusammen und dupliziert damit, wofür `QnaSessionListViewFactory` existiert. Stammt aus dem gestrichenen B16-Umfang und ist von der dortigen Zurückstellung unabhängig. |

### B18 — Template-Tests prüfen Quelltext statt Verhalten

`tests/Unit/QnaTemplateStructureTest.php` zählt Zeichenketten
(`substr_count($template, '<turbo-frame')`,
`assertStringNotContainsString('request_token', …)`). Die Tests brechen bei
jeder Umformatierung und prüfen kein Verhalten.

Die dahinterliegende Invariante ist allerdings **echt und
sicherheitsrelevant**: Im initial ausgelieferten, cachefähigen Markup darf kein
CSRF-Token und kein mitgliedsbezogener Zustand stehen. Genau deshalb verdient
sie einen Test, der das Template rendert und die Ausgabe prüft, statt den
Quelltext zu durchsuchen.

Dasselbe gilt abgeschwächt für `tests/Unit/DcaConfigurationTest.php`, das auf
wörtliche Palettenstrings prüft.

### B19 — Front-End-Assets liefen am Hausstandard vorbei — **ERLEDIGT**

`public/qna.js` importierte Turbo dynamisch aus einer vendorierten Kopie mit
hartkodiertem Hash, während `heimrichhannot/contao-ux-turbo-encore` mit
`turbo_no_drive.js` exakt dieselbe Sache bereits löst — gleiche Absicht, gleiche
Turbo-Version 8.0.23.

Die README-Zusage „kein Build-Step im Host-Projekt", die dem entgegenstand, war
kein Produktentscheid, sondern beim Bau entstanden und wurde übersehen.

**Umgesetzt am 15.09.2026:** Umstellung auf Encore. Details, Belege und der
offene Punkt zum fehlenden Tag stehen in `.docs/build/DECISIONS.md` unter `D12`.
Damit sind B17.2 und B17.3 miterledigt, statt behandelt zu werden.

**Offen und blockierend für `composer install` in Fremdprojekten:**
`heimrichhannot/contao-ux-turbo-encore` ist ungetaggt. In der `composer.json`
steht derzeit `dev-main`. Sobald das Repository ein Tag hat, auf `^0.1`
umstellen und `composer update` ausführen.

---

## 2. Phasenübersicht

| Phase | Prompt | Befunde | Verhaltensneutral |
| --- | --- | --- | --- |
| 1 | `prompts/phase-1-locking.md` | B1, B2 | ja |
| 2 | `prompts/phase-2-exceptions.md` | B3 | ja |
| 3 | `prompts/phase-3-gateways.md` | B4, B5, B6, B7 | ja |
| 4 | `prompts/phase-4-view.md` | B8, B9, B10, B11 | ja |
| 5 | `prompts/phase-5-tests-performance.md` | B12, B13, B14, B18 | nein (B13, B14) |
| ~~6~~ | ~~`prompts/phase-6-extensibility.md`~~ | ~~B15, B16~~ | **entfällt** |
| 7 | `prompts/phase-7-cleanup.md` | B17.1 – B17.7 | ja |

**Phase 6 ist am 15.09.2026 gestrichen.** Beide Befunde sind zurückgestellt
(Begründungen dort); der Prompt wurde gelöscht. Der einzige verbliebene Punkt
aus ihrem Umfang — die Duplizierung in `getContent()` — ist als B17.7 nach
Phase 7 gewandert. **Phase 7 ist damit die letzte Phase.**

Begründung der Reihenfolge:

* **1 vor allem anderen** — höchster Korrektheitswert, kleinster Radius.
* **2 danach** — löscht am meisten Code bei geringstem Risiko und macht die
  Controller klein genug, dass Phase 4 überschaubar bleibt.
* **3 vor 4** — mechanisch und von PHPStan abgesichert; `QuestionSort` (B4)
  wird in Phase 4 gebraucht.
* **4** — der große Umbau, bewusst nach 1-3, damit er kleiner ausfällt.
* **5 nach 4** — die Integrationstests (B12) sind das Sicherheitsnetz für die
  Denormalisierung (B13) und die Cache-Änderung (B14).
* **7 zuletzt**, weil es reine Kosmetik ist.
