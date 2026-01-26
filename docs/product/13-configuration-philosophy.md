FILENAME: 13-configuration-philosophy.md

# BusinessApp — Configuration Philosophy

Configuration should:
- happen early
- reflect real business behaviour
- reduce friction later

Defaults should be sensible.
Advanced options should stay hidden unless needed.

## Field Propagation & Change Rules

To ensure consistency and protect your historical data, BusinessApp follows strict rules when settings change.

The **Business Type** acts as the single source of truth for your data fields. Whenever you create a new Quote, Job, Task, or Order, it adopts the exact field configuration active at that moment. (See [Initial Setup and Business Type](01-initial-setup-and-business-type.md) for authoritative behavior on immutability.)

However, once a record is created, it is locked to the configuration under which it was born. This means that if you later update your Business Type settings, your existing records—including Drafts—will not automatically change. This behaviour ensures that your past work always remains accurate and predictable, regardless of how your business evolves.
