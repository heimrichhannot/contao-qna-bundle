# Phase 4 — Bühnen-Seitentyp, Turbo, Polling, Caching

Fortsetzung der Contao-5.7-Q&A-Erweiterung. Spezifikation: `SPEC.md`.
Bisherige Entscheidungen: `DECISIONS.md`. **Lies beides zuerst.**

Bei Widersprüchen zwischen den beiden Dateien gilt `SPEC.md`.

Diese Phase behandelt: SPEC.md Abschnitte 6, 7, 8.

Voraussetzung: Phasen 1–3 sind abgeschlossen.

Das ist die technisch riskanteste Phase. Arbeite hier besonders strikt nach der
Verifikationspflicht aus SPEC.md §0.2: keine API verwenden, die du nicht im
`vendor/`-Code gesehen hast.

---

## Aufgaben

### 1. Page Type `qna_stage`

Eigener Contao-Seitentyp über `#[AsPage(...)]` mit Page Controller. Kein
Content Element, kein Frontend-Modul. `tl_page`-Anpassungen, Palette,
Übersetzungen und Service-Definition liegen im Bundle.

Ein Controller bedient Übersicht und Detail:

```
/buehne                          Übersicht
/buehne/mobilitaet-der-zukunft   Detailansicht
```

Verwende einen **eigenen** Routenparameter (z. B. `{alias}` mit
`defaults: ['alias' => '']`), **nicht** `auto_item` — die Contao-Doku warnt
vor der Kollision mit reservierten Namen des Page-Routings. Lies den Parameter
als Controller-Argument, nicht über die Legacy-`Input`-API.

URL-Erzeugung über `PageRoute::PAGE_BASED_ROUTE_NAME` via
`UrlGeneratorInterface` oder `PageModel::getFrontendUrl([...])`.

**Explizit testen:** optionaler Parameter zusammen mit dem URL-Suffix des
Website-Roots — sowohl `/buehne.html` als auch `/buehne/alias.html` müssen
korrekt auflösen. Falls das nicht sauber funktioniert, dokumentiere die
gewählte Alternative in `DECISIONS.md`.

### 2. Content Composition (D1)

Contao 5.7 stellt dafür `Contao\CoreBundle\ContentComposition\ContentComposition`
bereit — das konkrete Muster steht in SPEC.md §6.2. `contentComposition` bleibt
aktiv, du renderst die Q&A-Ausgabe und setzt sie über `setSlot('main', …)`;
Header, Footer und übrige Slots kommen aus dem Seitenlayout.

Die API ist neu und öffentlich noch nicht dokumentiert. Deshalb zuerst:
Klasse, Service-ID und Methodensignaturen unter
`vendor/contao/core-bundle/src/ContentComposition/` nachlesen und mit dem
Beispiel in der Spec abgleichen. Prüfe insbesondere den erwarteten Typ von
`setSlot()`. Abweichungen dokumentieren, nicht improvisieren.

Die API ist im Core zusätzlich als `@experimental` markiert, BC-Brüche in
Minor-Releases sind also erlaubt. Kapsle sie deshalb vollständig im Page
Controller — genau eine Stelle im Paket kennt `ContentComposition`. Vermerke
das in den bekannten Einschränkungen des README.

Implementiere außerdem den **Fallback für Layouts ohne Twig-Slots** gemäß
SPEC.md §6.2: Erkennung des Layout-Typs, dann der Legacy-Weg über einen
temporär registrierten `generatePage`-Hook und `FrontendIndex::renderPage()`.
Prüfe zuerst im `vendor/`-Code, wie der Builder ein nicht-modernes Layout
signalisiert — eine Prüfung vor dem Aufruf ist besser als ein gefangener
Exception-Pfad. Der Hook wird danach immer entfernt, auch im Fehlerfall. Beide
Pfade liegen in derselben Klasse und werden beide getestet.

Trage die verifizierten Signaturen mit Dateipfad in `DECISIONS.md` ein.

### 3. Bühnenansicht

Übersicht und statusabhängige Detailansicht gemäß SPEC.md §6.3, Sortierung
gemäß §6.4. Die Fragenlisten-Partials aus Phase 3 werden wiederverwendet, nicht
dupliziert.

Start und Stopp ausschließlich per POST aus der Detailansicht, über den
`SessionService` aus Phase 2, geschützt durch CSRF und den Voter
`QNA_SESSION_CONTROL`. Ungültige Übergänge werden serverseitig abgewiesen.

### 4. Turbo-Endpunkte

Die Routen aus SPEC.md §7.5 registriert das Bundle selbst, im Frontend-Scope.
Keine JSON-API, sondern semantische HTML-/Turbo-Antworten.

Setze D7 um: Turbo Streams oder ein sauberer Post/Redirect/Get-Flow — was mit
Contao robuster funktioniert. Entscheidung begründen und dokumentieren.

Verhalten nach den Aktionen gemäß SPEC.md §7.4. Der Server bleibt Source of
Truth.

### 5. Turbo-Asset und Polling-Skript

Setze D2 um (SPEC.md §7.1): Turbo mitliefern oder als dokumentierte
Voraussetzung deklarieren. Bevorzugt mitliefern, Version gepinnt.

Falls mitgeliefert, ist die Nebenwirkung zwingend zu beherrschen: Turbo Drive
deaktivieren, nur Frames und Streams nutzen, das Skript nur auf Seiten mit
Q&A-Ausgabe laden. Andernfalls kapert Turbo die Navigation des gesamten
Host-Projekts.

Das Polling-Skript ist ein sehr kleines eigenständiges ES-Modul im Paket, ohne
Abhängigkeit von einem Host-Build. Anforderungen vollständig gemäß SPEC.md
§7.3 — insbesondere:

* jeder pollende Frame besitzt ein `src`-Attribut, sonst funktioniert
  `frame.reload()` nicht
* Pause bei `document.hidden`
* längeres Intervall bei `waiting` und `closed`
* exponentielles Backoff bei Fehlern
* Timer-Aufräumung, keine doppelten Timer nach Turbo-Visits
* Intervalle aus der Bundle-Konfiguration, keine Magic Numbers
* keine Geschäftslogik im JavaScript

Die Sortierauswahl der Bühne wird in die Frame-`src` übernommen und
serverseitig ausgewertet, damit sie über Aktualisierungen erhalten bleibt.

### 6. Caching

Gemäß SPEC.md §8. Beachte die Trennung: Contao mischt die Cache-Header von
Fragment-Antworten in die Hauptantwort, deshalb setzt das **Content-Element-
Fragment keine restriktiven Header** — sonst verliert die ganze Seite den
Page-Cache. `private, no-store` gilt ausschließlich für die eigenständigen
Frame- und Aktions-Routen, die keine Fragmente sind.

Der neutrale Initial-Render aus Phase 3 bleibt erhalten, der
member-spezifische Inhalt kommt über den lazy geladenen Frame. Kein CSRF-Token
in potenziell gecachtem Markup.

Ergänze im Bericht eine Lastabschätzung: Intervall × erwartete Zuschauerzahl =
Requests pro Sekunde auf nicht cachebare Endpunkte. Prüfe, ob die
Frame-Endpunkte `ETag`/`304` unterstützen können.

---

## Abnahmekriterien

1. Übersicht und Detail funktionieren über einen Page Controller; beide
   URL-Formen inklusive Suffix wurden geprüft.
2. Unbekannter oder unveröffentlichter Alias liefert 404.
3. Start und Stopp laufen nur über POST, mit CSRF und Voter; `closed → open`
   wird abgewiesen.
4. Jeder pollende Frame hat ein `src`; Turbo Drive ist deaktiviert.
5. Die dynamischen Endpunkte liefern `private, no-store`.
6. Keine im `vendor/`-Code unbelegte API wurde verwendet.

## Bericht

* Page-Type-Registrierung, Routing, geprüfte URL-Formen
* Entscheidung D1 mit Belegen, D2 und D7 mit Begründung
* Turbo-Mechanismus und Polling-Verhalten
* Cache-Strategie und Lastabschätzung
* ausgeführte Checks mit realer Kommandozeile und realer Ausgabe
* alles, was du nicht verifizieren konntest
