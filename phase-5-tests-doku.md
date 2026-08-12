# Phase 5 — Tests, Qualität, Dokumentation, Abnahme

Abschluss der Contao-5.7-Q&A-Erweiterung. Spezifikation: `SPEC.md`.
Entscheidungen: `DECISIONS.md`. **Lies beides zuerst.**

Bei Widersprüchen zwischen den beiden Dateien gilt `SPEC.md`.

Diese Phase behandelt: SPEC.md Abschnitte 12, 14.

Voraussetzung: Phasen 1–4 sind abgeschlossen.

---

## Aufgaben

### 1. Tests vervollständigen

Zuschnitt gemäß SPEC.md §12.1 — und halte dich daran. Baue **keine**
stundenlange Kernel-Testinfrastruktur, wenn keine MySQL-Instanz verfügbar ist.
Nicht ausführbare Testarten werden im Bericht als nicht verifiziert
ausgewiesen; das ist ein akzeptables Ergebnis.

Pflichtabdeckung ist die Liste in SPEC.md §12.2. Ergänze, was in den Phasen 2
bis 4 noch offen geblieben ist, insbesondere:

* Reader: fehlender, unbekannter und unveröffentlichter Alias
* Sortierung in beiden Varianten
* Stage: Übersicht ohne Alias, Detail mit Alias, unbekannter Alias → 404,
  Ausgabe mit modernem Twig-Slot-Layout und über den Legacy-Fallback
* Mitgliederdaten: Löschen entfernt Fragen und Votes, Kontoschließung im
  Deaktivierungsmodus entfernt nichts
* Turbo/HTTP: Frame-Endpunkte, vier POST-Aktionen, Authentifizierung, CSRF,
  Response-Typ, `Cache-Control` der dynamischen Endpunkte

Der Unique-Constraint wird über eine Assertion auf die Schema-Definition
belegt, das Duplicate-Verhalten über einen Service-Test mit gemockter
`UniqueConstraintViolationException`.

### 2. Toolchain finalisieren

PHPUnit, PHPStan und PHP-CS-Fixer vollständig konfigurieren und laufen lassen.
Gefundene Fehler beheben. Keine halb konfigurierte Toolchain hinterlassen.

Prüfe zusätzlich SPEC.md §12.3: `strict_types`, Dependency Injection,
`readonly` wo sinnvoll, kein `System::getContainer()` als Service-Locator,
konsistente Namespaces, keine Geschäftslogik in Templates oder JavaScript.

### 3. Integrationsprüfung

Falls ein lauffähiges Contao-5.7-Projekt im Workspace liegt, binde das Paket
über ein lokales Composer-`path`-Repository ein. **Kopiere den Extension-Code
nicht** in das Projekt. Die lokale Entwicklungsintegration gehört nicht in die
finale Extension.

Prüfe dann, jeweils mit realer Ausgabe: Bundle wird erkannt, Services werden
geladen, DCA wird geladen, Backend-Menü erscheint, Tabellen werden erkannt,
Content Elements sind auswählbar, Page Type ist auswählbar, Templates werden
gefunden, Assets werden geladen, Routen funktionieren, Stage Page Controller
funktioniert, Layout-Integration funktioniert.

Ist kein Projekt vorhanden, halte das im Bericht fest — und behaupte nichts
Gegenteiliges.

### 4. README

Vollständig gemäß SPEC.md §14:

* **Voraussetzungen** — PHP, Contao, Turbo (je nach Entscheidung D2)
* **Installation** — `composer require`, danach die Contao-Datenbank-
  aktualisierung
* **Einrichtung** — Sessions anlegen und veröffentlichen, List-Element
  anlegen, Reader-Seite anlegen, Reader-Element einfügen, Stage-Seite mit Page
  Type `qna_stage` anlegen, Stage-Seite über den Contao-Seitenschutz sichern,
  Mitgliedergruppen zuweisen
* **URL-Beispiele** — `/fragerunden`, `/fragerunden/<alias>`, `/buehne`,
  `/buehne/<alias>`
* **Technische Architektur** — Content Elements, Page Controller, Turbo
  Frames, Polling, Datenmodell, Authentifizierung, Caching, Unique Votes
* **Erweiterungspunkt Autorisierung** — wie ein Host-Projekt den Voter
  `QNA_SESSION_CONTROL` verschärft
* **Betrieb** — Lastabschätzung des Pollings
* **Bekannte Einschränkungen** — offen und ehrlich, inklusive der bewussten
  Entscheidung gegen Moderation und der dafür vorhandenen Gegenmaßnahmen
* **Notwendige Host-Konfiguration**, falls sich etwas nicht vom Bundle selbst
  bereitstellen ließ

### 5. Saubere Installation testen

Falls möglich, installiere das Paket einmal aus einem sauberen Zustand und
prüfe, dass keine manuelle Übertragung von Code ins Host-Projekt nötig ist.

---

## Abschlusschecks

Führe aus, soweit verfügbar, und protokolliere jeweils Kommandozeile und reale
Ausgabe:

```
composer validate
php -l (alle Dateien)
PHPUnit
PHPStan
PHP-CS-Fixer
Symfony-Container
Contao-Container
Datenbankschema
Routen
Autoloading
Bundle-Registrierung
```

Nicht ausführbare Checks werden als **nicht verifiziert** gekennzeichnet.

---

## Abschlussbericht

Kompakt, faktisch, ohne Marketing:

1. Paketname und Namespace
2. Bundle-/Extension-Struktur
3. Composer-Abhängigkeiten
4. implementierte Komponenten
5. Tabellen, Felder, Indizes
6. Content-Element-Typen
7. Page Type und Routing
8. Turbo-Mechanismus
9. Polling-Mechanismus inklusive Lastabschätzung
10. Sicherheitsmaßnahmen (Authentifizierung, Autorisierung, CSRF,
    Missbrauchsschutz)
11. Cache-Strategie
12. Backend-Integration
13. alle Entscheidungen D1–D7 mit Begründung
14. ausgeführte Checks mit Ergebnis — und getrennt davon die nicht
    verifizierten Punkte
15. Installationsweg
16. verbleibende technische Einschränkungen und Risiken

Dazu eine Liste der wichtigsten neu angelegten Dateien.

Wenn etwas nicht funktioniert oder ungeprüft blieb, schreib es hin. Ein
ehrlicher Bericht mit Lücken ist brauchbar, ein geschönter nicht.
