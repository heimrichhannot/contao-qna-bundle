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

## Structure
- `src/EventListener/Cron/`
    Cronjobs
- `src/EventListener/DataContainer/[Table]/`
    DCA Callback Listener. One Class per Callback. [Table ] is the table name without tl_ prefix and CamelCalse, for example Member for tl_member. Name the classes after the callback name with Listener suffix, for example ConfigOnLoadListener for 'config.onload' or FieldsExampleOptionsListener for 'fields.example.options'


