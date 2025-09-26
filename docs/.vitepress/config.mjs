import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Bob Query Builder',
  description: 'A powerful, Laravel-like PHP query builder for modern applications',
  base: '/bob-the-builder/',

  themeConfig: {
    nav: [
      { text: 'Guide', link: '/guide/getting-started' },
      { text: 'API Reference', link: '/api/' },
      { text: 'Examples', link: '/examples/' },
      { text: 'GitHub', link: 'https://github.com/Marwen-Brini/bob-the-builder' }
    ],

    sidebar: {
      '/guide/': [
        {
          text: 'Getting Started',
          items: [
            { text: '✨ What\'s New (v2.2.0)', link: '/guide/whats-new' },
            { text: 'Introduction', link: '/guide/getting-started' },
            { text: 'Installation', link: '/guide/installation' },
            { text: 'Configuration', link: '/guide/configuration' },
            { text: 'Quick Start', link: '/guide/quick-start' }
          ]
        },
        {
          text: 'Core Features',
          items: [
            { text: 'Query Builder', link: '/guide/query-builder' },
            { text: 'Where Clauses', link: '/guide/where-clauses' },
            { text: 'Joins', link: '/guide/joins' },
            { text: 'Models', link: '/guide/models' }
          ]
        },
        {
          text: 'Advanced',
          items: [
            { text: 'Performance', link: '/guide/performance' },
            { text: 'Logging', link: '/guide/logging' },
            { text: 'Extending Bob', link: '/guide/extending' },
            { text: 'CLI Tools', link: '/guide/cli' },
            { text: 'Migration Guide', link: '/guide/migration' },
            { text: 'Troubleshooting', link: '/guide/troubleshooting' },
            { text: '📝 Changelog', link: '/guide/CHANGELOG' }
          ]
        }
      ],
      '/api/': [
        {
          text: 'API Reference',
          items: [
            { text: 'Complete API', link: '/api/' }
          ]
        }
      ],
      '/examples/': [
        {
          text: 'Examples',
          items: [
            { text: 'Basic Examples', link: '/examples/' }
          ]
        }
      ]
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/Marwen-Brini/bob-the-builder' }
    ],

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2025 Marwen Brini'
    },

    search: {
      provider: 'local'
    }
  },

  markdown: {
    lineNumbers: true
  }
})