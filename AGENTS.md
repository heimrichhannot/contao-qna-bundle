- Read and follow `AGENTS.local.md` in the repository root if it exists for additional local instructions.
- Use Symfony PHP Translation Format for Contao and Other Translations where possible when creating new files.
- Use Doctrine Schema Representation for Contao DCA SQL Column Definition.
- Do not define a custom targetColumn for Contao DCA virtual fields unless explicitly requested.
- Use AbstractBundle class for the bundle class, if possible
- Register listeners with PHP attributes, never in configuration. Use `#[AsCallback]` for
  Contao DCA callbacks, `#[AsHook]` for Contao hooks, `#[AsEventListener]` for Symfony
  events, `#[AsCronJob]` for cronjobs, and the matching Contao attributes for content
  elements, frontend modules and insert tags. Do not add callbacks to DCA arrays
  (`'onload_callback' => [...]`), do not add hooks to `$GLOBALS['TL_HOOKS']`, and do not add
  `contao.callback` / `contao.hook` / `kernel.event_listener` tags to `services.yaml`.
  The attribute keeps the registration next to the code it registers.
- When several listeners share one callback target and their order matters: set an explicit
  `priority` on each attribute instead of relying on declaration order.
- Do not give your own concepts Contao-reserved names. The class suffix `*Model`
  and the directory `src/Model/` belong to Contao Active Record classes
  (`Contao\Model` subclasses registered in `$GLOBALS['TL_MODELS']`). Plain data
  structures, value objects and domain models that are not Active Record classes
  belong in `src/Domain/` and carry no `Model` suffix. The same reservation
  applies to `Entity` (Doctrine ORM) and to `*Controller` outside of
  `src/Controller/`.

## Structure
- `src/EventListener/Cron/`
    Cronjobs
- `src/EventListener/DataContainer/[Table]/`
    DCA Callback Listener. One Class per Callback. [Table ] is the table name without tl_ prefix and CamelCalse, for example Member for tl_member. Name the classes after the callback name with Listener suffix, for example ConfigOnLoadListener for 'config.onload' or FieldsExampleOptionsListener for 'fields.example.options'
- `src/Domain/`
  Domain models and value objects of the bundle itself: immutable, no database
  access, no framework base class. Not Contao models.
- `src/Model/`
  Contao Active Record classes only. One class per table, named `<Table>Model`, extending `Contao\Model`, registered in `$GLOBALS['TL_MODELS']`.