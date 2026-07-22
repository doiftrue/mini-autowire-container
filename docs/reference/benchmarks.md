# Benchmarks

These are the latest detailed LiteWire DI measurements. Timings depend on PHP, hardware, extensions, and system load; compare only runs made in an equivalent environment.

## What the results mean

**LiteWire DI is fast.** Resolving a small object graph for the first time added about `0.002 ms` in this benchmark.

- **100 similar first-time resolutions:** roughly `0.2 ms` of container overhead.
- **1,000 similar first-time resolutions:** roughly `2 ms`.
- **Repeated `get()` call:** about `0.00006 ms` because the shared object is already stored.
- **Three-level autowiring:** less than `0.003 ms` on the first resolution.

Most plugins create far fewer than 1,000 services, and shared services are resolved only once. For a typical small application or WordPress plugin, the container overhead is effectively negligible.

The 100- and 1,000-resolution figures are simple linear illustrations based on the measured cold overhead. Real results depend on constructor complexity, dependency depth, PHP, and hardware.

## Latest run

- Date: 2026-07-06
- PHP: 8.5.5
- PHPBench: 1.7.0
- OPcache: enabled
- Xdebug: disabled
- Failures and errors: 0

| Subject | Runs × rounds | Memory peak | Time | Variance |
| --- | ---: | ---: | ---: | ---: |
| `direct_instantiation` | 10,000 × 5 | 666.744 KB | 0.078 μs | ±2.36% |
| `get__cold` | 10,000 × 5 | 16.422 MB | 2.055 μs | ±1.38% |
| `get__stored` | 10,000 × 5 | 666.720 KB | 0.060 μs | ±2.35% |
| `make__reflection__cold` | 10,000 × 5 | 15.862 MB | 1.986 μs | ±1.44% |
| `make__reflection__cached` | 10,000 × 5 | 666.744 KB | 0.799 μs | ±1.57% |
| `make__registered_factory` | 10,000 × 5 | 666.744 KB | 0.419 μs | ±2.68% |
| `get__deep_autowiring__cold` | 10,000 × 5 | 18.467 MB | 2.932 μs | ±0.86% |
| `get__deep_autowiring__stored` | 10,000 × 5 | 666.744 KB | 0.059 μs | ±4.11% |
| `has__resolvable_class__cold` | 10,000 × 5 | 11.356 MB | 2.232 μs | ±2.92% |
| `has__resolvable_class__stored` | 10,000 × 5 | 739.432 KB | 0.120 μs | ±2.64% |

`get__cold` includes reflection, dependency discovery, object construction, and storage of the shared result. `get__stored` measures a later lookup. `make()` always creates a fresh root object; its cached benchmark follows an earlier resolution in the same container.

::: info About memory figures
Memory peak is measured for the complete benchmark process, not as memory allocated by one container call. Do not multiply it by the number of services.
:::

---

::: info Additional information
The authoritative source for the full benchmark methodology and subject descriptions is [`benchmarks/README.md`](https://github.com/doiftrue/litewire-di/blob/main/benchmarks/README.md).
:::
