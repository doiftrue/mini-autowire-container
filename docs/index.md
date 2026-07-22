---
layout: home

hero:
  name: LiteWire DI
  text: A small autowiring container for PHP
  tagline: One portable PHP file, no runtime dependencies, and a familiar get() / has() API.
  image:
    src: /logo.svg
    alt: LiteWire DI logo
  actions:
    - theme: brand
      text: Get started
      link: /guide/getting-started
    - theme: alt
      text: Documentation
      link: /guide/full-documentation

features:
  - title: Portable
    details: Install with Composer or copy one PHP file into a small application, plugin, theme, or library.
  - title: Autowiring
    details: Resolve concrete constructor dependencies automatically and explicitly bind interface implementations.
  - title: Predictable lifetimes
    details: Use get() for shared services and make() when a fresh instance is required.
---

## Start with the smallest useful setup

Install the package, create a container, and request a concrete class. LiteWire DI builds its typed constructor dependencies automatically.

```bash
composer require doiftrue/litewire-di
```

Read the [getting-started guide](/guide/getting-started) for a short example, or continue to the full [container documentation](/guide/full-documentation).
