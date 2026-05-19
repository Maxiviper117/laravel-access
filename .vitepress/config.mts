import { defineConfig } from 'vitepress'

export default defineConfig({
  srcDir: 'docs',
  base: '/laravel-access/',

  title: 'Laravel Access',
  description: 'Explicit scoped roles and enum-first permissions for Laravel applications',

  themeConfig: {
    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Overview', link: '/' },
          { text: 'Getting started', link: '/tutorials/getting-started' },
        ],
      },
      {
        text: 'How-to Guides',
        items: [
          { text: 'Complete example', link: '/how-to/complete-example' },
          { text: 'Scaffold team scopes', link: '/how-to/scaffold-team-scopes' },
          { text: 'Define permissions', link: '/how-to/define-permissions' },
          { text: 'Configure roles', link: '/how-to/configure-roles' },
          { text: 'Use policies', link: '/how-to/use-policies' },
          { text: 'Share with Inertia', link: '/how-to/share-with-inertia' },
          { text: 'Debug access', link: '/how-to/debug-access' },
        ],
      },
      {
        text: 'Explanation',
        items: [
          { text: 'Mental model', link: '/explanation/mental-model' },
          { text: 'Scopes', link: '/explanation/scopes' },
          { text: 'Policies', link: '/explanation/policies' },
          { text: 'Caching', link: '/explanation/caching' },
        ],
      },
      {
        text: 'Reference',
        items: [
          { text: 'Configuration', link: '/reference/configuration' },
          { text: 'User API', link: '/reference/user-api' },
          { text: 'Commands', link: '/reference/commands' },
          { text: 'Middleware', link: '/reference/middleware' },
          { text: 'Database', link: '/reference/database' },
        ],
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/Maxiviper117/laravel-access' },
    ],

    search: {
      provider: 'local',
    },
  },
})
