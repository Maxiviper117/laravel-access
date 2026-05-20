---
layout: home

hero:
  name: Laravel Access
  text: Explicit scoped roles and enum-first permissions
  tagline: Permission enums, polymorphic scopes, and zero implicit state.
  actions:
    - theme: brand
      text: Get Started
      link: /tutorials/getting-started
    - theme: alt
      text: View on GitHub
      link: https://github.com/Maxiviper117/laravel-access

features:
  - icon: 🎯
    title: Explicit Scopes
    details: Users have different roles in different contexts — companies, teams, or any Eloquent model. No global state, no team_id hacks.
  - icon: 🔒
    title: Enum-First Permissions
    details: PHP BackedEnums are the single source of truth. Compile-time safety, IDE autocomplete, synced to DB with access:sync.
  - icon: 🧩
    title: Hybrid RBAC
    details: Developers own permissions in code. End-users create roles at runtime. access:sync --prune never touches dynamic roles.
  - icon: ⚡
    title: Inertia-Ready
    details: Permission maps for your frontend with Access::for($user)->in($scope)->toArray([...]).
---
