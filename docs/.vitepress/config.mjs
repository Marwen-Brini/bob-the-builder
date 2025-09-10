import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Bob Query Builder',
  description: 'A powerful, Laravel-like PHP query builder for modern applications',
  
  themeConfig: {
    logo: '/logo.svg',
    
    nav: [
      { text: 'Guide', link: '/guide/getting-started' },
      { text: 'API Reference', link: '/api/' },
      { text: 'Examples', link: '/examples/' },
      {
        text: 'v1.0.0',
        items: [
          { text: 'Changelog', link: '/changelog' },
          { text: 'Contributing', link: '/contributing' }
        ]
      }
    ],

    sidebar: {
      '/guide/': [
        {
          text: 'Introduction',
          items: [
            { text: 'Getting Started', link: '/guide/getting-started' },
            { text: 'Installation', link: '/guide/installation' },
            { text: 'Configuration', link: '/guide/configuration' },
            { text: 'Quick Start', link: '/guide/quick-start' }
          ]
        },
        {
          text: 'Core Concepts',
          items: [
            { text: 'Query Builder', link: '/guide/query-builder' },
            { text: 'Connections', link: '/guide/connections' },
            { text: 'Models', link: '/guide/models' },
            { text: 'Transactions', link: '/guide/transactions' }
          ]
        },
        {
          text: 'Query Building',
          items: [
            { text: 'Selects', link: '/guide/selects' },
            { text: 'Where Clauses', link: '/guide/where-clauses' },
            { text: 'Joins', link: '/guide/joins' },
            { text: 'Aggregates', link: '/guide/aggregates' },
            { text: 'Ordering & Grouping', link: '/guide/ordering-grouping' },
            { text: 'Pagination', link: '/guide/pagination' },
            { text: 'Unions', link: '/guide/unions' },
            { text: 'Raw Expressions', link: '/guide/raw-expressions' }
          ]
        },
        {
          text: 'Advanced Features',
          items: [
            { text: 'Logging', link: '/guide/logging' },
            { text: 'Extending Bob', link: '/guide/extending' },
            { text: 'Macros', link: '/guide/macros' },
            { text: 'Query Scopes', link: '/guide/scopes' },
            { text: 'Dynamic Finders', link: '/guide/dynamic-finders' },
            { text: 'Performance', link: '/guide/performance' },
            { text: 'Caching', link: '/guide/caching' },
            { text: 'Connection Pooling', link: '/guide/connection-pooling' },
            { text: 'Query Profiling', link: '/guide/profiling' }
          ]
        },
        {
          text: 'Database Support',
          items: [
            { text: 'MySQL', link: '/guide/mysql' },
            { text: 'PostgreSQL', link: '/guide/postgresql' },
            { text: 'SQLite', link: '/guide/sqlite' },
            { text: 'Custom Drivers', link: '/guide/custom-drivers' }
          ]
        },
        {
          text: 'Integrations',
          items: [
            { text: 'WordPress', link: '/guide/wordpress' },
            { text: 'Laravel', link: '/guide/laravel' },
            { text: 'Symfony', link: '/guide/symfony' },
            { text: 'Slim Framework', link: '/guide/slim' }
          ]
        },
        {
          text: 'CLI',
          items: [
            { text: 'CLI Overview', link: '/guide/cli' },
            { text: 'Test Connection', link: '/guide/cli-test' },
            { text: 'Build Queries', link: '/guide/cli-build' }
          ]
        }
      ],
      '/api/': [
        {
          text: 'API Reference',
          items: [
            { text: 'Overview', link: '/api/' },
            { text: 'Builder', link: '/api/builder' },
            { text: 'Connection', link: '/api/connection' },
            { text: 'Model', link: '/api/model' },
            { text: 'Grammar', link: '/api/grammar' },
            { text: 'Processor', link: '/api/processor' },
            { text: 'Expression', link: '/api/expression' }
          ]
        },
        {
          text: 'Interfaces',
          items: [
            { text: 'BuilderInterface', link: '/api/interfaces/builder' },
            { text: 'ConnectionInterface', link: '/api/interfaces/connection' },
            { text: 'GrammarInterface', link: '/api/interfaces/grammar' },
            { text: 'ProcessorInterface', link: '/api/interfaces/processor' },
            { text: 'ExpressionInterface', link: '/api/interfaces/expression' }
          ]
        },
        {
          text: 'Exceptions',
          items: [
            { text: 'QueryException', link: '/api/exceptions/query' },
            { text: 'ConnectionException', link: '/api/exceptions/connection' },
            { text: 'GrammarException', link: '/api/exceptions/grammar' }
          ]
        }
      ],
      '/examples/': [
        {
          text: 'Examples',
          items: [
            { text: 'Basic Queries', link: '/examples/' },
            { text: 'Complex Joins', link: '/examples/joins' },
            { text: 'Subqueries', link: '/examples/subqueries' },
            { text: 'WordPress Integration', link: '/examples/wordpress' },
            { text: 'Model Usage', link: '/examples/models' },
            { text: 'Migrations', link: '/examples/migrations' },
            { text: 'Real World Apps', link: '/examples/real-world' }
          ]
        }
      ]
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/Marwen-Brini/bob-the-builder' }
    ],

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2024 Marwen Brini'
    },

    search: {
      provider: 'local'
    },

    editLink: {
      pattern: 'https://github.com/Marwen-Brini/bob-the-builder/edit/main/docs/:path',
      text: 'Edit this page on GitHub'
    }
  },

  markdown: {
    lineNumbers: true,
    theme: {
      light: 'github-light',
      dark: 'github-dark'
    }
  }
})