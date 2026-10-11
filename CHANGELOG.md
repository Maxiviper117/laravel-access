# Changelog

## [0.1.10](https://github.com/Maxiviper117/laravel-access/compare/v0.1.9...v0.1.10) (2026-10-11)


### Features

* **access:** support dynamic permission definitions ([#49](https://github.com/Maxiviper117/laravel-access/issues/49)) ([b3f6d05](https://github.com/Maxiviper117/laravel-access/commit/b3f6d057e4989fecc7b05b62c1d8bc85cc300d96))

## [0.1.9](https://github.com/Maxiviper117/laravel-access/compare/v0.1.8...v0.1.9) (2026-10-11)


### Bug Fixes

* **cache:** prevent permission cache identity collisions ([#43](https://github.com/Maxiviper117/laravel-access/issues/43)) ([79a4d68](https://github.com/Maxiviper117/laravel-access/commit/79a4d68b87ba7a50d0b2efce46239a0d0abc24c3))
* **scope:** enforce scoped permission for generated invites ([#42](https://github.com/Maxiviper117/laravel-access/issues/42)) ([ba0deb5](https://github.com/Maxiviper117/laravel-access/commit/ba0deb5a1a1960846fd1808093dc5d3001b90ad0))
* **seeder:** require existing owner for generated seeders ([#46](https://github.com/Maxiviper117/laravel-access/issues/46)) ([e6d2eb7](https://github.com/Maxiviper117/laravel-access/commit/e6d2eb7949db505ed5daede4933e77d6d6bb43f6))

## [0.1.8](https://github.com/Maxiviper117/laravel-access/compare/v0.1.7...v0.1.8) (2026-10-11)


### Bug Fixes

* Enforce existing-user invitation policy ([#36](https://github.com/Maxiviper117/laravel-access/issues/36)) ([58481f8](https://github.com/Maxiviper117/laravel-access/commit/58481f8fb3710b72ec717af2a2855fde825a658a))
* Invalidate old permission cache after assignment changes ([#38](https://github.com/Maxiviper117/laravel-access/issues/38)) ([f2c6e3f](https://github.com/Maxiviper117/laravel-access/commit/f2c6e3f9eb054956a3f7ceb744c7f6fbd573cca1))
* Prevent scope admins from inviting higher roles ([#35](https://github.com/Maxiviper117/laravel-access/issues/35)) ([e6e4672](https://github.com/Maxiviper117/laravel-access/commit/e6e4672cdca86e2cd38e30f363a30939bf4bcf0f))

## [0.1.7](https://github.com/Maxiviper117/laravel-access/compare/v0.1.6...v0.1.7) (2026-10-10)


### Bug Fixes

* enforce role scope during permission checks ([#33](https://github.com/Maxiviper117/laravel-access/issues/33)) ([04e2812](https://github.com/Maxiviper117/laravel-access/commit/04e2812a7211406d00d09cebcd9edc15f01f73c3))

## [0.1.6](https://github.com/Maxiviper117/laravel-access/compare/v0.1.5...v0.1.6) (2026-10-10)


### Bug Fixes

* enforce uniqueness for global access records ([#31](https://github.com/Maxiviper117/laravel-access/issues/31)) ([214629b](https://github.com/Maxiviper117/laravel-access/commit/214629b3b610fa4b12dc52c6cfbdf0aefd92bf86))

## [0.1.5](https://github.com/Maxiviper117/laravel-access/compare/v0.1.4...v0.1.5) (2026-10-10)


### Features

* add global permission revoke helper ([#26](https://github.com/Maxiviper117/laravel-access/issues/26)) ([b213994](https://github.com/Maxiviper117/laravel-access/commit/b21399417ac24e2bb6cc2390272c58537aef501c))

## [0.1.4](https://github.com/Maxiviper117/laravel-access/compare/v0.1.3...v0.1.4) (2026-10-10)


### Bug Fixes

* Enforce global and scoped role boundaries ([#24](https://github.com/Maxiviper117/laravel-access/issues/24)) ([f3d812c](https://github.com/Maxiviper117/laravel-access/commit/f3d812c289da481d1b12313ff6e970f7fa76979a))

## [0.1.3](https://github.com/Maxiviper117/laravel-access/compare/v0.1.2...v0.1.3) (2026-07-10)


### Bug Fixes

* Harden authorization record integrity ([#21](https://github.com/Maxiviper117/laravel-access/issues/21)) ([68b8399](https://github.com/Maxiviper117/laravel-access/commit/68b83995edaf01288b7fe24b783262502cd82a2c))
* Improve access integrity ([#20](https://github.com/Maxiviper117/laravel-access/issues/20)) ([5911701](https://github.com/Maxiviper117/laravel-access/commit/5911701fc91981e74148cbe05ad572df3c9468f6))

## [0.1.2](https://github.com/Maxiviper117/laravel-access/compare/v0.1.1...v0.1.2) (2026-05-25)


### Features

* add access:seeder command and document scope starter kit support ([#6](https://github.com/Maxiviper117/laravel-access/issues/6)) ([f443172](https://github.com/Maxiviper117/laravel-access/commit/f4431722397cd3d40ea0ab903d2f669e2521cc08))


### Bug Fixes

* Harden AccessContext role handling and update docs ([#13](https://github.com/Maxiviper117/laravel-access/issues/13)) ([7d8d96c](https://github.com/Maxiviper117/laravel-access/commit/7d8d96c14347dae5360dbce07657c521b4c33fd2))

## [0.1.1](https://github.com/Maxiviper117/laravel-access/compare/v0.1.0...v0.1.1) (2026-05-20)


### Bug Fixes

* add Rector and resolve PHPStan level 10 type errors ([#4](https://github.com/Maxiviper117/laravel-access/issues/4)) ([c1bb5f7](https://github.com/Maxiviper117/laravel-access/commit/c1bb5f788664259ac2c521cbb4d87abe13d9a57b))

## 0.1.0 (2026-05-20)


### Features

* Implement Laravel Access Package with Enhanced Features ([#1](https://github.com/Maxiviper117/laravel-access/issues/1)) ([ce68c12](https://github.com/Maxiviper117/laravel-access/commit/ce68c12d7d56b42c00d8af448e5e1811dc096d12))

## Changelog
