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
