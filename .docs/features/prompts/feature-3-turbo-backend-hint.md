# Feature 3 — Backend-Hinweis bei fehlendem Turbo-Entry

Lies zuerst `.docs/features/FEATURES.md` §0 vollständig, dann §3 — die
Belegtabelle in §3.2 ist deine API-Grundlage. Lies außerdem `AGENTS.md`
(Listener-Struktur, Attribut-Registrierung) und `AGENTS.local.md`.

**Verhaltensändernd:** nur im Backend (neue Hinweismeldung), kein Frontend.
**Voraussetzung:** Feature 1 committet, Checks aus §0.6 grün.
**Branch:** `feature/turbo-backend-hint`

---

## Ziel

Redaktion und Administration sehen im Backend einen Hinweis über
`Contao\Message`, wenn für die betroffene Seite — oder projektweit — keiner
der beiden Turbo-Entries `huh_ux_turbo_encore` / `huh_ux_turbo_encore_no_drive`
aktiv ist. Heute steht diese Information nur in der Browser-Konsole.

## Aufgabe

### 0. Belege nachlesen

Bevor du Code schreibst, lies die in `FEATURES.md` §3.2 genannten Dateien im
DDEV-Projekt:

```
/home/dev/Kunden/contao/contao_0507/vendor/heimrichhannot/contao-encore-bundle/src/Asset/PageEntrypoints.php
/home/dev/Kunden/contao/contao_0507/vendor/heimrichhannot/contao-encore-bundle/src/EventListener/DcaField/EncoreEntriesSelectFieldListener.php
/home/dev/Kunden/contao/contao_0507/vendor/heimrichhannot/contao-encore-bundle/src/Helper/ConfigurationHelper.php
/home/dev/Kunden/contao/contao_0507/vendor/heimrichhannot/contao-encore-bundle/contao/dca/tl_layout.php
/home/dev/Kunden/contao/contao_0507/vendor/heimrichhannot/contao-encore-bundle/contao/dca/tl_page.php
```

Bestätige im Bericht: Feldname, Blob-Format (`active`/`entry`), Aktiv-Semantik
und die Rolle von `tl_layout.addEncore`. Schau dir zusätzlich in der
DDEV-Datenbank eine echte Zeile an:

```bash
cd /home/dev/Kunden/contao/contao_0507
ddev mysql -e "SELECT id, addEncore, encoreEntries FROM tl_layout"
ddev mysql -e "SELECT id, title, encoreEntries FROM tl_page WHERE encoreEntries IS NOT NULL AND encoreEntries <> ''"
```

Das Bundle referenziert **keine** Klasse des Encore-Bundles (§0.4). Die
Entry-Namen kommen aus
`HeimrichHannot\ContaoUxTurboEncore\EncoreExtension::DEFAULT` / `::NO_DRIVER`
(`vendor/heimrichhannot/contao-ux-turbo-encore/src/EncoreExtension.php`).

### 1. Prüfservice

`src/Asset/TurboEntryAvailability.php` gemäß `FEATURES.md` §3.3.

* `final readonly`, Abhängigkeiten `ContaoFramework` (für `LayoutModel`,
  `PageModel` über Adapter) und `Doctrine\DBAL\Connection`.
* Eine private Methode `containsActiveTurboEntry(mixed $blob): bool`:
  `StringUtil::deserialize($blob, true)` über den Framework-Adapter, dann
  Zeilen prüfen. Eine Zeile zählt, wenn `entry` einer der beiden Namen ist
  **und** (`active` fehlt **oder** truthy ist) — exakt die Semantik aus
  `PageEntrypoints.php:53-55`.
* `isActiveForPage(PageModel $page)`: `loadDetails()`, Layout nur mit
  `addEncore`, dann `trail`, dann die Seite. Frühzeitig `true` zurückgeben.
* `isActiveAnywhere()`: zwei DBAL-Abfragen aus §3.3; vorher Spaltenexistenz
  über `createSchemaManager()->listTableColumns('tl_layout')` (Schlüssel sind
  kleingeschrieben — siehe `src/Migration/RebuildVoteCountMigration.php:23`,
  das dasselbe Muster für `votecount` nutzt). Fehlt die Spalte → `false`.
* Kein Zustand, keine Caches über den Request hinaus.

### 2. Meldungsservice

`src/Asset/TurboHintMessenger.php` mit `warn(?PageModel $page): void` gemäß
§3.3. Abhängigkeiten: `TurboEntryAvailability`, `ContaoFramework`,
`TranslatorInterface`. Die Meldung wird über
`$this->framework->getAdapter(Message::class)->addInfo($text)` gesetzt —
Vorbild `vendor/contao/core-bundle/src/EventListener/DataContainer/LegacyTemplatesListener.php:46`.

Doppelte Meldungen im selben Request verhindern. Zulässige Lösung: die Klasse
ist `final` (nicht `readonly`) und merkt sich in `private bool $warned` den
ersten Aufruf; begründe das im Klassen-Docblock in einem Satz. Contao setzt
Services pro Request neu auf, ein Request-Guard genügt.

Übersetzungen `qna.backend.turbo_missing_page` und
`qna.backend.turbo_missing_global` in `translations/contao_default.de.php`
**und** `.en.php` mit den Texten aus §3.3 (EN sinngemäß).

### 3. Listener

Drei Klassen gemäß der Tabelle in §3.3, je eine Datei, Registrierung
ausschließlich per `#[AsCallback(table: …, target: 'config.onload')]`:

* `src/EventListener/DataContainer/Content/ConfigOnloadListener.php`
* `src/EventListener/DataContainer/Page/ConfigOnloadListener.php`
* `src/EventListener/DataContainer/Session/ConfigOnloadListener.php`

Gemeinsame Regeln:

* Signatur `__invoke(DataContainer $dataContainer): void`.
* `act` aus `RequestStack::getCurrentRequest()?->query->get('act')`; kein
  Request → nichts tun.
* Datensatz über `$dataContainer->getCurrentRecord()`; `null` → nichts tun.
* Alle Contao-Klassen (`ArticleModel`, `PageModel`, `Message`) nur über
  `ContaoFramework::getAdapter()`, damit die Tests ohne Framework laufen.
* Der `tl_content`-Listener prüft **vor** jeder Modellabfrage den `type`.
  Andere Elementtypen dürfen keine einzige Datenbankabfrage auslösen — dieser
  Callback läuft bei jedem Öffnen eines Inhaltselements.

Ablage `Content/`, `Page/`, `Session/` folgt der `AGENTS.md`-Konvention
(Tabellenname ohne `tl_`, CamelCase). `Session/` existiert bereits
(`FieldsAliasSaveListener`).

### 4. Tests

* `tests/Unit/TurboEntryAvailabilityTest.php` gemäß §3.5 Nr. 1–2. Baue die
  Blobs mit `serialize([...])` in der Form, die du in Schritt 0 in der
  Datenbank gesehen hast. `PageModel`/`LayoutModel` als Stubs mit `__get`
  bzw. über `Adapter`-Mocks — Vorbild `tests/Unit/QnaSessionReaderControllerTest.php`
  (`ReaderInputAdapter`) und `tests/Unit/QnaStageControllerTest.php`
  (`createFramework()`).
* `tests/Unit/TurboHintListenerTest.php` gemäß §3.5 Nr. 3–5 für alle drei
  Listener. Der `Message`-Adapter ist ein Mock mit
  `expects(self::once())->method('addInfo')` bzw. `self::never()`.

### 5. Dokumentation

* `README.md`: unter „Requirements" nach dem Turbo-Absatz ein kurzer Absatz,
  dass das Backend beim Bearbeiten von Q&A-Elementen, Bühnenseiten und im
  Q&A-Modul einen Hinweis zeigt, wenn kein Turbo-Entry aktiv ist.
* `.docs/build/DECISIONS.md`: D15 mit der Belegtabelle aus `FEATURES.md` §3.2
  (Pfade aus dem DDEV-Projekt ausdrücklich als solche kennzeichnen) und der
  Entscheidung für drei Prüfpunkte statt eines `onsubmit`-Callbacks.

### 6. DDEV-Verifikation

1. `cache:clear`.
2. Im Layout des Demo-Projekts die Turbo-Entries deaktivieren (oder
   `addEncore` abwählen). Ein `qna_session_reader`-Element öffnen → Hinweis
   erscheint genau einmal. Ein Text-Element derselben Seite öffnen → kein
   Hinweis.
3. Bühnenseite (`qna_stage`) öffnen → Hinweis. Reguläre Seite öffnen → kein
   Hinweis.
4. Q&A-Modul öffnen → globaler Hinweis.
5. Entry im Layout wieder aktivieren, Schritte 2–4 wiederholen → kein Hinweis.
6. Entry nur auf einer Elternseite aktivieren, Layout ohne Entry → auf der
   Kindseite kein Hinweis, auf einer Seite eines anderen Astes Hinweis.

## Nicht-Ziele

* Keine Prüfung, ob der Encore-Build tatsächlich vorliegt
  (`public/build/entrypoints.json`) — das ist Sache des Encore-Bundles.
* Keine automatische Aktivierung eines Entries (D2: Drive ist eine
  Projektentscheidung).
* Kein Hinweis im Frontend, keine Änderung an `assets/js/qna.js`.
* Keine harte Abhängigkeit auf `heimrichhannot/contao-encore-bundle`.

## Akzeptanzkriterien

1. `grep -rn "HeimrichHannot\\\\EncoreBundle" src/ tests/` liefert nichts.
2. `grep -rn "huh_ux_turbo_encore" src/` liefert nichts — die Namen kommen aus
   den Konstanten des ux-turbo-Pakets.
3. Alle drei Listener tragen `#[AsCallback]`; `config/services.yaml` ist
   unverändert.
4. Für ein Nicht-Q&A-Inhaltselement löst der `tl_content`-Listener keine
   Modell- oder Datenbankabfrage aus (Unit-Test beweist es mit
   `expects(self::never())`).
5. Ohne Encore-Spalten liefert der Prüfservice `false` ohne Exception
   (Unit-Test).
6. README und DECISIONS D15 sind nachgezogen.

## Checks

```bash
vendor/bin/php-cs-fixer check --diff --sequential
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpunit --testsuite 'Q&A Bundle'
```

```bash
cd /home/dev/Kunden/contao/contao_0507
ddev exec vendor/bin/contao-console cache:clear
ddev exec vendor/bin/contao-console debug:container --tag=contao.callback | grep -i qna
```

## Bericht

Nach `FEATURES.md` §5. Zusätzlich: die in Schritt 0 gelesene echte
`encoreEntries`-Zeile (gekürzt), und das Ergebnis der sechs DDEV-Schritte.
