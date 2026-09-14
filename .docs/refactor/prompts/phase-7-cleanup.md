# Phase 7 — Restarbeiten

Lies zuerst `.docs/refactor/REFACTOR.md` §0 vollständig. Die dortigen Regeln
gelten für diese Phase und werden hier nicht wiederholt.

**Befunde dieser Phase:** B17.1 bis B17.6
**Verhaltensneutral:** ja (§0.4)
**Voraussetzung:** Phasen 1-6 sind abgeschlossen und committet.

---

## Ziel

Die Kleinigkeiten, die einzeln keinen Umbau rechtfertigen und zusammen den
Eindruck einer ungepflegten Codebasis erzeugen. Bewusst am Ende: Nach Phase 4
und 6 sind einige davon schon von selbst verschwunden.

**Prüfe jeden Punkt gegen den aktuellen Stand, bevor du ihn bearbeitest.** Wenn
ein Befund durch eine frühere Phase erledigt ist, notiere das und geh weiter —
nicht künstlich etwas ändern, damit die Liste abgehakt aussieht.

## B17.1 — Doppelte `DELETE_MODE`-Konstante

`src/EventListener/CloseAccountEventListener.php` und
`src/EventListener/Hook/CloseAccountListener.php` definieren beide

```php
private const string DELETE_MODE = 'close_delete';
```

und prüfen damit dasselbe. Zusätzlich liegen sie auf unterschiedlicher
Verzeichnistiefe, obwohl `AGENTS.md` für Hook-Listener `EventListener/Hook/`
vorschreibt.

**Aufgabe:**

* Die Konstante wandert an eine Stelle — sinnvollerweise zu `MemberDataEraser`
  oder in ein kleines Enum, wenn Contao die gültigen Modi ohnehin als feste
  Menge kennt (`close_delete`, `close_deactivate`; prüfe
  `vendor/contao/core-bundle/contao/dca/tl_content.php` nach §0.2).
* Der Event-Listener zieht nach `src/EventListener/Event/` um, analog zum
  Hook-Listener. Beide behalten ihre Attribut-Registrierung
  (`#[AsEventListener]`, `#[AsHook]`) — keine Konfiguration.

Beide Einstiegspunkte bleiben bestehen. Sie decken unterschiedliche
Contao-Wege ab (modernes Content-Element vs. veraltetes Modul), das ist keine
Dopplung, sondern Absicht — siehe `.docs/build/DECISIONS.md`.

`src/EventListener/DataContainer/Member/ConfigOnDeleteListener.php` bleibt, wo
er ist; er entspricht der Struktur aus `AGENTS.md`.

## B17.2 — Handgepflegte Asset-Hashes

`public/manifest.json` pflegt Hashes von Hand:

```json
{
    "qna.css": "qna.css?v=fba4e1a52fcc",
    "qna.js": "qna.js?v=a5413c2f0993",
    "turbo.es2017-esm.js": "turbo.es2017-esm.js?v=b9d35d123a07"
}
```

`public/qna.js:4` wiederholt den Turbo-Hash ein zweites Mal:

```js
Turbo = await import("./turbo.es2017-esm.js?v=b9d35d123a07")
```

Ein veralteter Hash liefert allen Nutzenden stale Caches, ohne dass etwas
anschlägt.

**Aufgabe:** Ein kleines Skript (`tools/` oder `bin/`, PHP passt zum Projekt),
das die Hashes aus den Dateiinhalten neu berechnet und mit `--check` nur prüft
statt schreibt. Als CI-Schritt einhängen:

```yaml
- name: Asset manifest
  run: php tools/manifest.php --check
```

Der Turbo-Hash in `qna.js` muss dabei mitgeprüft werden — genau die Dopplung
ist die Fehlerquelle.

Kein Build-Step, kein npm. Das README sagt zu, dass keiner nötig ist
(„No JavaScript build step is required in the host project"), und das bleibt so.

## B17.3 — Leeres `assets/`

`assets/` enthält nur `.gitkeep`, während `public/` die Quellen hält.
`.docs/build/DECISIONS.md` beschreibt es anders: „`assets/` bleibt der
Quellordner, auslieferbare Artefakte gehören nach `public/`."

**Aufgabe:** Entscheide und stelle Konsistenz her — entweder `assets/`
entfernen und `DECISIONS.md` korrigieren, oder die Quellen dorthin verschieben.

Ohne Build-Step (B17.2) hat eine Trennung Quelle/Artefakt keinen Nutzen; das
Löschen ist die wahrscheinlich richtige Antwort. Begründe die Wahl.

## B17.4 — Uneinheitliche Finalität

`QnaSessionListController`, `QnaSessionReaderController` und
`QnaStageController` sind nicht `final`, während alles andere `final readonly`
ist. Nur bei `QnaSessionControlVoter` gibt es dafür einen dokumentierten Grund
(„Host projects can replace this service to apply stricter control rules").

**Aufgabe:** Die drei Controller werden `final`. `readonly` nur, wo die
Contao-Basisklasse es zulässt — `AbstractContentElementController` und
`AbstractPageController` bringen eigene Felder mit; prüfe das nach §0.2, statt
es anzunehmen.

`QnaStageController` sollte nach Phase 6 bereits `final` sein. Prüfen, nicht
doppelt machen.

Der Voter bleibt nicht-`final`. Sein Kommentar bleibt.

## B17.5 — Parameter wird überschrieben

`src/Service/QuestionService.php:39`:

```php
$question = trim($question);
```

Der `string`-Parameter wird überschrieben, und dieselbe Methode gibt am Ende
ein `QnaQuestion` zurück. Zwei Bedeutungen für einen Namen.

**Aufgabe:** Lokale Variable umbenennen (`$text`, `$trimmed`). Ein
Zweizeiler — aber genau die Sorte Unklarheit, die beim nächsten Lesen Zeit
kostet.

Prüfe bei der Gelegenheit, ob die Validierung (leer, Länge, Cooldown) in
`create()` noch am richtigen Ort steht. Ein `QuestionText`-Value-Object wäre
sauberer, ist aber **kein Ziel dieser Phase** — wenn du es für lohnend hältst,
notiere es als Folgebefund in `REFACTOR.md`, statt es hier einzubauen.

## B17.6 — `Qna`-Präfix

Alle Klassen tragen `Qna`, obwohl der Namespace es bereits sagt:
`HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway`.

**Aufgabe:** Nur dort bereinigen, wo Phase 4 die Klasse ohnehin verschoben hat
— das ist bereits geschehen (`Domain/Session`, `View/Model/ReaderView`, …).

Für den Rest (`QnaSessionGateway`, `QnaQuestionGateway`, `QnaVoteGateway`,
`QnaActionController`, `QnaFrameController`, `QnaSessionControlVoter`,
`QnaStageController`, die Content-Element-Controller) gilt: **nicht anfassen.**

Begründung: Der Nutzen ist rein kosmetisch, die Änderung erzeugt einen
umfangreichen Diff über Tests, `services.yaml`-Erwartungen und
Content-Element-Registrierungen, und Klassennamen wie
`QnaSessionListController` erscheinen in Host-Projekten, die den Service
dekorieren. Das ist Bruch ohne Gegenwert.

Notiere die Entscheidung in `REFACTOR.md` unter B17.6, damit die Frage nicht
in sechs Monaten erneut gestellt wird.

## Nicht-Ziele

* Keine neuen Features.
* Keine Umbenennung von Datenbankspalten oder DCA-Feldern.
* Keine Änderung an öffentlichen Klassennamen außer den in Phase 4 bereits
  verschobenen.
* Keine Stilanpassungen jenseits dessen, was `php-cs-fixer` ohnehin erzwingt.

## Akzeptanzkriterien

1. `DELETE_MODE` existiert genau einmal; beide Close-Account-Listener liegen
   auf konsistenter Verzeichnistiefe.
2. Der Manifest-Check läuft in CI und schlägt bei einem manipulierten
   Asset-Inhalt nachweislich fehl. Belege den roten Lauf.
3. `assets/` und `DECISIONS.md` widersprechen sich nicht mehr.
4. Alle Controller sind `final`; der Voter ist es begründet nicht.
5. Kein überschriebener Parameter in `QuestionService::create()`.
6. `REFACTOR.md` enthält die Entscheidung zu B17.6 und etwaige Folgebefunde.

## Checks

```bash
vendor/bin/php-cs-fixer check --diff --sequential
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpunit
QNA_DATABASE_TESTS=1 vendor/bin/phpunit --testsuite Integration
php tools/manifest.php --check
```

## Abschlussbericht des Programms

Diese Phase schließt den Umbau ab. Fasse zusammen:

* Zeilenzahl `src/` vor Phase 1 und nach Phase 7.
* Welche Befunde vollständig erledigt sind, welche bewusst offen blieben
  (insbesondere B14, falls Phase 5 ihn zurückgestellt hat) und warum.
* Alle Ergänzungen in `.docs/build/DECISIONS.md` aus den Phasen 3, 5 und 6.
* Neue Folgebefunde, die während des Umbaus aufgefallen sind — als neue
  Abschnitte in `REFACTOR.md`, nicht als lose Notiz.
