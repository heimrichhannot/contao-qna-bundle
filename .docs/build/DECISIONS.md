# API-Recherche und Entscheidungen

Recherchegrundlage ist die mit Composer installierte Version
`contao/core-bundle` 5.7.11. Aussagen zu Contao-APIs stützen sich auf die
Sourcen unter `vendor/contao/core-bundle/src/`; ergänzend sind die jeweils
zuständigen Symfony- und Contao-Manager-Quellen genannt.

## Verifizierte APIs

| Gesuchte API | Gefunden | Ergebnis und Beleg |
| --- | --- | --- |
| Bundle-Registrierung | ja | `vendor/contao/core-bundle/src/ContaoManager/Plugin.php`, `vendor/contao/manager-plugin/src/Bundle/BundlePluginInterface.php`, `vendor/contao/manager-plugin/src/Bundle/Config/BundleConfig.php`: ein `BundlePluginInterface` liefert `BundleConfig`-Instanzen; das Q&A-Bundle wird nach `ContaoCoreBundle` geladen. |
| Routen über das Contao-Manager-Plugin | ja | `vendor/contao/core-bundle/src/ContaoManager/Plugin.php`, `vendor/contao/manager-plugin/src/Routing/RoutingPluginInterface.php`: `getRouteCollection(LoaderResolverInterface, KernelInterface)` lädt die Bundle-Routenkonfiguration. |
| `#[AsContentElement]` | ja | `vendor/contao/core-bundle/src/DependencyInjection/Attribute/AsContentElement.php`: `__construct(?string $type = null, string $category = 'miscellaneous', ?string $template = null, ?string $method = null, ?string $renderer = null, array\|bool $nestedFragments = false, int $priority = 0, mixed ...$attributes)`. |
| `#[AsPage]` | ja | `vendor/contao/core-bundle/src/DependencyInjection/Attribute/AsPage.php`: `__construct(?string $type = null, bool\|string\|null $path = null, array $requirements = [], array $options = [], array $defaults = [], array $methods = [], ?string $locale = null, ?string $format = null, bool $contentComposition = true, ?string $urlSuffix = null, ?string $template = null)`. |
| Content-Element-Basis | ja | `vendor/contao/core-bundle/src/Controller/ContentElement/AbstractContentElementController.php`: finaler Controller-Aufruf mit `Request`, `ContentModel`, Section und Klassen; die Unterklasse implementiert `getResponse(FragmentTemplate, ContentModel, Request): Response`. |
| Page-Controller-Basis | ja | `vendor/contao/core-bundle/src/Controller/Page/AbstractPageController.php`: `renderPage(PageModel): Response`; `vendor/contao/core-bundle/src/Controller/Page/RegularPageController.php`: regulärer, mit `#[AsPage]` registrierter Controller. |
| `LayoutTemplate` | ja | `vendor/contao/core-bundle/src/Twig/LayoutTemplate.php`: `setSlot(string, Stringable\|string): void` und `getResponse(?Response = null): Response`. |
| Moderne Seitenlayouts und Slots | ja | `vendor/contao/core-bundle/src/ContentComposition/ContentComposition.php`: `createContentCompositionBuilder(PageModel): ContentCompositionBuilder`; `vendor/contao/core-bundle/src/ContentComposition/ContentCompositionBuilder.php`: `buildLayoutTemplate(): LayoutTemplate`, Aufbau der Slots und Abbruch bei einem nicht modernen Layout. Beide Methoden sind unabhängig vom Page-Registry-Schalter `contentComposition`; die API ist dort als `@experimental` markiert. |
| Page-Content-Composition-Schalter | ja | `vendor/contao/core-bundle/src/DependencyInjection/Compiler/RegisterPagesPass.php` übergibt das Attribut an `vendor/contao/core-bundle/src/Routing/Page/PageRegistry.php`. Dessen `supportsContentComposition()` wird in `vendor/contao/core-bundle/src/EventListener/DataContainer/ContentCompositionListener.php` nur für Artikeloperation und automatische Artikelanlage ausgewertet. |
| Frontend-Scope für eigene Routen | ja | `vendor/contao/core-bundle/src/ContaoCoreBundle.php`: `SCOPE_FRONTEND = 'frontend'`; `vendor/contao/core-bundle/src/Routing/Page/PageRoute.php` setzt `_scope`; `vendor/contao/core-bundle/src/Routing/ScopeMatcher.php` wertet ihn aus. Eigene Controller-Routen erhalten in `config/routes.yaml` den Default `_scope: frontend`. |
| Bundle-Template-Namespace | ja | `vendor/symfony/twig-bundle/DependencyInjection/TwigExtension.php`: ein Bundle-Verzeichnis `templates/` würde unter dem um `Bundle` gekürzten Bundle-Namen als `@HeimrichHannotQna` registriert. Dieser Symfony-Namespace existiert grundsätzlich, ist hier aber nicht der maßgebliche Renderweg. Die Q&A-Templates liegen unter `contao/templates/` und werden über die von `vendor/contao/core-bundle/src/Twig/Loader/TemplateLocator.php` und `ContaoFilesystemLoader.php` aufgebaute verwaltete Hierarchie als `@Contao/...` aufgelöst; dadurch haben Projekt- und Theme-Templates Vorrang vor der Bundle-Fassung. Der in `TemplateLocator::isNamespaceRoot()` verifizierte Marker `contao/templates/.twig-root` erhält dabei die Unterordner in den logischen Namen. `RegisterFragmentsPass.php` setzt für Content-Elemente ohne explizite Template-Angabe automatisch `content_element/<type>`, sodass die beiden Controller ihr übergebenes `FragmentTemplate` rendern. |
| Asset-Bereitstellung | ja | `vendor/contao/core-bundle/src/DependencyInjection/Compiler/AddAssetsPackagesPass.php`: ein Bundle-Ordner `public/` wird als Asset-Paket und unter `bundles/<bundle-name>` registriert; `vendor/symfony/framework-bundle/Command/AssetsInstallCommand.php` installiert die Dateien. Eine `public/manifest.json` aktiviert für dieses Paket die `JsonManifestVersionStrategy`; die Manifest-Ziele tragen inhaltsbasierte `?v=`-Parameter. Da Turbo aus `qna.js` dynamisch und nicht über Twig geladen wird, enthält auch dessen relativer Import den Turbo-Hash. `assets/` bleibt der Quellordner, auslieferbare Artefakte gehören nach `public/`. |
| DCA-Loading | ja | `vendor/contao/core-bundle/src/DependencyInjection/Compiler/AddResourcesPathsPass.php` registriert Bundle-Ressourcen; `vendor/contao/core-bundle/contao/library/Contao/DcaLoader.php` lädt `contao/dca/<Tabelle>.php`; `vendor/contao/core-bundle/src/Doctrine/Schema/DcaSchemaProvider.php` verarbeitet die Doctrine-Schema-Arrays in `fields.*.sql` und `config.sql.keys`. |
| Übersetzungen aus Bundles | ja | `vendor/symfony/framework-bundle/DependencyInjection/FrameworkExtension.php` registriert Bundle-Ordner `translations/`; `vendor/contao/core-bundle/src/Cache/ContaoCacheWarmer.php` berücksichtigt Symfony-Domains mit Präfix `contao_`. Neue Übersetzungen werden daher als Symfony-PHP-Ressourcen unter `translations/contao_*.de.php` bzw. `.en.php` angelegt. |
| `ptable`/`ctable`-Löschkaskade | ja | `vendor/contao/core-bundle/contao/drivers/DC_Table.php`: `deleteChildren()` folgt den `ctable`-Definitionen rekursiv über `pid`. Für die Q&A-Eltern-Kind-Kette ist kein eigener Callback nötig. |
| Frontend-Kontoschließung | ja | `vendor/contao/core-bundle/src/Controller/ContentElement/CloseAccountController.php` dispatcht `CloseAccountEvent` und unterscheidet `close_delete` von `close_deactivate`; `vendor/contao/core-bundle/src/Event/CloseAccountEvent.php` liefert Mitglied und Content-Model. Das veraltete `vendor/contao/core-bundle/contao/modules/ModuleCloseAccount.php` dispatcht kein Event, sondern ruft den `closeAccount`-Hook mit Mitglieds-ID, Modus und Modul auf. Die gültigen Modi stehen außerdem in `vendor/contao/core-bundle/contao/dca/tl_content.php` und `tl_module.php`. |
| Item-Parameter und `requireItem` | ja | `vendor/contao/core-bundle/src/Routing/Page/PageRegistry.php` macht Seitenparameter bei `requireItem` zwingend; `vendor/contao/core-bundle/src/Routing/Enhancer/InputEnhancer.php` bildet den einzelnen Parameter als `auto_item` ab; `vendor/contao/core-bundle/contao/library/Contao/Input.php` markiert einen Parameter bei `Input::get()` standardmäßig als verwendet; `vendor/contao/core-bundle/src/EventListener/LegacyRouteParametersListener.php` verwirft erfolgreiche Frontend-Antworten mit unbenutzten Parametern. |
| `FilterPageTypeEvent` | ja, veraltet | `vendor/contao/core-bundle/src/Event/FilterPageTypeEvent.php`: seit Contao 5.3 veraltet und für Contao 6 zur Entfernung vorgesehen; stattdessen sollen DCA-Berechtigungen verwendet werden. Die aktuelle Dispatch-Stelle ist `vendor/contao/core-bundle/src/EventListener/DataContainer/PageTypeOptionsListener.php`. |
| Frontend-CSRF | ja | `vendor/contao/core-bundle/src/EventListener/RequestTokenListener.php` prüft Frontend-POSTs und erwartet `REQUEST_TOKEN`; `vendor/contao/core-bundle/src/Csrf/ContaoCsrfTokenManager.php` stellt `getDefaultTokenValue()` bereit; `vendor/contao/core-bundle/src/Controller/AbstractController.php` konfiguriert Contao-Formulare mit `contao.csrf.token_manager` und dem Token-Feld. |
| Fragment-Cache-Verhalten | ja | `vendor/contao/core-bundle/src/Controller/AbstractFragmentController.php` markiert automatisch erzeugte Fragment-Antworten zur Cache-Control-Zusammenführung; `vendor/contao/core-bundle/src/EventListener/SubrequestCacheSubscriber.php` führt die Header in die Hauptantwort zusammen. Eine explizit erzeugte `Response` behält die gesetzten Cache-Header. |
| `ResponseContext` | ja | `vendor/contao/core-bundle/src/Routing/ResponseContext/ResponseContext.php` hält kontextbezogene Services und Header; `vendor/contao/core-bundle/src/Routing/ResponseContext/ResponseContextAccessor.php` finalisiert diesen Kontext. Es handelt sich nicht um einen Cache-Schalter. |
| Page-Route mit optionalem Parameter und Suffix | ja, mit Einschränkung | `vendor/contao/core-bundle/src/Routing/Page/PageRoute.php` definiert `PAGE_BASED_ROUTE_NAME`; `vendor/contao/core-bundle/src/Routing/Page/PageRouteCompiler.php` entfernt den Suffix vor dem Kompilieren und fügt ihn anschließend wieder ein. Dadurch matchen sowohl `/buehne.html` als auch `/buehne/alias.html`. Die URL-Generierung mit einem nicht leeren `alias` funktioniert; bei `alias = ''` lehnt der Symfony-Generator in 5.7.11 den leeren Wert vor `.html` jedoch als ungültig ab. |
| Klassisches Seiten-Rendering | ja, veraltet | `vendor/contao/core-bundle/contao/controllers/FrontendIndex.php`: `renderPage(PageModel): Response` delegiert in 5.7 an den regulären Seitencontroller und ist für Contao 6 als entfernt markiert; `vendor/contao/core-bundle/contao/pages/PageRegular.php` ruft den `generatePage`-Hook mit `PageModel`, `LayoutModel` und `PageRegular` auf. |
| Turbo im Core | teilweise | `vendor/contao/core-bundle/src/ContaoCoreBundle.php` registriert das Format `turbo_stream`; `vendor/contao/core-bundle/assets/backend.js` bindet Turbo für das Backend ein. Eine automatische Turbo-Einbindung für das Frontend wurde in den Core-Sourcen nicht gefunden. |
| Backend-Modulregistrierung | ja | `vendor/contao/core-bundle/contao/config/config.php` registriert Backend-Bereiche und -Module über `$GLOBALS['BE_MOD']` mit einer `tables`-Liste. Das Bundle verwendet denselben Mechanismus in `contao/config/config.php`. |
| Alias und Toggle im DCA | ja | `vendor/contao/core-bundle/src/Slug/Slug.php` stellt `generate(string, int\|iterable, ?callable, string)` bereit; `vendor/contao/core-bundle/src/EventListener/DataContainer/DefaultOperationsListener.php` erzeugt bei genau einem Feld mit `toggle = true` die Standard-Toggle-Operation. |
| DCA-Callbacks als Services | ja | `vendor/contao/core-bundle/src/DependencyInjection/Attribute/AsCallback.php` registriert Service-Callbacks mit `table` und `target`. Der Session-Alias-Callback wird so als `fields.alias.save` registriert. |
| Frontend-User im Voter | ja | `vendor/contao/core-bundle/contao/classes/FrontendUser.php` ist der Frontend-Benutzer des Symfony-Security-Kontexts; `vendor/contao/core-bundle/src/Security/Voter/MemberGroupVoter.php` prüft den Token-User ebenfalls per `instanceof FrontendUser`. |

## Nicht verifizierbar

- Eine Contao-5.7-Option wie `cache: false` an `#[AsContentElement]` existiert in
  der realen Attributsignatur nicht. Cache-Sicherheit muss über die
  `Response`-Header und die oben beschriebene Fragment-Zusammenführung
  umgesetzt werden.
- Eine automatische Bereitstellung von Turbo im Frontend wurde nicht
  gefunden.
- Eine spezielle, generische Contao-Response-Klasse für Turbo Streams im
  Frontend wurde nicht gefunden. Verifiziert ist nur das registrierte
  `turbo_stream`-Format.

## D1 – Content Composition (entschieden)

Der Seitentyp wird mit `contentComposition = false` registriert. Der Schalter
steuert über `PageRegistry::supportsContentComposition()` die Artikeloperation
und automatische Artikelanlage im Backend; er wird weder von
`ContentComposition::createContentCompositionBuilder()` noch von
`ContentCompositionBuilder::buildLayoutTemplate()` ausgewertet. Damit kann der
Controller weiterhin das Layout samt Slots aufbauen, während Redakteure der
Bühnenseite keine später überschriebenen Artikel zuweisen können.

Bei einem modernen Layout verwendet `QnaStageController` ausschließlich diese
in Contao 5.7.11 verifizierte Kette:

* `vendor/contao/core-bundle/src/ContentComposition/ContentComposition.php`:
  `createContentCompositionBuilder(PageModel): ContentCompositionBuilder`;
  Service-ID und Klassenalias stehen in
  `vendor/contao/core-bundle/config/services.yaml` als
  `contao.content_composition` bzw.
  `Contao\CoreBundle\ContentComposition\ContentComposition`.
* `vendor/contao/core-bundle/src/ContentComposition/ContentCompositionBuilder.php`:
  `buildLayoutTemplate(): LayoutTemplate`. Dieselbe Methode prüft
  `layout->type` und wirft bei einem Wert ungleich `modern` eine
  `LogicException`. Da `LayoutModel` bereits vor dem Builder verfügbar ist,
  verzweigt der Controller vor diesem Aufruf.
* `vendor/contao/core-bundle/src/Twig/LayoutTemplate.php`:
  `setSlot(string, Stringable|string): void` und
  `getResponse(?Response = null): Response`. Der gerenderte Twig-String wird
  daher direkt in den Slot `main` gesetzt.

Die komplette experimentelle API ist in genau dieser Controllerklasse
gekapselt. Für ein Layout mit `type = default` registriert dieselbe Klasse
temporär den `generatePage`-Hook, ruft das in
`vendor/contao/core-bundle/contao/controllers/FrontendIndex.php` verifizierte
`renderPage(PageModel): Response` auf und setzt im Hook `Template->main`. Ein
`finally`-Block stellt den vorherigen Hookwert wieder her bzw. entfernt den
Bundle-Hook. Dieser Weg ist notwendig, aber `FrontendIndex::renderPage()` ist
in 5.7 bereits für die Entfernung in Contao 6 markiert.

Der eigene Parameter heißt `alias`; `auto_item` wird nicht verwendet. Der
Compiler in `PageRouteCompiler.php` löst mit Root-Suffix beide eingehenden
Formen korrekt auf. In der realen Integrationsumgebung wurden
`/codex-qna-modern-stage.html` und
`/codex-qna-modern-stage/codex-open-session.html` sowie dieselben Formen mit
klassischem Layout nach der Umstellung auf `false` erfolgreich gerendert.

Eine bekannte 5.7.11-Einschränkung bleibt: Der Symfony-Generator kann den
optionalen Parameter vor `.html` nicht leer erzeugen. Auch die geprüfte
Alternative `ContentUrlGenerator::generate($pageModel)` scheitert real: Sie
kompiliert in `vendor/contao/core-bundle/src/Routing/ContentUrlGenerator.php`
dieselbe PageRoute und endete für die Testseite in einer
`RouteParametersException`, deren vorherige `InvalidParameterException` den
leeren Wert für `alias` gegen `[^/]++` ausweist. Deshalb erzeugt das Bundle nur
Detail-Links mit nicht leerem Alias und keinen künstlichen Zurück-/Self-Link zur
Übersicht. Die Übersichtsroute selbst löst sauber auf.

## D2 – Turbo-Bereitstellung (entschieden)

Das Bundle liefert Turbo 8.0.23 als gepinntes ES-Modul
`public/turbo.es2017-esm.js` samt MIT-Lizenz mit. SHA-256 des ausgelieferten
Artefakts:
`b9d35d123a07614f55eaaf993f74d687a503ae41ba50ef835aafa18dbb265a13`.
Das kleine Bundle-Modul `public/qna.js` prüft zuerst `window.Turbo`. Nur wenn
keine Instanz vorhanden ist, importiert es das mitgelieferte Modul dynamisch und
setzt für diese Instanz `Turbo.session.drive = false`. Eine vorhandene
Host-Instanz wird weder erneut geladen noch in ihrer Drive-Konfiguration
verändert. Die Assets werden nur von Q&A-Reader- und
Bühnen-Detailtemplates eingebunden; die Bühnenübersicht lädt lediglich CSS.
Das Host-Projekt braucht weder eine Turbo-Abhängigkeit noch einen
JavaScript-Build.

## D3 – Löschen von Mitgliederdaten (entschieden)

Fragen und Votes eines gelöschten Mitglieds werden **gelöscht**, nicht
anonymisiert. Weil der Fragetext weiterhin personenbezogene Inhalte enthalten
kann und `memberId` im Schema nicht nullable ist, wäre das Setzen einer
Ersatz-ID keine belastbare Anonymisierung.

Eine gemeinsame Löschklasse löscht in einer Transaktion zunächst alle eigenen
Votes sowie alle Votes auf Fragen des Mitglieds und danach dessen Fragen.
Diese Reihenfolge ist notwendig, weil die Operation nicht über `DC_Table` auf
einer Q&A-Elterntabelle startet. Der `tl_member`-Callback für die
Backend-Löschung und die Frontend-Eintrittspfade delegieren ausschließlich an
diese Klasse.

Für das moderne Close-Account-Content-Element wird das in Contao 5.7
vorhandene `CloseAccountEvent` verwendet und der Modus aus dem Content-Model
gelesen. Das veraltete Close-Account-Frontend-Modul dispatcht dieses Event
nicht; nur für diesen getrennten Pfad bleibt deshalb der `closeAccount`-Hook
registriert. Beide Pfade löschen ausschließlich bei `close_delete`. Bei
`close_deactivate` bleiben Fragen und Votes erhalten.

## D4 – Vote-Zählung (entschieden)

Votes werden mit `COUNT()` im Gateway aggregiert. Es gibt keine
denormalisierte `vote_count`-Spalte. Das hält das Ausgangsschema frei von einem
race-condition-anfälligen Zähler; die benötigten Indizes und der eindeutige
Schlüssel `(pid, memberId)` liegen auf Datenbankebene vor.

## D5 – Item und 404 (entschieden)

Der Reader liest den Legacy-Parameter ausschließlich in
`QnaSessionReaderController::resolveSession()` mit
`Input::get('auto_item')`. Es gibt keinen globalen Listener und die Liste liest
den Parameter nicht. Dadurch wird er nur verbraucht, wenn das Reader-Element im
tatsächlich gerenderten Seiteninhalt eingesetzt ist. Der dritte Parameter von
`Input::get()` bleibt beim Default `false`; der Zugriff entfernt `auto_item`
damit aus Contaos Liste der unbenutzten Routenparameter.

Belege in Contao 5.7.11:

* `vendor/contao/core-bundle/src/Routing/Enhancer/InputEnhancer.php` ordnet einen
  einzelnen zusätzlichen Pfadabschnitt `auto_item` zu und übergibt die erkannten
  Namen anschließend an `Input::setUnusedRouteParameters()`.
* `vendor/contao/core-bundle/contao/library/Contao/Input.php` liest
  `auto_item` aus den Request-Attributen. `Input::get()` entfernt den Namen bei
  seinem standardmäßigen dritten Argument `false` aus der Unused-Liste.
* `vendor/contao/core-bundle/src/EventListener/LegacyRouteParametersListener.php`
  wirft nach einer ansonsten erfolgreichen Frontend-Hauptantwort eine
  `UnusedArgumentsException`, falls Parameter unbenutzt geblieben sind.
* `vendor/contao/core-bundle/src/Routing/Page/PageRegistry.php` setzt bei
  `tl_page.requireItem` die Requirement für `parameters` auf einen zwingenden,
  nicht leeren Pfadwert.

Mit `requireItem = true` scheitert eine URL ohne Item daher bereits beim
Page-Routing. Bei `requireItem = false` bleibt die Seite routbar; ist dort der
Q&A-Reader eingesetzt, wirft er für den fehlenden Parameter selbst eine
`PageNotFoundException`. Unbekannte und unveröffentlichte Aliase ergeben
ebenfalls diese Exception; die Gateway-Abfrage schließt unveröffentlichte
Datensätze bereits mit `published = '1'` aus.

Wenn Liste und Reader auf derselben Seite liegen, liest die Liste den Parameter
nicht und der Reader verbraucht ihn. Eine URL mit Alias zeigt deshalb Liste und
Reader gemeinsam und funktioniert auch, wenn die Liste auf dieselbe Seite
weiterleitet. Eine URL ohne Alias ist für diese Kombination immer 404: entweder
schon im Routing (`requireItem = true`) oder durch den Reader
(`requireItem = false`). Die kombinierte Seite ist somit keine eigenständige
Listen-Übersichtsseite; für eine Übersicht ohne Alias müssen Liste und Reader
auf getrennten Seiten liegen.

## D6 – Persistenz (entschieden)

Das Paket verwendet ausschließlich **DBAL-Gateways**, keine
Contao-Models. Die benötigten Listenabfragen kombinieren Fragen, aggregierte
Vote-Zahl und den Vote-Status des aktuellen Mitglieds. DBAL erlaubt diese
gebündelten Abfragen sowie Transaktionen und eine gezielte Behandlung des
Unique-Constraint-Verstoßes, ohne eine zweite Persistenzabstraktion
einzuführen.

## D7 – Formularantwort (entschieden)

Die vier Schreibaktionen behalten den Post/Redirect/Get-Flow: POST mutiert
ausschließlich über den jeweiligen Service und antwortet mit `303 See Other`
auf den passenden GET-Endpunkt. Turbo fordert bei unsicheren Formularrequests
automatisch `text/vnd.turbo-stream.html` an und behält den Accept-Header beim
Redirect bei (`public/turbo.es2017-esm.js`, `FormSubmission.prepareRequest()`).
Die GET-Endpunkte antworten deshalb bei dieser Content-Negotiation mit
Turbo-Streams, bei normalen Frame-Polls weiterhin mit vollständigem
HTML-Frame-Markup.

Diese Erweiterung von D7 ist für die Reader-Aufteilung notwendig. Status und
Frageformular liegen im nicht gepollten Controls-Frame
`qna-session-<id>-reader`; Fragen und Votes liegen im gepollten Questions-Frame
`qna-session-<id>-questions`. Das Frageformular zielt per
`data-turbo-frame` auf den Questions-Frame. Nach erfolgreicher Erstellung
aktualisiert die Redirect-GET-Antwort die Liste und ersetzt den Controls-Inhalt
einmalig, wodurch das Feld geleert wird. Nach Vote, Start und Stopp
aktualisieren Streams nur ihren jeweiligen dynamischen Bereich. Die
Sortierlinks verwenden dagegen eine normale Frame-Navigation, damit Turbo die
gewählte URL als neue Frame-`src` übernimmt; das Bundle setzt für diese
Navigation den vorhandenen Turbo-Morph-Renderer ein, damit der Fokus an der
stabilen Link-ID erhalten bleibt.
Da Turbo Stream-Antworten vor einer Frame-Navigation abfängt
(`StreamObserver.inspectFetchResponse()`), wird der einmalige
`resetQuestionForm`-Redirect nicht zur dauerhaften `src` des Questions-Frames.

Normale Listen-Polls führen ein statusselektives Stream-Update mit: Nur ein
Controls-Knoten, dessen `data-qna-state` nicht dem aktuellen Serverstatus
entspricht, ist Ziel. Bei unverändertem `open` bleibt der bestehende
Textarea-DOM-Knoten samt Wert, Cursor und Auswahl unangetastet; bei
`waiting`/`closed` wird der Formularbereich spätestens durch den nächsten
Listen-Poll ersetzt. Es wird kein Formularzustand in JavaScript gespeichert.

Fachliche Ablehnungen bleiben HTML-Antworten mit `422 Unprocessable Entity`.
Für eine fehlgeschlagene Frame-Form-Submission lädt Turbo ausdrücklich die
Antwort in den ursprünglichen Frame statt in das mit `data-turbo-frame`
angegebene Erfolgsziel (`FrameController.formSubmissionFailedWithResponse()`
im ausgelieferten Turbo-Modul). Deshalb erscheint eine Fragenvalidierung im
Controls-Frame; der eingereichte Wert wird serverseitig erneut gerendert.
Fehlende Authentifizierung, fehlende Steuerberechtigung und CSRF-Abweisungen
behalten ihre harten HTTP-Statuscodes.

Polling-Reloads der Reader-Liste und der Bühne verwenden Morphing mit stabilen
IDs. Das Polling-Modul verschiebt außerdem Reloads, während der betreffende
Frame Fokus enthält oder beschäftigt ist. Dadurch bleiben Vote-Klicks,
Tastaturfokus und die Bühnen-Sortierumschaltung vor einem zeitgleichen Tausch
geschützt, ohne das Polling der außerhalb liegenden Frageneingabe anzuhalten.

Alle sieben Routen liegen durch `config/routes.yaml` im Frontend-Scope. Die
vier Aktionen akzeptieren nur POST und setzen `_token_check = true`; der in
`vendor/contao/core-bundle/src/EventListener/RequestTokenListener.php`
verifizierte Listener prüft dadurch bei zustandsbehafteten bzw.
authentifizierten Frontend-Requests das Feld `REQUEST_TOKEN`. Start und Stopp
prüfen zusätzlich `QNA_SESSION_CONTROL` und delegieren ihre Zustandsübergänge
an `SessionService`.

## D8: Beantwortet-Status

Ein boolesches DCA-Feld `answered` (Doctrine-Schemarepräsentation, Default false)
ersetzt den bisherigen Ausschluss dieses Status. `QuestionAnswerService` setzt
explizite Zielzustände idempotent. Dieser Service und `VoteService` sperren die
Frage mit `SELECT ... FOR UPDATE` in einer Transaktion, bevor sie den Status
prüfen. Die Autorisierung bleibt beim bestehenden `QNA_SESSION_CONTROL`-Voter.
Bühnenaktionen behalten den privaten 303/Stream-Ablauf und die Sortierung bei.

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

## D12: Front-End-Assets über Encore statt vendorierter Dateien (Refactor, 15.09.2026)

Die ursprüngliche Lösung — `public/qna.js`, `public/qna.css`, eine vendorierte
Turbo-Kopie und eine handgepflegte `public/manifest.json` — hat den Hausstandard
von Heimrich & Hannot parallel neu erfunden.
`heimrichhannot/contao-ux-turbo-encore` liefert mit
`assets/js/turbo_no_drive.js` exakt dieselbe Absicht
(`import * as Turbo from '@hotwired/turbo'; Turbo.session.drive = false;`) und
dieselbe Turbo-Version 8.0.23.

Die Zusage „kein Build-Step im Host-Projekt" im README war **kein bewusster
Produktentscheid**, sondern beim Bau entstanden und wurde übersehen. Sie ist
damit hinfällig.

Neue Struktur:

* `assets/js/qna.js` und `assets/css/qna.css` sind Quellen, `public/` entfällt
  vollständig. Damit ist der in `DECISIONS.md` seit jeher beschriebene
  Quellordner erstmals auch der tatsächliche.
* `src/Asset/EncoreExtension.php` deklariert den Entry `huh_qna`
  (`setRequiresCss(true)`) gemäß `HeimrichHannot\EncoreContracts\EncoreExtensionInterface`.
* Die drei Controller aktivieren Entrypoints über
  `HeimrichHannot\EncoreContracts\PageAssetsTrait::addPageEntrypoint()`. Ohne
  Fallback-Assets ist der Aufruf ohne Encore-Bundle ein No-Op — ein
  Nicht-Encore-Betrieb ist ausdrücklich nicht mehr vorgesehen.
* Turbo wird **nicht** von `qna.js` importiert und **nicht** vom Bundle
  aktiviert. Das Projekt aktiviert `huh_ux_turbo_encore` (mit Drive) oder
  `huh_ux_turbo_encore_no_drive` (ohne) in Layout oder Seite; beide sind
  Head-Scripts und legen die Instanz auf `window.Turbo`. Fehlt beides, meldet
  `qna.js` das auf der Konsole.

  **Begründung:** Ob Turbo Drive die Navigation abfängt, ist eine
  projektweite Entscheidung. Eine frühere Fassung hängte
  `huh_ux_turbo_encore_no_drive` hart an — das hätte in einem Projekt, das Drive
  bewusst nutzt, die Navigation still abgeschaltet.

  Eine bedingte Aktivierung („nur ergänzen, wenn noch kein Turbo-Entry aktiv
  ist") ist mit der vorhandenen API **nicht** zuverlässig umsetzbar:
  `EntryPointsBuilder::build()` führt drei Quellen erst zur Ausgabezeit zusammen
  — den `EntryBag` aus dem ResponseContext (Zeile 70), `tl_layout.encoreEntries`
  (Zeile 82) und die `tl_page`-Kette samt Vererbung (Zeile 94).
  `FrontendAsset::isActiveEntrypoint()` prüft nur den Bag und sieht die im
  Backend konfigurierten Entries also gar nicht. Der einzige Event des Bundles
  (`EncoreEnabledEvent`) greift eine Ebene höher. Belege:
  `vendor/heimrichhannot/contao-encore-bundle/src/EntryPoint/EntryPointsBuilder.php`,
  `src/Asset/FrontendAsset.php`.
* Entrypoints werden in `getContent()` bzw. `getResponse()` aktiviert, nicht in
  `__invoke()`: `FrontendAsset::addActiveEntrypoint()` schreibt in den
  ResponseContext und ist ein **stiller No-Op**, solange dieser nicht existiert —
  und er entsteht erst beim Rendern.

Belegte APIs: `heimrichhannot/contao-encore-contracts` 1.5.0
(`EncoreEntry.php`, `EncoreExtensionInterface.php`, `PageAssetsTrait.php`,
`AddPageEntrypointTrait.php`), `heimrichhannot/contao-ux-turbo-encore`
(`src/EncoreExtension.php`, Konstanten `DEFAULT` und `NO_DRIVER`).

**Offen:** `contao-ux-turbo-encore` ist nicht getaggt (nur `dev-main`, weder auf
packagist.org noch im Repository existiert eine Version), obwohl das dortige
CHANGELOG ein `[0.1.0]` ausweist. Bis zum Tag steht in der `composer.json`
`dev-main`. Danach auf `^0.1` umstellen und `composer update` ausführen.


## D13: Optional fest konfigurierte Reader-Session (17.09.2026)

`tl_content.qnaSession` bietet eine optionale Session-Auswahl einschließlich
unveröffentlichter Sessions. Ohne Auswahl bleibt D5 unverändert: Der Reader
liest und verbraucht `auto_item` und liefert bei fehlendem, unbekanntem oder
unveröffentlichtem Alias 404. Bei positiver ID wird ausschließlich
`findPublished()` verwendet, ohne den Input-Adapter anzufordern.

Ist die feste Session unveröffentlicht oder gelöscht, antwortet das Element
mit einer leeren Response (200). Eine redaktionelle Programmseite existiert
unabhängig von der eingebetteten Session und darf dadurch nicht unerreichbar
werden. Der Cache-Tag `contao.db.tl_qna_session.<id>` bleibt auch bei leerer
Ausgabe erhalten. Alternativen: 404 für die gesamte Seite oder Fallback auf
das URL-Item; beides würde die feste redaktionelle Zuordnung missachten.
`resolveSession(ContentModel)` liefert deshalb `?Session` statt des im Prompt
genannten nicht-nullbaren Rückgabetyps. Im Backend liefert `find()` auch den
Titel einer unveröffentlichten Session für den übersetzten Editor-Hinweis.

Die Liste bleibt unverändert. Ihre Links enthalten einen Alias als Item;
ein fester Reader verbraucht ihn nicht, sodass Contao korrekt 404 liefert.
Für Listenziele bleibt das Auswahlfeld deshalb leer.

Core-Belege unter `vendor/contao/core-bundle/`:

* `contao/dca/tl_content.php`, Feld `form`: Select, foreignKey,
  includeBlankOption, chosen und lazy hasOne-Relation. In den genannten
  `tl_content.php`/`tl_module.php` existiert kein CONCAT-Vorbild; die Auswahl
  verwendet deshalb `tl_qna_session.title`.
* `contao/library/Contao/Model.php::row()`: Zugriff auf rohe Felddaten;
  defensive int/string-Prüfung wie beim bestehenden Listen-Controller.
* `contao/library/Contao/Input.php::get()`: Der dritte Parameter ist
  standardmäßig false; der gelesene Route-Parameter wird verbraucht.
* `src/Controller/AbstractController.php::tagResponse()` und
  `src/Cache/CacheTagManager.php::tagWith()`: Tagging auch ohne Session-Objekt.
* `src/Twig/FragmentTemplate.php::set()`/`getResponse()` und
  `src/Controller/AbstractFragmentController.php::isBackendScope()`:
  Editor-Kontext und Scope-Trennung.

Auch Feature 1 erfordert eine Schemaergänzung (FEATURES.md §0.6 ist entsprechend korrigiert):
Das neue Feld in `tl_content` wird durch Contaos reguläre Schema-Migration
angelegt (Doctrine integer, unsigned, Default 0), wie im Feature-1-Prompt
explizit verlangt. Keine eigene Migrationsklasse ist nötig.

## D15: Backend-Hinweis bei fehlendem Turbo-Entry (17.09.2026)

Drei `config.onload`-Callbacks prüfen Q&A-Inhaltselemente im Edit-Modus,
Bühnenseiten im Edit-Modus sowie den Einstieg ins Q&A-Modul. Ein gemeinsamer
Prüfservice erkennt beide Turbo-Entries über die Konstanten `DEFAULT` und
`NO_DRIVER`; ein Meldungsservice übersetzt die Info und verhindert doppelte
Meldungen pro Request. Die notwendige veränderliche Guard-Eigenschaft ist die
begründete Ausnahme von `readonly`. Ein erfolgreicher Check verbraucht den
Guard nicht, damit ein späterer fehlender Entry noch gemeldet werden kann.

Alternative `onsubmit`: Ein Hinweis erst nach dem Speichern käme zu spät und
würde den Moduleinstieg nicht abdecken. Andere Inhaltstypen verlassen den
Callback vor jeder Modell- oder Schemaabfrage. Composer und Service-Konfiguration
bleiben unverändert, das Encore-Bundle wird nicht als Klasse referenziert.

### Nachgelesene Belege

Die folgenden Encore-Pfade liegen ausdrücklich im **DDEV-Projekt** unter
`/home/dev/Kunden/contao/contao_0507/vendor/heimrichhannot/contao-encore-bundle/`:

| Fakt | Pfad |
| --- | --- |
| Feldname `encoreEntries` | `src/Dca/EncoreEntriesSelectField.php::NAME_DEFAULT` |
| `blob NULL`, serialisierte Zeilen mit `entry` und optional `active` | `src/EventListener/DcaField/EncoreEntriesSelectFieldListener.php::onLoadDataContainer()` |
| Aktiv-Checkbox für beide Tabellen | `contao/dca/tl_layout.php`, `contao/dca/tl_page.php` |
| Fehlendes oder null `active` gilt als aktiv, sonst PHP-Truthy | `src/Asset/PageEntrypoints.php::generatePageEntrypoints()`; aktueller Nachfolger `src/EntryPoint/EntryPointsBuilder.php::build()` verwendet `active ?? true` |
| Layout und Seitenkette werden gesammelt | `src/Asset/PageEntrypoints.php::collectPageEntries()`, `src/EntryPoint/EntryPointsBuilder.php::build()` |
| `addEncore` ist Voraussetzung für die gesamte Seite | `src/Helper/ConfigurationHelper.php::isEnabledOnPage()`; `src/DataContainer/LayoutContainer.php::onLoadCallback()` |

Im Bundle-Repository nachgelesen:

| API | Pfad unter `vendor/` |
| --- | --- |
| Turbo-Konstanten | `heimrichhannot/contao-ux-turbo-encore/src/EncoreExtension.php` |
| Info-Meldung und Adapter-Vorbild | `contao/core-bundle/contao/library/Contao/Message.php::addInfo()`, `contao/core-bundle/src/EventListener/DataContainer/LegacyTemplatesListener.php` |
| Callback-Attribut, Request- und Record-Zugriff | `contao/core-bundle/src/DependencyInjection/Attribute/AsCallback.php`, `contao/core-bundle/src/EventListener/DataContainer/PreviewLinkListener.php` |
| Record ist Array oder null | `contao/core-bundle/contao/classes/DataContainer.php::getCurrentRecord()`, `contao/core-bundle/contao/drivers/DC_Table.php::getCurrentRecord()` |
| Vererbtes Layout und Trail | `contao/core-bundle/contao/models/PageModel.php::findWithDetails()` / `loadDetails()` |
| Modelle, rohe optionale Felder, Deserialisierung | `contao/core-bundle/contao/models/ArticleModel.php`, `contao/core-bundle/contao/models/LayoutModel.php`, `contao/core-bundle/contao/library/Contao/Model.php::findById()` / `row()`, `contao/core-bundle/contao/library/Contao/StringUtil.php::deserialize()` |
| Testbare Adapter | `contao/core-bundle/src/Framework/ContaoFramework.php::getAdapter()`, `contao/core-bundle/src/Framework/Adapter.php` |

### Präzisierungen gegenüber dem Prompt

* Ohne `addEncore` im effektiven Layout sind auch Seiten-Entries unwirksam:
  Die Seitenprüfung gibt deshalb sofort false zurück, statt nur den Layout-Blob
  zu überspringen. Die globale Prüfung bleibt die spezifizierte Suche nach
  Konfiguration, ohne Zuordnung sämtlicher Seiten zu Layouts.
* Der Session-Callback benötigt keinen Datensatz und keinen Edit-Modus; sonst
  würde die gemeinsame Record-Regel des Prompts den ausdrücklich verlangten
  Hinweis beim Moduleinstieg verhindern. Ohne Request tut er nichts.
* `trail` enthält auch die Seite selbst und gegebenenfalls 0. Diese werden
  übersprungen; die Seite wird zuletzt direkt geprüft.
* Schema-Spalten werden einmal je globaler Prüfung für **beide** Tabellen
  geprüft, einschließlich `addencore`. Optionale Modellfelder werden über
  `row()` gelesen; fehlende Encore-Felder verursachen keine Fehler.
* `PageEntrypoints` ist im installierten Vendor als deprecated markiert. Der
  aktuelle `EntryPointsBuilder` bestätigt die verwendete Zeilen-Semantik.
  Wie §3.3 vorgegeben prüft der Service das Vorhandensein aktiver Zeilen,
  nicht den fertigen Build oder programmatische Encore-Event-Overrides.

## D14: Neustart mit Durchgangszähler (17.09.2026)

Entscheidung gemäß FEATURES.md §2: Session und Fragen tragen `round` (unsigned
integer, Default 1). `restart()` öffnet nur eine geschlossene veröffentlichte
Session, erhöht den Zähler, ersetzt den Startzeitpunkt und leert den Endzeitpunkt.
Start bleibt auf `waiting` beschränkt. Alle Übergänge sperren zuerst die Session
und verwenden einen bedingten Status-UPDATE. Neue Fragen übernehmen den Durchgang
aus der gesperrten Zeile; die Sperrfolge Session → Frage → Vote bleibt erhalten.

Ein Archiv-Flag pro Frage wurde verworfen: Es würde beim Neustart alle Fragen
umschreiben und die Zuordnung mehrerer Durchgänge verlieren. Der Zähler macht
den Neustart zur O(1)-Operation; alte Fragen, Votes und Antwort-Markierungen
bleiben unverändert. Frontend-Listen filtern auf den aktuellen Durchgang.
Schreibzugriffe auf alte Fragen werden nach Frage/Zuordnung, Session-Existenz und
Offenheitsprüfung mit `QuestionArchivedException` (422) abgewiesen. Der Cooldown
bleibt als Missbrauchsschutz durchgangsübergreifend.

`StageView::hasControls()` umfasst Start, Stop und Restart. Beide Controller
verwenden diese Quelle für Token-Ausgabe bzw. Cacheentscheidung. Geschlossene
Operator-Frames bleiben daher privat; Zuschauer behalten die bisherige Cachepolitik.

Keine eigene Migration: Die DCA-Defaults ordnen alle Bestandsdaten Durchgang 1 zu.
Der Backend-Durchgang ist nicht editierbar; die Fragenliste zeigt ihn als zweites
Label-Feld und bietet ihn über `search,filter,limit` im Filterpanel an.

Nachgelesene Contao-Belege unter `vendor/contao/core-bundle/`:

* `contao/classes/DataContainer.php`: Sortierkonstanten und `generateRecordLabel()`
  mit `list.label.fields`/`format`.
* `contao/drivers/DC_Table.php:4476`: Elternansicht ruft `generateRecordLabel()` auf;
  `headerFields` und Filterpanel werden vom bestehenden Driver ausgewertet.
* `contao/dca/tl_content.php`: `rgxp => natural`.
* `src/Command/MigrateCommand.php`: nichtinteraktiv ohne `--with-deletes` werden
  fremde DROP-Vorschläge nicht ausgeführt; der ersetzte Index wird dennoch entfernt.
* `src/Csrf/ContaoCsrfTokenManager.php::getDefaultTokenValue()` und
  `src/Exception/PageNotFoundException.php`: bestehende Controller-APIs bestätigt.

Turbo-Bestätigung vor Templateänderung geprüft unter
`/home/dev/Kunden/contao/contao_0507/node_modules/@hotwired/turbo/dist/turbo.es2017-esm.js:1154-1161`:
`FormSubmission.start()` wertet `data-turbo-confirm` aus und bricht bei Ablehnung ab.

Reale DDL aus `ddev exec vendor/bin/contao-console contao:migrate --dry-run`:

```sql
DROP INDEX pid_createdat ON tl_qna_question
ALTER TABLE tl_qna_question ADD round INT UNSIGNED DEFAULT 1 NOT NULL
CREATE INDEX pid_round_createdat ON tl_qna_question (pid, round, createdat)
ALTER TABLE tl_qna_session ADD round INT UNSIGNED DEFAULT 1 NOT NULL
```

`contao:migrate --no-interaction` meldete `Executed 4 SQL queries`.
`round` wurde ohne Quoting akzeptiert. Der Demo-Host hat daneben bereits
bestehende, nicht ausgeführte DROP-Vorschläge für fremde Tabellen und Spalten;
ein global leerer Dry-Run ist deshalb keine zutreffende Zusage dieses Features.

EXPLAIN über `tests/Fixtures/explain-list.php` mit `QNA_DATABASE_TESTS=1
QNA_TEST_DB_NAME=db`: 50 Fragen im ausgewählten Durchgang, 950 in anderen
Durchgängen, 10.000 Votes. Ohne Index-Hint:

```text
table q: type=ref, key=pid_round_createdat, key_len=8,
         ref=const,const, rows=50, Extra=Using where; Using filesort
table v: type=eq_ref, key=pid_memberid, rows=1,
         Extra=Using where; Using index
```

Die ursprüngliche Fixture mit 50 von insgesamt 63 Fragen führte erwartungsgemäß
zu `type=ALL, key=NULL`. Die zusätzlichen Durchgänge machen den Filter selektiv;
dies ist Fixture-Evidenz, keine pauschale Produktions-Performancezusage.

Die Zwei-Prozess-Tests prüfen echten InnoDB-Lock-Wait einschließlich veralteter
Repeatable-Read-Snapshots. Wenn ein zulässiger Schreibzugriff zuerst gewinnt,
folgen Stop und Restart gemeinsam im zweiten Testprozess. Bei bereits geschlossener
Session wird eine zuerst eintreffende Einreichung abgewiesen; auch dieser Fall ist
separat abgedeckt. Ein Neustart legalisiert keine Frage in einer geschlossenen Session.

Browser-Verifikation im Demo-Host: Session 548 (`feature-2-restart`) wurde über
das Backend angelegt und veröffentlicht. Eigenständig geöffnete Frame-Formulare
führten Start, Frage, Antwort-Markierung, Stop und Restart erfolgreich aus.
Nach Restart waren Bühne und der weiter offene Reader leer; der Reader wechselte
von geschlossen zu offen. Backend-Label, Durchgangsfilter und `show` mit Durchgang 2
wurden sichtbar geprüft. Ein weiterer Restart auf Durchgang 3 ließ einen zuvor
geladenen Reader-Vote mit Archiv-Meldung enden; der Zähler der dafür zusätzlich
angelegten Fixture-Frage 1830 blieb 0. Testdaten bleiben als Demo-Nachweis erhalten.

Offene Browser-Verifikation: In der eingebetteten Bühnenansicht lösten die
geprüften Start-/Antwort-/Restart-Klicks keinen beobachteten POST aus. Polling
funktionierte; die eigenständigen Frame-Formulare funktionierten ebenfalls.
Eine Ursache ist nicht belegt. Der native Turbo-Bestätigungsdialog und der
vollständige eingebettete Restart-Flow sind daher **nicht verifiziert**.
Quellcode- und gerenderte Template-Tests ersetzen diesen Browsernachweis nicht.
Es wurden weder Turbo-Konfiguration noch Bundle-JavaScript geändert.
