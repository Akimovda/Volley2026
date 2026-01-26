# Changelog
All notable changes to this project will be documented in this file.  
See [standard-version](https://github.com/conventional-changelog/standard-version) for commit guidelines.

## [v0.3.0](https://github.com/Akimovda/Volley2026/compare/v0.1.1...v0.3.0) (2026-01-26)

### Features
- Auth providers: Telegram / VK / Yandex (login, link, unlink)
- Profile privacy toggle + отображение контактов (Telegram / VK) на публичном профиле
- Locations + Events scaffolding (models, migrations, create page)
- Event scheduling with timezone support
- New main layout component: `voll-layout`

### Infrastructure
- GitHub Actions CI for backend (PostgreSQL + PHPUnit)
- Release automation based on CHANGELOG

---

## [v0.1.1](https://github.com/Akimovda/Volley2026/compare/v0.1.1-alpha.0...v0.1.1) (2026-01-05)

### Documentation
- Normalize events, profile and developer documentation

---

## [v0.1.1-alpha.0](https://github.com/Akimovda/Volley2026/compare/v0.1-alpha...v0.1.1-alpha.0) (2026-01-05)

### Documentation
- Add changelog and release automation

---

## [v0.1-alpha](https://github.com/Akimovda/Volley2026/releases/tag/v0.1-alpha)

### Features
- Telegram + VK ID authentication (primary / secondary)
- Events page `/events`
- Join / leave event
- Profile completeness check on event registration
- `/profile/complete` with `required` fields and legacy `section` support
- Player profile `/profile/extra`
- Centralized project styles

### Documentation
- Added docs: status, auth, events, profile, dev, architecture, ADR
