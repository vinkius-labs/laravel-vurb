# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-06-01

### Added

- **Core MCP Server** — Full Model Context Protocol implementation over SSE and HTTP transports
- **Tool Builder** — Fluent `defineTool()` API with automatic JSON Schema generation from PHP types
- **Presenter Layer** — MVA (Model-View-Agent) pattern with `definePresenter()` for AI-optimized responses
- **Middleware Pipeline** — `defineMiddleware()` with before/after hooks, ordering, and dependency injection
- **Security Layer** — `InputFirewall`, `PromptFirewall`, `RateLimiter`, `AuditTrail`, and `JudgeChain`
- **FSM State Gate** — Finite State Machine guard for tool access with `defineFsm()`
- **Governance** — `GovernancePolicy` and `ComplianceGate` for enterprise policy enforcement
- **Observability** — `DebugObserver`, OpenTelemetry tracing, and Telescope/Pulse integrations
- **State Sync** — Epistemic cache with `StateSync::register()` and configurable TTL
- **DLP Redaction** — `DlpRedactor` with configurable patterns for PII/sensitive data masking
- **Service Provider** — Auto-discovery with `VurbServiceProvider`, config publishing, and migration publishing
- **Artisan Commands** — `vurb:install`, `vurb:make-tool`, `vurb:make-presenter`, `vurb:make-middleware`
- **Testing Utilities** — `VurbTestCase`, `FakeMcpTransport`, `ToolAssertions` trait for full test coverage
- **Skill Publishing** — `llms.txt` and `SKILL.md` with `php artisan vendor:publish --tag=vurb-skills`
- **Laravel 11, 12 & 13** support with PHP 8.2+
- **750 tests, 1815 assertions, 97.59% code coverage**

[1.0.0]: https://github.com/vinkius-labs/laravel-vurb/releases/tag/v1.0.0
