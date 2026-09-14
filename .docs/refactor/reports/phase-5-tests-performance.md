# Phase 5 — Testnetz und Lesepfad

Stand: 14.09.2026. Ausgangscommit: `ba99a6e`. Kein Commit und kein Push.
Die bereits vorhandene Änderung am Phasen-Prompt wurde unverändert gelassen.

## Ergebnis und Nachweisgrenze

Schritte 0–4 sind implementiert: registrierte Integrationssuite mit
MariaDB-Service und DCA-Schema-Bootstrap in CI, semantischer Gast-Test,
gerenderte Template-Invarianten, transaktionaler Vote-Zähler mit Reparaturmigration
und selektive öffentliche Bühnenantworten mit 1 s gemeinsamer TTL.

Die vorgeschriebene Reihenfolge wurde eingehalten: Vor B13 liefen 20 echte
Integrationstests mit 237 Assertions erfolgreich; derselbe Stand wurde in einer
frisch erzeugten Datenbank absichtlich rot und nach Rücknahme wieder grün.
Der abschließende Stand hat 130 Unit- und 25 Integrationstests.

**GitHub Actions selbst wurde nicht ausgeführt.** Der rote Lauf unten beweist
den Exit-Code des in CI verwendeten PHPUnit-Schritts in DDEV, einschließlich
Schema-Bootstrap, InnoDB-Sperrprüfung und separatem Observer. Ein tatsächlich
roter gehosteter Workflow ist ohne Veröffentlichung dieser uncommitteten
Änderungen nicht nachgewiesen. Akzeptanzkriterium 2 bleibt in genau diesem
Punkt offen. Es wird kein grüner oder roter GitHub-Lauf behauptet.

Umgebung: DDEV-Projekt `contao0507.contao`, PHP 8.4.24, PHPUnit 12.5.33,
MariaDB `10.11.19-MariaDB-ubu2204-log`. Shell-Arbeitsverzeichnis für sämtliche
DDEV-Kommandos unten: `/home/dev/Kunden/contao/contao_0507`.

## Bootstrap und absichtlich roter Lauf (B12)

Ausgeführt vor der Zähleränderung:

```bash
ddev mysql -e "CREATE DATABASE IF NOT EXISTS qna_phase5_test; GRANT ALL ON qna_phase5_test.* TO 'db'@'%';"
ddev exec --dir /var/www/html/vendor/heimrichhannot/contao-qna-bundle env QNA_DATABASE_TESTS=1 QNA_TEST_DB_NAME=qna_phase5_test php tests/Fixtures/create-schema.php
```

Ausgabe, Exit 0:

```text
Created three InnoDB tables from the bundle DCA definitions.
```

In `testGuestDoesNotOwnMemberZeroVote()` wurde nach dem echten Vote-Insert
vorübergehend eingefügt und anschließend vollständig entfernt:

```php
self::fail('PHASE5_RED_PROBE: integration suite must fail after real database setup.');
```

Exaktes ausgeführtes Kommando:

```bash
ddev exec --dir /var/www/html/vendor/heimrichhannot/contao-qna-bundle env QNA_DATABASE_TESTS=1 QNA_TEST_DB_NAME=qna_phase5_test QNA_TEST_OBSERVER_USER=root QNA_TEST_OBSERVER_PASSWORD=root vendor/bin/phpunit --testsuite Integration --fail-on-skipped
```

Reale Ausgabe (ANSI-Farben und Fortschritt entfernt), **Exit 1**:

```text
PHPUnit 12.5.33 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.4.24
Configuration: /home/dev/Kunden/github/contao-qna-bundle/phpunit.xml.dist
Time: 00:10.276, Memory: 8.00 MB
There was 1 failure:
1) HeimrichHannot\QnaBundle\Tests\Integration\QuestionAnswerDatabaseTest::testGuestDoesNotOwnMemberZeroVote
PHASE5_RED_PROBE: integration suite must fail after real database setup.
/home/dev/Kunden/github/contao-qna-bundle/tests/Integration/QuestionAnswerDatabaseTest.php:69
FAILURES!
Tests: 20, Assertions: 235, Failures: 1.
```

Nach Rücknahme desselben Fehlers, identisches Kommando, **Exit 0**:

```text
Time: 00:09.856, Memory: 8.00 MB
OK (20 tests, 237 assertions)
```

Der finale Schema-Bootstrap wurde zusätzlich auf einer weiteren leeren
Datenbank überprüft:

```bash
ddev mysql -e "CREATE DATABASE qna_phase5_ci; GRANT ALL ON qna_phase5_ci.* TO 'db'@'%';"
ddev exec --dir /var/www/html/vendor/heimrichhannot/contao-qna-bundle env QNA_DATABASE_TESTS=1 QNA_TEST_DB_NAME=qna_phase5_ci php tests/Fixtures/create-schema.php
```

Beide Exit 0, Bootstrap-Ausgabe erneut:

```text
Created three InnoDB tables from the bundle DCA definitions.
```

Die finalen 25 Tests gegen diese frisch erzeugten Tabellen stehen unter „Checks“.
CI verwendet für den Anwendungspfad `qna`, für den Observer Root und installiert
`pcntl`/`posix`. `--fail-on-skipped` verhindert einen grünen Integrationsschritt,
wenn nur der Opt-in-Guard erreicht wird.

## EXPLAIN vorher/nachher (B13)

Vor und nach der Änderung tatsächlich ausgeführt:

```bash
ddev exec --dir /var/www/html/vendor/heimrichhannot/contao-qna-bundle env QNA_DATABASE_TESTS=1 php tests/Fixtures/explain-list.php
```

Das Skript legt 50 Fragen mit je 200 Votes an, liest den aktuellen Gateway-SQL
und führt `EXPLAIN` sowie `ANALYZE FORMAT=JSON` für Mitglied 42 aus. Alle
Fixture-Daten werden zurückgerollt. Im finalen Skript sind die Zähler ebenfalls
konsistent mit 200 befüllt. Es ist ein lokaler Fixture-Vergleich, kein
Mehrbenutzer-Lasttest. Insbesondere ist die EXPLAIN-Schätzung `rows=1` im alten
Plan irreführend; entscheidend ist die reale `r_rows=200`-Messung.

Vorher:

```sql
SELECT
    q.id, q.pid, q.memberId, q.question, q.createdAt, q.answered,
    COUNT(v.id) AS voteCount,
    MAX(CASE WHEN :memberId > 0 AND v.memberId = :memberId THEN 1 ELSE 0 END) AS hasVoted
FROM tl_qna_question q
LEFT JOIN tl_qna_vote v ON v.pid = q.id
WHERE q.pid = :sessionId
GROUP BY q.id, q.pid, q.memberId, q.question, q.createdAt, q.answered
ORDER BY voteCount DESC, q.createdAt ASC
```

Nachher:

```sql
SELECT
    q.id, q.pid, q.memberId, q.question, q.createdAt, q.answered,
    q.voteCount,
    CASE WHEN v.id IS NOT NULL THEN 1 ELSE 0 END AS hasVoted
FROM tl_qna_question q
LEFT JOIN tl_qna_vote v ON v.pid = q.id AND :memberId > 0 AND v.memberId = :memberId
WHERE q.pid = :sessionId
ORDER BY voteCount DESC, q.createdAt ASC
```

EXPLAIN-Ausgabefelder, tabellarisch transkribiert:

| Stand | table | type | key | key_len | ref | rows | Extra |
| --- | --- | --- | --- | --- | --- | --- | --- |
| vorher | q | ALL | null | null | null | 50 | Using where; Using temporary; Using filesort |
| vorher | v | ref | UNIQ_FA8082EF5550C4ED9033DBEA | 4 | qna_phase5_test.q.id | 1 | Using index |
| nachher | q | ALL | null | null | null | 50 | Using where; Using filesort |
| nachher | v | eq_ref | UNIQ_FA8082EF5550C4ED9033DBEA | 8 | qna_phase5_test.q.id,const | 1 | Using where; Using index |

Reale `ANALYZE FORMAT=JSON`-Werte:

| Feld | Vorher | Nachher (finale Fixture) |
| --- | --- | --- |
| query_block.r_total_time_ms | 19.02972307 | 0.290759773 |
| Vote-Join r_loops | 50 | 50 |
| Vote-Join r_rows | 200 | 1 |
| Vote-Join used_key_parts | pid | pid, memberId |
| Vote-Join pages_accessed | 20111 | 200 |

Damit ist auch die separate Optimierung von `hasVoted` belegt: ein eindeutiger
Lookup ersetzt die Aggregation. Die kleinen Tabellen mit nur einer Session
werden auf der Fragenseite weiterhin vollständig gelesen; eine zusätzliche
Indexoptimierung wird nicht behauptet.

## Migration und Demo-Daten

Zuerst `ddev exec vendor/bin/contao-console cache:clear`, Exit 0:

```text
[OK] Cache for the "dev" environment (debug=true) was successfully cleared.
```

Der erste `contao:migrate --dry-run` zeigte neben dem neuen Q&A-Migrator und
`ALTER TABLE tl_qna_question ADD voteCount INT UNSIGNED DEFAULT 0 NOT NULL`
auch fremde Migrationen und DROP-Vorschläge. Daher wurde ausschließlich die
neue Bundle-Migration ausgeführt, gegen den alten Stand ohne Zählerspalte:

```bash
ddev exec --dir /var/www/html/vendor/heimrichhannot/contao-qna-bundle env QNA_DATABASE_TESTS=1 QNA_TEST_DB_NAME=qna_phase5_test php tests/Fixtures/migrate-vote-count.php
ddev exec --dir /var/www/html/vendor/heimrichhannot/contao-qna-bundle env QNA_DATABASE_TESTS=1 php tests/Fixtures/migrate-vote-count.php
```

Beide Exit 0, jeweils reale Ausgabe:

```text
pending=1
HeimrichHannot\QnaBundle\Migration\RebuildVoteCountMigration executed successfully
pending after=0
```

Ein vorheriger Versuch mit `ddev exec ... php -r` scheiterte am DDEV-Shell-Escaping
(`bash: line 1: name: unbound variable`, Exit 1), bevor PHP lief. Die obigen
Datei-Aufrufe ersetzten diesen Versuch.

Konsistenzprüfung der migrierten Demo:

```bash
ddev mysql -e "SELECT id,title,published,state FROM tl_qna_session; SELECT COUNT(*) AS questions, SUM(voteCount) AS cached_votes, (SELECT COUNT(*) FROM tl_qna_vote) AS vote_rows FROM tl_qna_question; SELECT COUNT(*) AS mismatches FROM tl_qna_question q WHERE q.voteCount <> (SELECT COUNT(*) FROM tl_qna_vote v WHERE v.pid=q.id);"
```

Reale relevante Ausgabe, Exit 0:

```text
questions  cached_votes  vote_rows
11         14            14
mismatches
0
```

Die Integrationstests prüfen zusätzlich Gast-Zeilen, Autoren-Vote-Rollback,
beide Sortierungen und Mitgliedsbindung, Duplikate unter altem Snapshot,
Vote/Duplicate/Erasure/Fragenlöschung, Erasure gegen Vote in beiden Reihenfolgen
und Reparatur eines absichtlich falschen Zählers einschließlich Idempotenz.
Die Unit-Erwartungen der Member-Erasure wurden wegen des zusätzlichen
Zähler-Updates von vier auf fünf Statements angepasst; Session- und
Fragensperren bleiben die ersten beiden Statements.

## Laufzeitprüfung der Cache-Politik (B14)

`ddev exec curl -sS -D - http://localhost/_qna/stage/20/questions` lieferte
HTTP 200, aber im Dev-Modus:

```text
Cache-Control: max-age=0, must-revalidate, private, s-maxage=1
Contao-Private-Response-Reason: response-cookies (contao_frontend_deauth_profile_token, contao_frontend_auth_profile_token)
```

Das ist Contao-Schutzverhalten wegen Profiler-Cookies. Für den Prod-Nachweis
wurde ein temporäres Skript `.phase5-runtime.php` im Bundle ausgeführt und nach
Abschluss entfernt. Wesentlicher Inhalt (real verwendeter Request-Pfad):

```php
require '/var/www/html/vendor/autoload.php';
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'prod';
$_SERVER['DISABLE_HTTP_CACHE'] = '1';
$request = Symfony\Component\HttpFoundation\Request::create('http://localhost'.($argv[1] ?? '/_qna/stage/20/questions'));
if (isset($argv[2])) {
    $request->headers->set('Accept', $argv[2]);
}
$kernel = Contao\ManagerBundle\HttpKernel\ContaoKernel::fromRequest('/var/www/html', $request);
$response = $kernel->handle($request);
echo 'Status: '.$response->getStatusCode()."\n";
echo $response->headers;
echo 'Contains token field: '.(int) str_contains((string) $response->getContent(), 'REQUEST_TOKEN')."\n";
$kernel->terminate($request, $response);
```

Der erste Prod-Versuch lieferte 500 wegen einer veralteten Container-Referenz
auf `QnaFrameResponseFactory` aus einer früheren Phase. Nach
`ddev exec vendor/bin/contao-console cache:clear --env=prod` (Exit 0,
`[OK] Cache for the "prod" environment (debug=false) was successfully cleared.`)
folgten die erfolgreichen finalen Aufrufe:

```bash
ddev exec php /var/www/html/vendor/heimrichhannot/contao-qna-bundle/.phase5-runtime.php
ddev exec php /var/www/html/vendor/heimrichhannot/contao-qna-bundle/.phase5-runtime.php /_qna/stage/20/questions text/vnd.turbo-stream.html
ddev exec php /var/www/html/vendor/heimrichhannot/contao-qna-bundle/.phase5-runtime.php /_qna/reader/20
```

Ausgaben, jeweils Exit 0, relevante Header:

```text
# Bühne HTML
Status: 200
Cache-Control: max-age=0, must-revalidate, public, s-maxage=1
Content-Type: text/html; charset=UTF-8
Vary: Origin
Vary: Accept
Vary: Accept-Language
Vary: Cookie
Vary: Authorization
Contains token field: 0

# Bühne Stream
Status: 200
Cache-Control: max-age=0, must-revalidate, public, s-maxage=1
Content-Type: text/vnd.turbo-stream.html; charset=UTF-8
Vary: Origin
Vary: Accept
Vary: Accept-Language
Vary: Cookie
Vary: Authorization
Contains token field: 0

# Reader HTML
Status: 200
Cache-Control: no-store, private
Content-Type: text/html; charset=UTF-8
Contains token field: 0
```

Dies prüft die vollständige Produktionskernel-Antwort einschließlich
Response-Listenern, nicht die Trefferquote eines Reverse-Proxys. Authentifizierte
Steuerantworten sind mit real gerenderten Templates und gemockter Identität in
Unit-Tests abgedeckt, nicht durch einen neuen Browser-Login. HTML und Stream
mit Steuerung enthalten Token und bleiben privat; Cookies, Authorization und
Polling-Basisintervall ≤ 1000 ms sperren geteiltes Caching ebenfalls.

## Abschließende Checks

Die folgenden Kommandos und Ausgaben sind die tatsächlich ausgeführten
abschließenden Prüfungen. ANSI-Farben wurden entfernt. Ohne Opt-in überspringt
der normale PHPUnit-Aufruf erwartungsgemäß 25 Integrationstests; der separate
Datenbanklauf führt alle 25 aus und erlaubt kein Überspringen.

### cs-fixer

```bash
ddev exec --dir /var/www/html/vendor/heimrichhannot/contao-qna-bundle vendor/bin/php-cs-fixer check --diff --sequential
```

Exit 0. Reale Ausgabe:

```text
PHP CS Fixer 3.95.18 Adalbertus by Fabien Potencier, Dariusz Ruminski and contributors.
PHP runtime: 8.4.24
Loaded config default from "/home/dev/Kunden/github/contao-qna-bundle/.php-cs-fixer.dist.php".
Running analysis on 1 core sequentially.
Using cache file ".php-cs-fixer.cache".
  0/90 [░░░░░░░░░░░░░░░░░░░░░░░░░░░░]   0%
 90/90 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%


Found 0 of 90 files that can be fixed in 0.004 seconds, 10.00 MB memory used
```

### phpstan

```bash
ddev exec --dir /var/www/html/vendor/heimrichhannot/contao-qna-bundle vendor/bin/phpstan analyse --no-progress
```

Exit 0. Reale Ausgabe:

```text
Note: Using configuration file /home/dev/Kunden/github/contao-qna-bundle/phpstan.neon.

 [OK] No errors
```

### phpunit

```bash
ddev exec --dir /var/www/html/vendor/heimrichhannot/contao-qna-bundle vendor/bin/phpunit
```

Exit 0. Reale Ausgabe:

```text
PHPUnit 12.5.33 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.24
Configuration: /home/dev/Kunden/github/contao-qna-bundle/phpunit.xml.dist

...............................................................  63 / 155 ( 40%)
............................................................... 126 / 155 ( 81%)
....SSSSSSSSSSSSSSSSSSSSSSSSS                                   155 / 155 (100%)

Time: 00:00.286, Memory: 16.00 MB

OK, but some tests were skipped!
Tests: 155, Assertions: 858, Skipped: 25.
```

### integration

```bash
ddev exec --dir /var/www/html/vendor/heimrichhannot/contao-qna-bundle env QNA_DATABASE_TESTS=1 QNA_TEST_DB_NAME=qna_phase5_ci QNA_TEST_OBSERVER_USER=root QNA_TEST_OBSERVER_PASSWORD=root vendor/bin/phpunit --testsuite Integration --fail-on-skipped
```

Exit 0. Reale Ausgabe:

```text
PHPUnit 12.5.33 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.24
Configuration: /home/dev/Kunden/github/contao-qna-bundle/phpunit.xml.dist

.........................                                         25 / 25 (100%)

Time: 00:11.403, Memory: 8.00 MB

OK (25 tests, 296 assertions)
```

### dry-run

```bash
ddev exec vendor/bin/contao-console contao:migrate --dry-run
```

Exit 0. Reale Ausgabe:

```text
Pending migrations
------------------

 * Contao\CoreBundle\Migration\Version507\PrepareForOutputEncodingMigration
 * Maildrum document schema 2 migration

Pending database migrations (dd7a732e1b5977f5eb749447d3e1c04ee558d76636326608586cbbf4d571f02e)
----------------------------------------------------------------------------------------------

 * DROP TABLE tl_newsletter_deny_list
 * DROP TABLE tl_newsletter
 * DROP TABLE tl_filecredit_page
 * DROP TABLE tl_newsletter_channel
 * DROP TABLE tl_newsletter_recipients
 * DROP TABLE tl_filecredit
 * ALTER TABLE tl_content DROP filecredits_paginationLimit, DROP videoProvider, DROP addPreviewImage, DROP addPlayButton, DROP videoRemoveControls, DROP videoLoop, DROP videoDuration, DROP videoShowRelated, DROP ytModestBranding, DROP ytShowInfo, DROP videoLinkText, DROP videoFullsize, DROP videoAutoplay, DROP videoSRC, DROP videoSubtitles, DROP videoAlternativeText, DROP transcriptedYoutube, DROP transcriptedVimeo, DROP mlSort
 * ALTER TABLE tl_files DROP copyright, DROP copyrightUrl
 * ALTER TABLE tl_member DROP newsletter
 * ALTER TABLE tl_module DROP newsletters, DROP nl_channels, DROP nl_text, DROP nl_hideChannels, DROP nl_subscribe, DROP nl_unsubscribe, DROP nl_template
 * ALTER TABLE tl_news DROP dateAdded
 * ALTER TABLE tl_page DROP filecredits_noIndex, DROP overrideNoCookieVideoUrlSettings, DROP enableNoCookieVideoUrl, DROP overrideEnablePrivacyNotice, DROP enablePrivacyNotice, DROP videofullsizeTemplate, DROP videoprivacyTemplate
 * ALTER TABLE tl_user DROP newsletters, DROP backendLostPasswordActivation
 * ALTER TABLE tl_user_group DROP newsletters

 [OK] All migrations completed.
```

## Ergänzungen in DECISIONS.md — Wortlaut

## D9: Gast-Votes und ausführbare Integrationstests (Refactor Phase 5)

Seit Phase 3 gilt im Reader wie auf der Bühne: Eine Vote-Zeile mit
`memberId = 0` zählt zur Gesamtzahl, aber für Gäste niemals als eigener Vote
(`hasVoted = false`). Der frühere Reader hätte diese Zeile als eigenen Vote
gewertet. Das ist eine bewusste Verhaltenskorrektur der ursprünglich als
verhaltensneutral bezeichneten Phase 3. Ein echter Datenbanktest ersetzt die
bisherige SQL-Textprüfung dieses Falls.

CI erstellt in einer leeren MariaDB-10.11-Datenbank die drei Bundle-Tabellen
über `tests/Fixtures/create-schema.php` aus den Doctrine-Schemadefinitionen der
DCA-Dateien. Damit gibt es keinen parallel gepflegten SQL-Dump und keine
vollständige Contao-Installation als Voraussetzung der Service-Tests. Der
separate Integrationsschritt aktiviert `QNA_DATABASE_TESTS=1` und
`--fail-on-skipped`; fehlende Extensions oder übersprungene Tests sind Fehler.
Der Anwendungsbenutzer bleibt unprivilegiert; nur die Observer-Verbindung
nutzt Root für `information_schema.INNODB_TRX` (PROCESS).

## D10: Wiederherstellbarer Vote-Zähler (Refactor Phase 5)

`tl_qna_question.voteCount` ist ein unsigned Integer mit Default 0 in
Doctrine-Schemarepräsentation. Die Wahrheit bleibt `UNIQUE(pid, memberId)` auf
`tl_qna_vote`. `QnaVoteGateway::create()` erhöht den Zähler erst nach einem
erfolgreichen Insert in derselben Service-Transaktion; Duplicate-Votes werfen
vor dem Inkrement. Das schließt den automatischen Autoren-Vote ein.
`MemberDataEraser` hält weiterhin zuerst die Session-Sperren; der Vote-Gateway
verringert betroffene Zähler vor dem Löschen der Votes. Die Sperrreihenfolge
und die bestehenden Service-Transaktionsgrenzen bleiben unverändert.

`RebuildVoteCountMigration` ergänzt die Spalte vor dem Contao-Schema-Update
und rekonstruiert sie aus den Vote-Zeilen. Abweichende Zähler aktivieren dieselbe
Migration erneut; sie ist auch nach der Erstinstallation wiederholbar.
DDL läuft außerhalb der Reparaturtransaktion (MySQL impliziter Commit), die
Reparatur selbst unter aufsteigend erworbenen Session-Sperren. Belegte APIs:
`vendor/contao/core-bundle/src/Migration/AbstractMigration.php`,
`MigrationResult.php`, `src/DependencyInjection/ContaoCoreExtension.php`
(Autokonfiguration von `MigrationInterface`) und `src/Command/MigrateCommand.php`
(Migrationen vor Schema-Abgleich), jeweils im Core-Bundle.

Die Listenabfrage liest `q.voteCount`; `hasVoted` nutzt getrennt einen durch
`memberId > 0` begrenzten LEFT JOIN auf den eindeutigen Schlüssel `(pid, memberId)`.
Weder COUNT noch GROUP BY bleiben im Listenpfad. EXPLAIN/ANALYZE mit 50 Fragen
und je 200 Votes belegt den Gewinn: vorher 50 × 200 gelesene Vote-Zeilen,
nachher 50 × 1, Zugriff `ref` → `eq_ref`, ohne temporäre Aggregationstabelle.
Der aktuelle Vote-State nach Schreiboperationen bleibt ein gesperrter
COUNT-Lesevorgang; seine Repeatable-Read-Semantik wird nicht verändert.

## D11: Bühnen-Cache und gerenderte Template-Invarianten (Refactor Phase 5)

Die Cache-Politik ist ein expliziter Parameter von `TurboResponseFactory::html()`
und `::stream()`, Standard weiterhin `private, no-store`. Ausschließlich
`QnaFrameController::stage()` wählt `public, max-age=0, s-maxage=1, must-revalidate`
für eine Antwort ohne Start-/Stopp-Steuerung, ohne Request-Cookies und ohne
Authorization-Header. Die gemeinsame TTL beträgt eine Sekunde, also weniger
als das normale Polling-Intervall von 2,5 Sekunden. Bei konfigurierten
Basisintervallen von höchstens einer Sekunde bleibt auch die Bühne privat.
`max-age=0` hält den Browser zur erneuten Abfrage an; `must-revalidate` erlaubt
keine veraltete Auslieferung nach Ablauf. Die bestehende Polling-Verzögerung
ist davon unabhängig; die zusätzliche Cache-Frische beträgt höchstens 1 s.

Bühnenantworten variieren nach `Accept`, `Accept-Language`, `Cookie` und
`Authorization`. So kann ein geteilter Zuschauer-Cache keine Steuerantwort,
andere Sprache oder HTML-/Stream-Repräsentation ersetzen. Steuerantworten
enthalten weiterhin CSRF-Token sowie Start-/Stopp- und ggf. Answer-Formulare
und bleiben `private, no-store`; ebenso alle Reader- und Aktionsantworten.
Cookie-basierte Zuschauer werden bewusst nicht geteilt gecacht.
`vendor/contao/core-bundle/src/EventListener/MakeResponsePrivateListener.php`
kann öffentliche Antworten zusätzlich privatisieren, z. B. bei Session- oder
Profiler-Cookies. Diese Schutzlogik wird nicht umgangen.

Der Template-Test rendert die echten Bundle-Twig-Dateien einschließlich ihrer
Includes und prüft DOM/HTML. Nur die unabhängige Contao-Seitenhülle wird im
Unit-Test ersetzt; die native `AddTokenParser`-Syntax ist aus
`vendor/contao/core-bundle/src/Twig/ResponseContext/AddTokenParser.php` verifiziert.
Ein absichtlich mit Token, Mitgliedsstatus und Vote-Daten angereicherter
Kontext muss dieselbe neutrale Initialausgabe liefern wie der alternative
Kontext. Der Cache-Test rendert die realen Stage-/Reader-Views für HTML und
Streams, Zustände und Steuerberechtigungen. DCA-Palettenstring-Tests bleiben
bestehen: Die Palette selbst ist ein String; ein Umbau bringt hier keinen
zusätzlichen Verhaltensnachweis.

## Grenzen und Abschluss

Kein gehosteter GitHub-Actions-Lauf, keine neue Browser-Anmeldung und kein
Reverse-Proxy-Lasttest. Der finale Contao-Dry-run meldet weiter fremde
Migrationen und DROP-Vorschläge; diese wurden nicht ausgeführt.
Die Q&A-Migration und die Q&A-Schemaänderung sind dort nicht mehr offen.
Es wurde nicht committet oder gepusht.


Die beiden nur für diese Prüfung erzeugten Datenbanken samt separaten Grants
wurden anschließend entfernt (Exit 0, keine Ausgabe):

```bash
ddev mysql -e "DROP DATABASE qna_phase5_ci; DROP DATABASE qna_phase5_test; REVOKE ALL ON qna_phase5_ci.* FROM 'db'@'%'; REVOKE ALL ON qna_phase5_test.* FROM 'db'@'%';"
```

`git diff --check` wurde im Bundle-Verzeichnis ausgeführt: Exit 0, keine Ausgabe.
