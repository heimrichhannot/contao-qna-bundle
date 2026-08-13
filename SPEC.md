# SPEC — contao-qna-bundle

Referenzspezifikation für eine eigenständige Contao-5.7-Erweiterung
(Event-Q&A / Fragerunden).

Diese Datei ist die **einzige fachliche Quelle**. Die Phasen-Prompts
(`prompts/phase-*.md`) verweisen auf die Abschnitte dieser Datei und
wiederholen sie nicht.

---

## 0. Verbindliche Rahmenbedingungen

### 0.1 Paket

| Punkt | Wert |
| --- | --- |
| Composer-Paket | `heimrichhannot/contao-qna-bundle` |
| Composer-Type | `contao-bundle` |
| PHP-Namespace | `HeimrichHannot\QnaBundle\` → `src/` |
| Test-Namespace | `HeimrichHannot\QnaBundle\Tests\` → `tests/` |
| Bundle-Klasse | `HeimrichHannotQnaBundle` |
| Lizenz | LGPL-3.0-or-later (`LICENSE` liegt bereits im Repository) |
| PHP | `^8.4` — bewusst über der Contao-5.7-Mindestanforderung (8.3) |
| Contao | `^5.7` |
| Symfony | passend zur Contao-5.7-Baseline (7.x) |

Sprache im Repository: **Englisch** für Code, Kommentare, Commit-Nachrichten,
README, Klassennamen. Deutsch existiert ausschließlich als Übersetzungsdatei.

### 0.2 API-Verifikationspflicht

Contao 5.7 ist neuer als das Trainingswissen der meisten Modelle. Deshalb gilt
ausnahmslos:

1. Installiere zu Beginn `contao/core-bundle:^5.7` als Dev-Abhängigkeit des
   Pakets, damit die echten Sourcen unter `vendor/contao/core-bundle/src/`
   vorliegen.
2. **Jede** verwendete Contao-Klasse, -Methode, -Attribut-Signatur und
   -Konstante wird vor Verwendung im `vendor/`-Verzeichnis nachgelesen
   (`grep -r`, dann Datei öffnen).
3. Klassen, Methoden oder Attribut-Parameter, die dort nicht auffindbar sind,
   werden **nicht erfunden**. Stattdessen: dokumentierte Alternative verwenden
   und den Punkt in `DECISIONS.md` unter „Nicht verifizierbar" eintragen.
4. Für jede zentrale API-Entscheidung wird in `DECISIONS.md` der konkrete
   Dateipfad in `vendor/` als Beleg notiert.

### 0.3 Ehrlichkeitsregel für Checks

Behaupte nie, einen Check ausgeführt zu haben. Jeder im Bericht genannte Check
enthält die tatsächlich abgesetzte Kommandozeile und die reale Ausgabe (gekürzt).
Nicht ausführbare Checks werden als **nicht verifiziert** gekennzeichnet — das
ist ein akzeptables Ergebnis, eine erfundene Erfolgsmeldung nicht.

### 0.4 Abgrenzung zum Host-Projekt

Die gesamte Funktionalität liegt im Composer-Paket. Kein Code, keine DCA-Datei,
kein Template, kein Routing-Eintrag und keine Service-Definition wird in einem
Host-Projekt angelegt oder verändert. Ein vorhandenes Contao-Projekt darf
ausschließlich als Integrationsumgebung dienen, eingebunden über ein lokales
Composer-`path`-Repository.

Falls eine Host-Konfiguration technisch unvermeidbar ist, wird sie im README
unter „Voraussetzungen" dokumentiert — und zuvor ernsthaft versucht,
sie zu vermeiden.

### 0.5 Integrationsumgebung

Für Integrationstests steht ein lauffähiges Contao-5.7-Projekt bereit:
**DDEV-Projekt `contao0507.contao`**, in dem die Erweiterung per Symlink
eingebunden ist. Änderungen am Paket wirken dort sofort.

Konsequenzen:

* Alle Kommandos laufen **im Container**, nicht auf dem Host — also
  `ddev exec …` bzw. `ddev php`, `ddev composer`, `ddev mysql`. Datenbank und
  PHP-Umgebung sind vom Host aus nicht erreichbar.
* Nach Änderungen an DCA, Service-Definitionen, Routen oder Übersetzungen wird
  der Contao-Cache geleert, sonst sind die Änderungen nicht wirksam.
* Damit sind **echte** Integrationsprüfungen möglich: Datenbankschema,
  Container, Routen, Backend-Verwaltung, Frontend-Ausgabe. Diese Checks werden
  ausgeführt und nicht als „nicht verifiziert" abgehakt.
* **Testdaten** im DDEV-Projekt anzulegen ist ausdrücklich erlaubt und
  erwünscht: Seiten, Layouts, Fragerunden, Mitglieder, Mitgliedergruppen. Das
  ist keine Verletzung von §0.4 — dort geht es um Anwendungs*code*, nicht um
  Inhalte einer Testinstanz.
* Der symlinkbasierte Einbindungsweg ist reine Entwicklungsinfrastruktur und
  gehört nicht ins ausgelieferte Paket.

---

## 1. Fachliches Ziel

Fragerunden bei Veranstaltungen und Podiumsdiskussionen.

Funktionsumfang:

1. Fragerunden (Sessions) im Contao-Backend verwalten
2. Content Element: Liste aller veröffentlichten Fragerunden
3. Content Element: Reader für eine einzelne Fragerunde
4. Fragen von eingeloggten Frontend-Mitgliedern
5. Upvotes auf Fragen
6. Bühnenansicht als eigener Contao-Seitentyp
7. Start/Ende einer Fragerunde aus der Bühnenansicht
8. Live-Aktualisierung über Hotwire Turbo (Polling, keine WebSockets)

### 1.1 Nicht-Ziele

Ausdrücklich **nicht** implementieren: Moderationsansicht, Fragenfreigabe,
anonyme Nutzer, Downvotes, Vote-Rücknahme, Antworten auf Fragen,
„beantwortet"-Status, WebSockets, Mercure, Benachrichtigungen, Export,
Session-Auswahl im Reader oder in der Bühnenseite, Frontend-Module, React,
Vue, eigene Benutzerverwaltung.

Eine während einer laufenden Fragerunde eingereichte Frage erscheint sofort.
Das ist eine bewusste Entscheidung — die Gegenmaßnahmen dazu stehen in
Abschnitt 4.4.

---

## 2. Datenmodell

Das Schema bringt die Erweiterung selbst mit (DCA-`SQL`-Definitionen, bei
Bedarf ergänzt um Contao-Migrationsklassen im Bundle). Keine manuell
auszuführende SQL-Datei als Installationsweg.

### 2.1 `tl_qna_session`

| Feld | Typ | Anmerkung |
| --- | --- | --- |
| `id` | int, PK | |
| `tstamp` | int | Contao-Standard |
| `title` | varchar(255) | |
| `alias` | varchar(255) | eindeutig, Contao-Alias-Erzeugung |
| `published` | char(1) | Contao-Boolean |
| `state` | varchar(16) | `waiting` \| `open` \| `closed`, Default `waiting` |
| `startedAt` | int, nullable | |
| `endedAt` | int, nullable | |

Indizes: `UNIQUE(alias)`, Index auf `published`.

`published` steuert die Sichtbarkeit, `state` die Laufzeit. Beide werden
nirgends vermischt.

Nur veröffentlichte Sessions dürfen in Listen erscheinen, über Reader oder
Bühne erreichbar sein und Fragen oder Votes entgegennehmen.

### 2.2 `tl_qna_question`

| Feld | Typ | Anmerkung |
| --- | --- | --- |
| `id` | int, PK | |
| `tstamp` | int | |
| `pid` | int | → `tl_qna_session.id` |
| `memberId` | int | → `tl_member.id` |
| `question` | text | |
| `createdAt` | int | fachlicher Erstellungszeitpunkt |

Indizes: `pid`, `createdAt`, kombinierter Index `(pid, createdAt)`.

Kind von `tl_qna_session` über `pid` (siehe Abschnitt 2.4).

Kein `published`, keine Moderation, kein Freigabestatus.

### 2.3 `tl_qna_vote`

| Feld | Typ | Anmerkung |
| --- | --- | --- |
| `id` | int, PK | |
| `tstamp` | int | |
| `pid` | int | → `tl_qna_question.id` |
| `memberId` | int | → `tl_member.id` |
| `createdAt` | int | |

Indizes: `pid`, `memberId`, **`UNIQUE(pid, memberId)`**.

Kind von `tl_qna_question` über `pid` (siehe Abschnitt 2.4).

Die Eindeutigkeit muss auf Datenbankebene garantiert sein, nicht nur im
PHP-Code. Ein Duplicate-Vote wird sauber abgefangen (Abschnitt 4.3).

### 2.4 Referentielle Integrität

Die Kaskade innerhalb der Q&A-Tabellen erledigt Contao selbst, sobald die
Eltern-Kind-Beziehungen im DCA korrekt deklariert sind. `DC_Table` folgt beim
Löschen den `ctable`-Angaben rekursiv über `pid`. Ein eigener
`ondelete_callback` ist dafür **nicht** nötig und wäre eine überflüssige
Doppelung.

Erforderlich sind also lediglich die Deklarationen:

| Tabelle | `config` |
| --- | --- |
| `tl_qna_session` | `ctable = ['tl_qna_question']` |
| `tl_qna_question` | `ptable = 'tl_qna_session'`, `ctable = ['tl_qna_vote']` |
| `tl_qna_vote` | `ptable = 'tl_qna_question'` |

Damit gilt automatisch: Löschen einer Session entfernt ihre Fragen und über
die zweite Stufe deren Votes; Löschen einer Frage entfernt ihre Votes.

Nicht abgedeckt ist das Löschen eines `tl_member`, weil die Mitgliedschaft
über `memberId` und nicht über `pid` abgebildet ist. Nur dafür ist eine eigene
Behandlung nötig (siehe D3): Löschen oder Anonymisieren der betroffenen Fragen
und Votes. Die gewählte Variante gehört ins README — sie ist
datenschutzrelevant.

Erfasst werden müssen **alle** Wege, auf denen ein Mitglied verschwindet:

1. **Backend-Löschung** über `DC_Table` — `ondelete_callback` auf `tl_member`.
2. **Kontoschließung im Frontend** — der `closeAccount`-Hook erhält
   `($intId, $strMode, $objModule)`. Der Modus unterscheidet Deaktivieren von
   Löschen; die Q&A-Daten werden **ausschließlich** im Löschmodus entfernt
   (`close_delete`). Bei einer Deaktivierung bleiben Fragen und Votes erhalten,
   weil das Konto weiter existiert.
3. **Das entsprechende Event**, falls Contao 5.7 zusätzlich zum Hook eines
   bereitstellt — im `vendor/`-Code prüfen und, falls vorhanden, dem Hook
   vorziehen. Beide Wege dürfen nicht doppelt löschen.

Verifiziere die tatsächlichen Modus-Konstanten im `vendor/`-Code statt sie aus
der Dokumentation älterer Contao-Versionen zu übernehmen.

Nicht erfasst bleiben Löschungen, die keinen dieser Wege nehmen — etwa direkte
SQL-Eingriffe, CLI-Werkzeuge oder fremde DSGVO-Erweiterungen mit eigenem
Löschpfad. Das gehört als Einschränkung ins README.

### 2.5 Vote-Zählung

Es gibt **keine** Spalte `vote_count` im Ausgangsschema. Die Zählung erfolgt
per Aggregation im Gateway. Falls sich im Verlauf zeigt, dass eine
denormalisierte Zählerspalte nötig ist, ist das eine bewusste Entscheidung
(D4) mit Konsequenzen für Index und Race-Condition-Behandlung — inklusive
Migration und Dokumentation.

---

## 3. Statusmaschine

```
waiting --start--> open --stop--> closed
```

* Neue Session: `state = waiting`
* Start: `state = open`, `startedAt = jetzt`
* Ende: `state = closed`, `endedAt = jetzt`
* `closed` ist final. `closed → open` ist verboten und wird serverseitig
  abgewiesen.

Alle Übergänge werden ausschließlich im `SessionService` validiert und
ausgeführt, nie im Controller, DCA oder Template.

---

## 4. Sicherheit

### 4.1 Authentifizierung

Jede schreibende Aktion prüft serverseitig einen authentifizierten
Contao-`FrontendUser` über Symfony Security. Das Ausblenden eines Buttons ist
niemals eine Absicherung.

`memberId` stammt **immer** aus dem Security-Kontext. Eine `memberId` aus dem
Request wird nie akzeptiert — auch nicht als Fallback.

### 4.2 Autorisierung

Start und Stopp einer Fragerunde sind steuernde Aktionen. Es genügt nicht,
sich allein auf den Contao-Seitenschutz zu verlassen.

Implementiere einen eigenen Symfony Security Voter mit einem Attribut wie
`QNA_SESSION_CONTROL`, das im Controller geprüft wird. Standardverhalten des
Bundles: jeder authentifizierte Frontend-User, der die geschützte Bühnenseite
erreicht, darf steuern. Der Voter existiert, damit Host-Projekte das ohne
Fork verschärfen können. Dokumentiere den Erweiterungspunkt im README.

Kein projektspezifisches Berechtigungssystem voraussetzen.

### 4.3 CSRF und Race Conditions

Alle mutierenden Requests (Frage, Vote, Start, Stopp) laufen ausschließlich
über POST und sind über das bestehende Contao-/Symfony-CSRF-System geschützt.
Keine selbst erfundenen Tokens. Turbo-Formulare senden den Token korrekt mit.

Beim Voting gilt: Unique Constraint zuerst, dann die
`UniqueConstraintViolationException` abfangen, dann den aktuellen Vote-Zustand
rendern. Aus Nutzersicht ist die Operation idempotent. Ein paralleler
Doppelklick erzeugt nie einen 500er.

### 4.4 Missbrauchsschutz

Ohne Moderation braucht die Erweiterung mechanische Grenzen:

* maximale Fragenlänge (Vorschlag: 500 Zeichen), serverseitig validiert
* leere bzw. nur aus Whitespace bestehende Fragen ablehnen
* Cooldown pro Mitglied und Session (Vorschlag: eine Frage alle 20 Sekunden),
  serverseitig
* Ausgabe konsequent escapen, kein HTML in Fragen
* administratives **Löschen** einzelner Fragen im Backend ist erlaubt und
  erwünscht — das ist keine Moderationsansicht, sondern eine Notbremse

Die konkreten Werte sind zentral konfigurierbar (Abschnitt 10.4).

---

## 5. Content Elements

Keine Frontend-Module. Ausgabe über `AbstractContentElementController` mit
`#[AsContentElement(...)]` und Twig-Templates.

Eigene Kategorie `qna` inklusive Übersetzung. Keine Abhängigkeit von einer
projektspezifischen Kategorie.

Alle benötigten `tl_content`-DCA-Erweiterungen liegen im Bundle.

### 5.1 `qna_session_list` — `QnaSessionListController`

Zeigt alle veröffentlichten Sessions: Titel, optional Status, Link zur
Detailansicht.

Konfiguration im Backend: **ausschließlich** die Reader-/Weiterleitungsseite.
Keine Session-Auswahl.

Links werden über die Contao-/Symfony-URL-Generierung erzeugt, nicht per
String-Konkatenation.

### 5.2 `qna_session_reader` — `QnaSessionReaderController`

Keine Session-Auswahl im Backend. Die Session wird ausschließlich über den
Item-Parameter der URL aufgelöst:

```
/fragerunden/mobilitaet-der-zukunft  →  tl_qna_session.alias
```

Zu verifizieren (D5): der in Contao 5.7 vorgesehene Weg, den Item-Parameter zu
lesen **und als verwendet zu markieren** — inklusive des Zusammenspiels mit
`tl_page.requireItem` und der Regel, dass nicht verbrauchte Parameter zu 404
führen. Der Parameter darf nur dort als verwendet markiert werden, wo der
Reader tatsächlich eingesetzt ist.

404 (`PageNotFoundException`) bei: fehlendem Item-Parameter, unbekanntem
Alias, unveröffentlichter Session. Keine stille leere Ausgabe.

Klarzustellen und zu testen: Verhalten, wenn Listen- und Reader-Element auf
derselben Seite liegen.

### 5.3 Reader-Verhalten nach Status

**`waiting`** — Titel und Hinweis, dass die Fragerunde noch nicht begonnen
hat. Kein Formular, keine Votes.

**`open`** — Titel, Eingabeformular, Fragenliste mit Vote-Zahl und
Vote-Button, Kennzeichnung bereits abgegebener Votes.

**`closed`** — Fragen und Vote-Zahlen bleiben sichtbar, Formular und
Vote-Buttons entfallen, Hinweis auf das Ende der Fragerunde.

### 5.4 Sortierung Teilnehmeransicht

```sql
ORDER BY vote_count DESC, createdAt ASC
```

Bei gleicher Vote-Zahl steht die früher gestellte Frage vorn.

---

## 6. Bühnenansicht (Page Type `qna_stage`)

### 6.1 Page Controller

Eigener Contao-Seitentyp über `#[AsPage(...)]`. Kein Content Element, kein
Frontend-Modul. Alle nötigen `tl_page`-Anpassungen, Übersetzungen und
Service-Definitionen liegen im Bundle.

Die Seite dient gleichzeitig als Übersicht und als Reader:

```
/buehne                          → Übersicht aller veröffentlichten Sessions
/buehne/mobilitaet-der-zukunft   → Bühnenansicht dieser Session
```

Umsetzung über einen Page Controller mit optionalem Routenparameter.

**Wichtig:** Verwende **nicht** `auto_item` als Parameternamen im
`#[AsPage(path: ...)]` — die Contao-Dokumentation warnt vor der Kollision mit
reservierten Namen des Page-Routings. Nimm einen eigenen Namen, z. B.
`{alias}` mit `defaults: ['alias' => '']`, und lies ihn als Controller-Argument
statt über die Legacy-`Input`-API.

URL-Erzeugung über `PageRoute::PAGE_BASED_ROUTE_NAME` via
`UrlGeneratorInterface` oder über `PageModel::getFrontendUrl([...])` (seit
Contao 5.3 mit Parameter-Array möglich).

Explizit zu testen: optionaler Parameter in Kombination mit dem URL-Suffix des
Website-Roots — also dass `/buehne.html` **und** `/buehne/alias.html` beide
korrekt auflösen.

### 6.2 Content Composition

Contao 5.7 stellt dafür den Service
`Contao\CoreBundle\ContentComposition\ContentComposition` bereit. Die
öffentliche Doku beschreibt ihn zum Zeitpunkt dieser Spec noch nicht — die
Dokumentation dazu liegt als Draft-PR im `contao/docs`-Repository
(PR #1728, Milestone 5.7). Das Muster lautet:

```php
#[AsPage]
readonly class ExamplePageController
{
    public function __construct(private ContentComposition $contentComposition)
    {
    }

    public function __invoke(PageModel $pageModel): Response
    {
        $layoutTemplate = $this->contentComposition
            ->createContentCompositionBuilder($pageModel)
            ->buildLayoutTemplate()
        ;

        $layoutTemplate->setSlot('main', '<p>Hello World!</p>');

        return $layoutTemplate->getResponse();
    }
}
```

Das ist der vorgesehene Weg für den Bühnen-Seitentyp: Der Page Controller
rendert seine Twig-Ausgabe zu einem String und setzt sie in den `main`-Slot.
Header, Footer und alle übrigen Slots kommen weiterhin aus dem gewählten
Seitenlayout.

Der Seitentyp wird dabei mit **`contentComposition: false`** registriert. Mit
`true` könnten Redakteure der Bühnenseite Artikel zuweisen, deren Inhalt der
Controller anschließend beim Setzen des `main`-Slots überschreibt — der Inhalt
verschwände ohne Fehlermeldung und ohne Hinweis im Backend. Mit `false` bietet
Contao die Artikelverwaltung für diesen Seitentyp gar nicht erst an.

Zu verifizieren: dass `createContentCompositionBuilder()` und
`buildLayoutTemplate()` auch bei `contentComposition: false` das
Layout-Template samt Slots liefern. Der Schalter steuert die Artikelzuweisung,
nicht den Layoutaufbau — das ist aber im `vendor/`-Code zu belegen und real zu
testen, weil davon der gesamte Renderpfad abhängt.

Vorgehen:

1. Verifiziere Klasse, Service-ID und Methodensignaturen im `vendor/`-Code
   (`vendor/contao/core-bundle/src/ContentComposition/`) — die API ist noch
   nicht dokumentiert, das Beispiel oben stammt aus einer Draft-PR und kann
   im Detail abweichen. Prüfe insbesondere, ob `setSlot()` einen String
   erwartet oder auch ein Template-Objekt akzeptiert.
2. Nur falls die Klasse dort wider Erwarten nicht existiert: Fallback auf ein
   eigenes Twig-Layout, dokumentiert als Abweichung.
3. Ergebnis, geprüfte Dateipfade und die tatsächlichen Signaturen nach
   `DECISIONS.md` (D1).

Da die API undokumentiert und neu ist, gilt sie als Bruchrisiko bei
Minor-Updates. Kapsle sie deshalb vollständig im Page Controller — genau eine
Stelle im Paket kennt `ContentComposition`. Die Q&A-Geschäftslogik und die
Templates dürfen nichts davon wissen.

Zusätzlich ist die API im Core als `@experimental` markiert. BC-Brüche in
Minor-Releases sind damit ausdrücklich erlaubt — ein weiterer Grund für die
Kapselung in genau einer Klasse und ein Punkt für die bekannten
Einschränkungen im README.

#### Fallback für Layouts ohne Twig-Slots

Slots existieren nur im modernen Twig-Seitenlayout. Der
`ContentCompositionBuilder` bricht bei einem klassischen Layout ab. Da das
Host-Projekt die Layout-Wahl trifft, darf die Bühnenseite daran nicht
scheitern: der Page Controller erkennt den Fall und geht dann den Legacy-Weg.

Dieser registriert temporär einen `generatePage`-Hook, rendert die Seite über
`FrontendIndex` und belegt im Hook die Section `main`:

```php
public function executeRender(PageModel $pageModel, array $arguments = []): Response
{
    $this->arguments = $arguments;
    $GLOBALS['TL_HOOKS']['generatePage']['qna_stage'] = [self::class, 'renderPageContent'];
    $response = (new FrontendIndex())->renderPage($pageModel);
    unset($GLOBALS['TL_HOOKS']['generatePage']['qna_stage']);

    return $response;
}

public function renderPageContent(
    PageModel $pageModel,
    LayoutModel $layout,
    PageRegular $pageRegular,
): void {
    $pageRegular->Template->main = $this->getContent($pageModel, $this->arguments);
}
```

Anforderungen dazu:

* Beide Wege liegen in **derselben** Klasse wie die
  `ContentComposition`-Anbindung. Es gibt genau eine Stelle im Paket, die
  Layout-Mechanik kennt.
* Prüfe im `vendor/`-Code, **wie** der Builder ein nicht-modernes Layout
  signalisiert (Exception, `null`, eigene Prüfmethode) — davon hängt die
  Weichenstellung ab. Eine Prüfung vor dem Aufruf ist einem gefangenen
  Exception-Pfad vorzuziehen.
* Der Hook wird nach dem Rendern **immer** wieder entfernt, auch im
  Fehlerfall.
* Ein Trait ist nicht erforderlich; eine private Methode im Page Controller
  genügt, solange nur dieser eine Controller sie braucht.
* Beide Pfade gehören in die Tests: moderne Slots und klassisches Layout.

### 6.3 Bühnenansicht nach Status

**Übersicht** — alle veröffentlichten Sessions mit Titel, Status und Link zur
Detailansicht. Nicht veröffentlichte Sessions erscheinen nicht.

**`waiting`** — Titel, Hinweis, Button „Fragerunde starten".

**`open`** — Laufindikator, Sortierumschaltung, Fragenliste mit Vote-Zahlen,
Button „Fragerunde beenden".

**`closed`** — Fragenliste und Vote-Zahlen, keine Möglichkeit zum Neustart.

Unbekannter oder unveröffentlichter Alias → 404.

### 6.4 Sortierung Bühne

Query-Parameter `?sort=votes` (Default) und `?sort=time`. Andere Werte werden
auf `votes` normalisiert.

```sql
votes: ORDER BY vote_count DESC, createdAt ASC
time:  ORDER BY createdAt ASC
```

Die gewählte Sortierung bleibt bei Turbo-Aktualisierungen erhalten. Das
bedeutet konkret: der Sortierparameter wird in die `src` des Turbo-Frames
übernommen und vom Frame-Endpunkt serverseitig ausgewertet.

---

## 7. Turbo, Polling und Assets

### 7.1 Turbo-Verfügbarkeit

Contao bringt Turbo seit 5.4 für das **Backend** mit. Im Frontend ist Turbo
nicht automatisch aktiv. Prüfe das im `vendor/`-Code und entscheide (D2):

* Turbo als Bundle-Asset ausliefern (Version pinnen), **oder**
* Turbo als dokumentierte Voraussetzung im README deklarieren.

Bevorzugt wird die erste Variante, damit die Erweiterung selbsttragend bleibt.

**Kritische Nebenwirkung:** Wird Turbo im Frontend geladen, übernimmt Turbo
Drive sämtliche Links und Formulare des Host-Projekts und kann dort
vorhandenes JavaScript brechen. Deshalb verbindlich:

* Turbo Drive deaktivieren (`Turbo.session.drive = false` oder gleichwertig)
* nur Turbo **Frames** und **Streams** verwenden
* das Skript nur auf Seiten laden, auf denen Q&A-Ausgabe existiert

**Vorhandene Turbo-Instanz respektieren:** Bringt das Host-Projekt bereits
Turbo mit, darf das Bundle weder eine zweite Instanz laden noch global
`drive` abschalten — sonst legt eine einzelne Q&A-Seite die Turbo-Navigation
des ganzen Projekts still. Das Bundle-Modul prüft deshalb vor dem Import, ob
`window.Turbo` existiert, und nutzt in dem Fall die vorhandene Instanz
unverändert. Frames und Streams funktionieren damit genauso. Das gehört ins
README.

### 7.2 Turbo Frames

Dynamische Bereiche werden in `<turbo-frame>` gekapselt, mit stabilen IDs wie
`qna-session-42-questions`.

Damit `frame.reload()` funktioniert, **muss** der Frame ein `src`-Attribut auf
einen eigenen Frame-Endpunkt besitzen. Ein Frame ohne `src` lässt sich nicht
neu laden — das ist der häufigste Implementierungsfehler in diesem Aufbau.

Empfohlen: `loading="lazy"` mit neutralem, nicht member-spezifischem
Initial-Markup, damit die umgebende Seite cachebar bleibt (Abschnitt 8).

### 7.3 Polling

Turbo pollt nicht von selbst. Die Erweiterung liefert ein **sehr kleines
eigenständiges ES-Modul** mit, das Frames in einem Intervall neu lädt. Es liegt
auslieferbar unter `public/` und wird über das von Contao registrierte
Asset-Paket eingebunden (`AddAssetsPackagesPass`, Auslieferungspfad
`bundles/<bundle-name>`). Keine Abhängigkeit von einem `app.js` des
Host-Projekts, keine Anpassung eines fremden Build-Systems, kein zusätzliches
Framework.

Anforderungen:

* Default-Intervall 2500 ms, zentral konfiguriert, keine Magic Number
* Polling pausiert bei `document.hidden`
* längeres Intervall in den Zuständen `waiting` und `closed`
* exponentielles Backoff bei Fehlerantworten
* Timer werden beim Entfernen des Elements aufgeräumt
* keine doppelten Timer nach Turbo-Visits
* nur Frames pollen, die im DOM vorhanden sind
* keine Geschäftslogik im JavaScript

**Lastabschätzung gehört ins README:** 2500 ms Intervall × Zuschauerzahl ergibt
die Requests pro Sekunde auf nicht cachebare Endpunkte. Prüfe, ob die
Frame-Endpunkte `ETag`/`304` unterstützen können, um die Last zu senken.

### 7.4 Formaktionen

Frage stellen, Vote abgeben, Session starten, Session beenden:

```
POST → serverseitige Mutation → Turbo-Stream-Antwort → betroffene Frames aktualisieren
```

Ein sauberer Post/Redirect/Get-Flow ist zulässig, wenn er mit Contao robuster
funktioniert. Einfache serverseitige Lösungen sind ausdrücklich erwünscht,
umfangreiche clientseitige State-Verwaltung nicht.

Nach dem Stellen einer Frage: Liste aktualisieren, Formular leeren,
Erfolgsmeldung möglich. Nach einem Vote: Zahl aktualisieren, Button als
gewählt markieren, Liste ggf. neu sortieren. Nach Start/Stopp: Bühnenstatus
aktualisieren; der Teilnehmer-Reader wechselt beim nächsten Poll automatisch.

Der Server ist immer die Source of Truth.

### 7.5 Endpunkte

Semantische HTML-/Turbo-Endpunkte, keine JSON-API. Alle Routen registriert das
Bundle selbst, im eigenen Namensraum:

```
contao_qna_reader_frame       GET
contao_qna_stage_questions    GET
contao_qna_question_create    POST
contao_qna_vote_create        POST
contao_qna_session_start      POST
contao_qna_session_stop       POST
```

Die Routen müssen im Contao-Frontend-Scope laufen (Scope-Konfiguration von
5.7 im `vendor/`-Code prüfen).

---

## 8. Caching

Folgendes darf niemals aus einem öffentlich gecachten Fragment stammen:
Vote-Status des aktuellen Mitglieds, CSRF-Token, aktueller Session-Status,
aktuelle Votes, aktuelle Fragen.

**Entscheidend ist die Trennung von Fragment und Frame-Route.** Contao führt
die Cache-Control-Header von Fragment-Antworten in die Hauptantwort zusammen
(`AbstractFragmentController`, `SubrequestCacheSubscriber`). Setzt also das
Content Element selbst `private, no-store`, verliert die **gesamte Seite** den
Page-Cache — auf einer Seite mit weiteren Inhalten ein unnötiger Schaden.

Daraus folgen zwei verbindliche Regeln:

* **Das Content-Element-Fragment bleibt cachebar.** Sein Render ist neutral:
  kein Vote-Status des Mitglieds, kein CSRF-Token, kein aktueller
  Session-Status. Es setzt keine restriktiven Cache-Header und markiert sich
  nicht als nicht cachebar.
* **Nur die eigenständigen Frame- und Aktions-Routen** liefern
  `private, no-store`. Sie sind keine Fragmente, laufen nicht als Subrequest
  und ihre Header werden deshalb nicht in eine Seitenantwort gemischt.

Der member-spezifische Inhalt erreicht die Seite ausschließlich über den lazy
geladenen Frame. Das ist keine Stilfrage, sondern die einzige Konstellation,
in der Seiten-Cache und korrekte, member-spezifische Live-Daten gleichzeitig
funktionieren.

Lässt sich das für einen Fall nicht durchhalten, wird das Fragment bewusst als
nicht cachebar geführt — dann aber mit ausdrücklichem Hinweis im README, dass
Seiten mit diesem Element nicht mehr im Page-Cache landen.

Das äußere Seitenlayout funktioniert unabhängig davon normal.

---

## 9. Services und Datenzugriff

Geschäftslogik gehört weder in Twig noch in DCA-Callbacks, JavaScript oder
Controller. Controller bleiben dünn.

```
QnaSessionGateway   QnaQuestionGateway   QnaVoteGateway
SessionService      QuestionService      VoteService
```

Die drei `*Gateway`-Klassen kapseln je genau eine Tabelle: SQL, Aggregationen
und Transaktionen. Bewusst nicht `*Repository`, weil dieser Name in
Contao- und Symfony-Bundles Doctrine-Entity-Repositories erwarten lässt (siehe
`vendor/contao/core-bundle/src/Repository/`) — hier gibt es weder Entities noch
`EntityManager`.

* **SessionService** — Session starten und beenden, Statusübergänge validieren
* **QuestionService** — Session validieren, authentifizierten Member
  verwenden, Länge/Cooldown prüfen, Frage erstellen
* **VoteService** — Session und Frage validieren, authentifizierten Member
  verwenden, Unique-Vote garantieren, Race Conditions behandeln

### 9.1 Persistenz

Entschieden (D6): **Doctrine DBAL**, gekapselt in den drei Gateway-Klassen.
Also `Doctrine\DBAL\Connection`, SQL und Query Builder innerhalb dieser
Klassen. Keine Contao-Models für die Q&A-Tabellen, keine `#[ORM\Entity]`,
kein `EntityManager`.

Die Tabellendefinition bleibt beim **DCA** (`fields.*.sql`, `config.sql.keys`)
— sie ist die einzige Schemaquelle, es gibt kein konkurrierendes ORM-Mapping.

Keine zweite Persistenzabstraktion heißt: für dieselbe Tabelle nicht zusätzlich
Contao-Models einführen. Davon ausgenommen ist das Backend — dort schreibt
`DC_Table` naturgemäß selbst, und die Löschkaskade läuft über `ptable`/`ctable`
(§2.4). Das ist kein Verstoß und wird nicht umgebaut.

### 9.2 Query-Effizienz

Die Fragenliste braucht Frage, Vote-Zahl und Vote-Status des aktuellen
Mitglieds. Verboten ist das Muster „1 Query Fragen + N Queries Zählung +
N Queries Vote-Status". Stattdessen JOINs, Aggregationen oder gebündelte
Queries. Keine SQL-String-Konkatenation mit Request-Daten.

---

## 10. Backend, Templates, Übersetzungen, Konfiguration

### 10.1 Backend

Eigener Backend-Bereich „Q&A" mit der Verwaltung „Fragerunden", vom Bundle
selbst registriert. Keine Abhängigkeit von einem Backend-Modul des
Host-Projekts.

Redaktionell bedienbar: Titel, Alias, Veröffentlicht.
`published` mit Contao-typischer Toggle-Funktion.

Die Laufzeitfelder `state`, `startedAt`, `endedAt` werden ausschließlich über
die Bühnensteuerung gesetzt und sind im Edit-Formular nicht editierbar
(read-only oder entfernt).

Eine administrative, read-only Liste der Fragen mit Löschmöglichkeit
(Abschnitt 4.4) ist erwünscht. Kein Moderationsfeature, kein Freigabestatus.

### 10.2 Templates

Moderne Twig-Templates, keine `.html5`-Templates.

**Ablageort ist `contao/templates/`, nicht `templates/`.** Nur das
Contao-Verzeichnis speist die verwaltete Template-Hierarchie
(`ContaoFilesystemLoader`, `TemplateLocator`) — daraus ergibt sich der
`@Contao`-Namespace und damit die Möglichkeit, dass Host-Projekte einzelne
Templates auf dem üblichen Contao-Weg überschreiben. Ein Bundle-Ordner
`templates/` erzeugt lediglich den Symfony-Namespace `@HeimrichHannotQna`;
darüber referenzierte Templates rendern zwar, sind für Redakteure und
Integratoren aber nicht überschreibbar.

```
contao/templates/
├── content_element/
│   ├── qna_session_list.html.twig
│   └── qna_session_reader.html.twig
└── qna/
    ├── reader_frame.html.twig
    ├── question_list.html.twig
    ├── question.html.twig
    ├── stage_overview.html.twig
    ├── stage_detail.html.twig
    └── stage_questions.html.twig
```

Referenziert wird über die Contao-Hierarchie, nicht über den
Symfony-Bundle-Namespace. Dass ein Host-Projekt ein Template überschreiben
kann, gehört zu den Pflicht-Tests.

Markup zwischen Reader und Bühne wird über gemeinsame Partials geteilt, nicht
dupliziert. Keine Geschäftslogik in Twig.

### 10.3 Barrierefreiheit und Styles

Echte `<button>`-Elemente, sinnvolle Labels, Tastaturbedienbarkeit, sichtbarer
Fokus, keine Information ausschließlich über Farbe. Ein bereits abgegebener
Vote ist im Markup eindeutig erkennbar (`aria-pressed="true"` plus Klasse).

Live-Regionen sparsam: `aria-live` gehört an gezielte Statusmeldungen, **nicht**
an die gepollte Fragenliste — sonst liest der Screenreader alle 2,5 Sekunden
die gesamte Liste vor.

Styles minimal, Klassen mit `qna-` genamespaced, keine aggressiven globalen
Selektoren, möglichst kein `!important`, kein CSS-Framework. Die Gestaltung
bleibt für das Host-Projekt überschreibbar.

### 10.4 Übersetzungen

Deutsch und Englisch für Backend-Labels, Content-Element-Namen,
Page-Type-Bezeichnung, Statuswerte, Buttons, Frontend-Hinweise,
Fehlermeldungen und Accessibility-Labels. Keine fest im PHP-Code stehenden
UI-Texte.

### 10.5 Konfiguration

Nur technische Parameter, minimal gehalten:

```yaml
contao_qna:
    polling_interval: 2500
    max_question_length: 500
    question_cooldown: 20
```

Fachliche Zuordnungen wie „aktive Session", „Reader-Session" oder
„Stage-Session" werden **niemals** global konfiguriert.

---

## 11. Fehlerverhalten

Saubere HTTP-Fehler statt stiller Fehler. 404 bei unbekanntem oder
unveröffentlichtem Alias und bei fehlendem erforderlichem Item-Parameter.
Passende 4xx-Antworten bei ungültigen Aktionen (z. B. Frage bei `closed`).

Für abgelehnte Formulare innerhalb eines Turbo-Frames gilt **`422
Unprocessable Entity`**. Das ist der Statuscode, bei dem Turbo die Antwort
rendert statt sie als Fehler zu behandeln. Bei anderen 4xx-Codes kann es sein,
dass der Frame nicht ersetzt wird und die Fehlermeldung den Nutzer nie
erreicht. Wo also eine fachliche Ablehnung mit sichtbarer Meldung im Frame
enden soll, ist 422 zu verwenden — hart abgewiesene Requests
(fehlende Authentifizierung, CSRF) behalten ihren eigentlichen Code.
Keine internen Exceptions oder Datenbankfehler in der Ausgabe. Produktionsfehler
über das normale Symfony-/Contao-Logging.

---

## 12. Tests und Qualität

### 12.1 Testzuschnitt

Pflichtabdeckung sind **Unit-Tests für alle Services** mit gemockten Gateways.
Sie laufen ohne Kernel und ohne Datenbank.

Darüber hinaus steht mit dem DDEV-Projekt aus §0.5 eine echte Umgebung samt
MySQL bereit. Deshalb gilt:

* Verhalten, das nur gegen eine echte Datenbank belastbar ist, wird dort
  geprüft — insbesondere der Unique-Constraint auf `tl_qna_vote`, das
  Duplicate-Vote-Verhalten und die Löschkaskade über `ptable`/`ctable`.
* Ergänzend bleibt die Assertion auf die DCA-/Schema-Definition sinnvoll, weil
  sie den Constraint auch ohne Datenbank absichert.
* Frontend-Verhalten (Frames, Statuscodes, Cache-Header) wird real über die
  Instanz geprüft, nicht nur behauptet.

Was sich trotz verfügbarer Umgebung nicht prüfen lässt, wird als **nicht
verifiziert** ausgewiesen. Eine aufwendige zweite Testinfrastruktur neben der
vorhandenen Instanz wird nicht gebaut.

### 12.2 Pflicht-Testfälle

**Session** — neue Session ist `waiting`; Start setzt `open` und `startedAt`;
Ende setzt `closed` und `endedAt`; `closed → open` wird abgewiesen.

**Published** — unveröffentlichte Session fehlt in Listen, ist über Reader und
Bühne nicht erreichbar, nimmt weder Fragen noch Votes an.

**Reader** — korrekter Item-Parameter lädt die richtige Session; fehlender und
unbekannter Alias führen zu 404; der Reader benötigt keine Konfiguration.

**Questions** — Frage nur bei `open`; nicht bei `waiting` oder `closed`;
`memberId` stammt ausschließlich aus dem Security-Kontext; Längenlimit und
Cooldown greifen.

**Votes** — erster Vote funktioniert; zweiter Vote desselben Mitglieds auf
dieselbe Frage erzeugt keinen zweiten Datensatz; dasselbe Mitglied kann andere
Fragen voten; verschiedene Mitglieder können dieselbe Frage voten; Vote nur bei
`open`; Unique-Constraint vorhanden; Duplicate-Vote erzeugt keinen 500er.

**Sortierung** — `votes` und `time` liefern die spezifizierte Reihenfolge.

**Mitgliederdaten** — Löschen eines Mitglieds entfernt seine Fragen und Votes;
eine Kontoschließung im **Deaktivierungsmodus** entfernt nichts.

**Stage** — Übersicht ohne Alias; Detail mit Alias; unbekannter Alias → 404;
Ausgabe funktioniert sowohl mit modernem Twig-Slot-Layout als auch über den
Legacy-Fallback.

**Turbo/HTTP** — Frame-Endpunkte, die vier POST-Aktionen, Authentifizierung,
CSRF, korrekter Response-Typ, `Cache-Control` der dynamischen Endpunkte.

### 12.3 Codequalität

PHP `strict_types`, Dependency Injection, `readonly` wo sinnvoll, kein
`System::getContainer()` als Service-Locator, PSR-4, konsistente Namespaces,
klar getrennte Verantwortlichkeiten.

Toolchain: PHPUnit, PHPStan, PHP-CS-Fixer. Wer sie hinzufügt, konfiguriert sie
vollständig und lässt sie laufen. Keine halb konfigurierte Toolchain
hinterlassen.

---

## 13. Zu treffende Entscheidungen

Diese Punkte sind bewusst offen und werden in `DECISIONS.md` beantwortet — je
mit Begründung und Beleg aus `vendor/`.

| ID | Entscheidung |
| --- | --- |
| D1 | Verifizierte Signaturen der `ContentComposition`-API, ggf. Fallback (6.2) |
| D2 | Turbo mitliefern oder als Voraussetzung deklarieren (7.1) |
| D3 | Verhalten bei Löschung eines `tl_member`: Löschen oder Anonymisieren (2.4) |
| D4 | Vote-Zählung per Aggregat oder denormalisierte Spalte (2.5) |
| D5 | Konkreter Mechanismus für Item-Parameter und 404-Verhalten (5.2) |
| D6 | ~~Contao-Models vs. DBAL~~ — entschieden: DBAL, siehe `DECISIONS.md` (9.1) |
| D7 | Turbo Streams vs. Post/Redirect/Get (7.4) |

---

## 14. Definition of Done

Ein eigenständig installierbares Composer-Paket:

```
contao-qna-bundle/
├── composer.json
├── LICENSE
├── README.md
├── DECISIONS.md
├── src/
├── config/
├── contao/          (DCA, config, templates/)
├── translations/
├── public/
└── tests/
```

Nach `composer require heimrichhannot/contao-qna-bundle` und der Contao-Datenbank-
aktualisierung stellt das Paket Tabellen, Backend-Verwaltung, Content Elements,
Stage Page Type, Controller, Services, Templates, Übersetzungen, Routen und
Assets selbst bereit. Keine manuelle Übertragung von Code in das Host-Projekt.

Das README dokumentiert: Voraussetzungen (PHP, Contao, Turbo), Installation,
Einrichtung (Sessions anlegen und veröffentlichen, List-Element, Reader-Seite,
Reader-Element, Stage-Seite mit Seitenschutz und Mitgliedergruppen),
URL-Beispiele, technische Architektur, den Erweiterungspunkt für die
Autorisierung, die Lastabschätzung des Pollings und alle bekannten
Einschränkungen.
